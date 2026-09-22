<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Import\Jobs;

use Atro\Entities\Job;
use Atro\Jobs\AbstractJob;
use Atro\Jobs\JobInterface;
use Import\Services\ImportFeed;

class ImportJobCreator extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $this->runNow($job->getPayload(), $job);
    }

    public function runNow(array $data, Job $job = null): void
    {
        $importFeed = $this->getEntityManager()->getRepository('ImportFeed')->get($data['importFeedId']);
        if (empty($importFeed)) {
            return;
        }

        $attachment = $this->getEntityManager()->getEntity('File', $data['attachmentId']);
        if (empty($attachment)) {
            return;
        }

        $payload = !empty($data['payload']) ? json_decode(json_encode($data['payload'])) : new \stdClass();
        $priority = $data['priority'];

        $maxPerJob = $payload->maxPerJob ?? (int)$importFeed->get('maxPerJob');
        $format = $payload->format ?? $importFeed->getFeedField('format');
        $delimiter = $payload->delimiter ?? $importFeed->getDelimiter();
        $enclosure = $payload->enclosure ?? $importFeed->getEnclosure();

        $serviceFactory = $this->getServiceFactory();

        if (!array_key_exists('jobData', $data)) {
            $data['jobData'] = [];
        }

        if (!empty($job)) {
            $data['jobData']['importJobCreatorId'] = $job->get('id');
        }

        /** @var ImportFeed $importFeedService */
        $importFeedService = $serviceFactory->create('ImportFeed');

        /** @var \Atro\Services\File $fileService */
        $fileService = $serviceFactory->create('File');

        // a caller can force these instead of trusting the feed's own configuration - e.g. database-type
        // feeds don't expose headerRowNumber/dataStartRowNumber at all, so ImportTypeDatabaseJobCreator
        // passes the generated file's actual (fixed) layout through the payload
        $headerRowNumber = (int)($data['headerRowNumber'] ?? $importFeed->getFeedField('headerRowNumber') ?? 0);
        $dataStartRowNumber = (int)($data['dataStartRowNumber'] ?? $importFeed->getFeedField('dataStartRowNumber') ?? 1);

        $fileParser = $importFeedService->getFileParser($format);
        $fileParser->setData([
            'headerRowNumber'    => $headerRowNumber,
            'dataStartRowNumber' => $dataStartRowNumber,
            'delimiter'          => $delimiter,
            'enclosure'          => $enclosure,
            'sheet'              => $importFeed->get('sheet') ?? 0,
        ]);

        $fileParser->convertAttachmentToUTF8($attachment);

        $header = [];
        if ($headerRowNumber > 0) {
            $header = $fileParser->getFileData($attachment, $headerRowNumber - 1, 1);
        }
        $offset = $dataStartRowNumber - 1;
        $rowNumberPart = 0;

        $service = $importFeedService->getImportTypeService($importFeed);
        $this->getMemoryStorage()->set('disableFileTransactions', true);

        $partNumber = 1;
        while (!empty($fileData = $fileParser->getFileData($attachment, $offset, $maxPerJob))) {
            $part = array_merge($header, $fileData);
            $fileExt = $format === 'CSV' ? 'csv' : 'xlsx';

            $input = new \stdClass();
            $input->name = ImportFeed::generateFileName(date('Y-m-d H:i:s') . ' (' . $partNumber . ')' . '.' . $fileExt);
            $input->importFeedId = $importFeed->get('id');
            if (!empty($job)) {
                $input->importJobId = $job->get('id');
            }
            $input->folderId = $importFeedService->createImportFileFolder($importFeed)->get('id');

            $jobAttachmentId = $fileService->createFileViaContents($input, $fileParser->createFileContent($part));

            // this part file was assembled with its own header copied to row 1 (gap rows already
            // dropped, see $header/$offset above), regardless of where the feed's own header sits -
            // tell prepareJobData about the part file's actual layout so it resolves sourceFields
            // (and offset) against row 1, not against the feed's original, possibly non-adjacent one
            $partHeaderRowNumber = $headerRowNumber > 0 ? 1 : 0;
            $partDataStartRowNumber = $headerRowNumber > 0 ? 2 : 1;

            $jobData = $service->prepareJobData($importFeed, $jobAttachmentId, $partHeaderRowNumber, $partDataStartRowNumber);
            if (!empty($payload->format)) {
                $jobData['fileFormat'] = $payload->format;
            }
            if (!empty($payload->delimiter)) {
                $jobData['delimiter'] = $payload->delimiter;
            }
            if (!empty($payload->enclosure)) {
                $jobData['enclosure'] = $payload->enclosure;
            }

            if (!empty($priority)) {
                $jobData['data']['priority'] = $priority;
            }
            $jobData['sheet'] = 0;
            $jobData['rowNumberPart'] = $rowNumberPart;
            $jobData['data']['importJobId'] = $importFeedService
                ->createImportJob($importFeed, $importFeed->getFeedField('entity'), $jobAttachmentId, $payload)
                ->get('id');

            if (!empty($data['jobData']) && is_array($data['jobData'])) {
                $jobData = array_merge($jobData, $data['jobData']);
            }

            $importFeedService->push($importFeedService->getName($importFeed) . ' (' . $partNumber . ')', 'ImportType' . ucfirst($importFeed->get('type')), $jobData);

            $offset = $offset + $maxPerJob;
            $rowNumberPart = $rowNumberPart + $maxPerJob;
            $partNumber++;
        }

        $this->getMemoryStorage()->delete('disableFileTransactions');
    }
}
