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

namespace Import\Services;

use Espo\Services\QueueManagerBase;

class ImportJobCreator extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        $importFeed = $this->getEntityManager()->getRepository('ImportFeed')->get($data['importFeedId']);
        if (empty($importFeed)) {
            return false;
        }

        $attachment = $this->getEntityManager()->getEntity('File', $data['attachmentId']);
        if (empty($attachment)) {
            return false;
        }

        $payload = !empty($data['payload']) ? json_decode(json_encode($data['payload'])) : new \stdClass();
        $priority = $data['priority'];

        /** @var \Espo\Core\ServiceFactory $serviceFactory */
        $serviceFactory = $this->getContainer()->get('serviceFactory');

        // create converted file for parent job
        if (!empty($payload->parentJobId)) {
            $parentJob = $this->getEntityManager()->getRepository('ImportJob')->get($payload->parentJobId);
            $jobData = $this->getImportTypeSimple()
                ->prepareJobData($parentJob->get('importFeed'), $data['attachmentId']);
            $this->getImportTypeSimple()->createConvertedFile($payload->parentJobId, $jobData);
        }

        if (!array_key_exists('jobData', $data)) {
            $data['jobData'] = [];
        }

        $data['jobData']['importJobCreatorId'] = $this->qmItem->get('id');

        /** @var ImportFeed $importFeedService */
        $importFeedService = $serviceFactory->create('ImportFeed');

        /** @var \Atro\Services\File $fileService */
        $fileService = $serviceFactory->create('File');

        $isFileHeaderRow = !empty($importFeed->getFeedField('isFileHeaderRow'));

        $fileParser = $importFeedService->getFileParser($importFeed->getFeedField('format'));
        $fileParser->setData([
            'isFileHeaderRow' => $isFileHeaderRow,
            'delimiter'       => $importFeed->getDelimiter(),
            'enclosure'       => $importFeed->getEnclosure(),
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

        $maxPerJob = (int)$importFeed->get('maxPerJob');
        $partNumber = 1;
        while (!empty($fileData = $fileParser->getFileData($attachment, $offset, $maxPerJob))) {
            $part = array_merge($header, $fileData);
            $fileExt = $importFeed->getFeedField('format') === 'CSV' ? 'csv' : 'xlsx';

            $input = new \stdClass();
            $input->name = date('Y-m-d H:i:s') . ' (' . $partNumber . ')' . '.' . $fileExt;
            $input->hidden = true;
            $input->folderId = $importFeedService->createImportFileFolder($importFeed)->get('id');

            $jobAttachment = $fileService->createFileViaContents($input, $fileParser->createFileContent($part));

            $jobData = $service->prepareJobData($importFeed, $jobAttachment['id']);
            if (!empty($priority)) {
                $jobData['data']['priority'] = $priority;
            }
            $jobData['sheet'] = 0;
            $jobData['rowNumberPart'] = $rowNumberPart;
            $jobData['data']['importJobId'] = $importFeedService
                ->createImportJob($importFeed, $importFeed->getFeedField('entity'), $attachment->get('id'), $payload, $jobAttachment['id'])
                ->get('id');

            if (!empty($data['jobData']) && is_array($data['jobData'])) {
                $jobData = array_merge($jobData, $data['jobData']);
            }

            $importFeedService->push($importFeedService->getName($importFeed) . ' (' . $partNumber . ')', $serviceName, $jobData);

            $offset = $offset + $maxPerJob;
            $rowNumberPart = $rowNumberPart + $maxPerJob;
            $partNumber++;
        }

        return true;
    }

    protected function getImportTypeSimple(): \Import\Services\ImportTypeSimple
    {
        return $this->getContainer()->get('serviceFactory')->create('ImportTypeSimple');
    }
}
