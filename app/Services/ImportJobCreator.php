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

use Espo\Core\FilePathBuilder;
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

        /** @var ImportFeed $importFeedService */
        $importFeedService = $serviceFactory->create('ImportFeed');

        /** @var \Espo\Core\FilePathBuilder $filePathBuilder */
        $filePathBuilder = $this->getContainer()->get('filePathBuilder');

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
            $jobAttachment = $this->getEntityManager()->getRepository('File')->get();
            $jobAttachment->set('name', date('Y-m-d H:i:s') . ' (' . $partNumber . ')' . '.' . $fileExt);
            $jobAttachment->set('role', 'Attachment');
            $jobAttachment->set('relatedType', 'ImportFeed');
            $jobAttachment->set('relatedId', $importFeed->get('id'));
            $jobAttachment->set('storage', 'UploadDir');
            $jobAttachment->set('storageFilePath', $filePathBuilder->createPath(FilePathBuilder::UPLOAD));

            $fileName = $this->getEntityManager()->getRepository('Attachment')->getFilePath($jobAttachment);
            $fileParser->createFile($fileName, $part);

            $jobAttachment->set('md5', \md5_file($fileName));
            $jobAttachment->set('type', \mime_content_type($fileName));
            $jobAttachment->set('size', \filesize($fileName));
            $this->getEntityManager()->saveEntity($jobAttachment);

            $jobData = $service->prepareJobData($importFeed, $jobAttachment->get('id'));
            if (!empty($priority)) {
                $jobData['data']['priority'] = $priority;
            }
            $jobData['sheet'] = 0;
            $jobData['rowNumberPart'] = $rowNumberPart;
            $jobData['data']['importJobId'] = $importFeedService
                ->createImportJob($importFeed, $importFeed->getFeedField('entity'), $attachment->get('id'), $payload, $jobAttachment->get('id'))
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
}
