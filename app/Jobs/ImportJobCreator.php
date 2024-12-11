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

        $isFileHeaderRow = !empty($importFeed->getFeedField('isFileHeaderRow'));

        $fileParser = $importFeedService->getFileParser($format);
        $fileParser->setData([
            'isFileHeaderRow' => $isFileHeaderRow,
            'delimiter'       => $delimiter,
            'enclosure'       => $enclosure,
            'sheet'           => $importFeed->get('sheet') ?? 0,
        ]);

        $fileParser->convertAttachmentToUTF8($attachment);

        $offset = 0;
        $rowNumberPart = 0;

        $header = [];
        if ($isFileHeaderRow) {
            $header = $fileParser->getFileData($attachment, 0, 1);
            $offset = 1;
        }

        $serviceName = $importFeedService->getImportTypeService($importFeed);
        $service = $serviceFactory->create($serviceName);

        $partNumber = 1;
        while (!empty($fileData = $fileParser->getFileData($attachment, $offset, $maxPerJob))) {
            $part = array_merge($header, $fileData);
            $fileExt = $format === 'CSV' ? 'csv' : 'xlsx';

            $input = new \stdClass();
            $input->name = date('Y-m-d H:i:s') . ' (' . $partNumber . ')' . '.' . $fileExt;
            $input->hidden = true;
            $input->folderId = $importFeedService->createImportFileFolder($importFeed)->get('id');

            $jobAttachment = $fileService->createFileViaContents($input, $fileParser->createFileContent($part));

            $jobData = $service->prepareJobData($importFeed, $jobAttachment['id']);
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
                ->createImportJob($importFeed, $importFeed->getFeedField('entity'), $jobAttachment['id'], $payload)
                ->get('id');

            if (!empty($data['jobData']) && is_array($data['jobData'])) {
                $jobData = array_merge($jobData, $data['jobData']);
            }

            $importFeedService->push($importFeedService->getName($importFeed) . ' (' . $partNumber . ')', $serviceName, $jobData);

            $offset = $offset + $maxPerJob;
            $rowNumberPart = $rowNumberPart + $maxPerJob;
            $partNumber++;
        }
    }
}
