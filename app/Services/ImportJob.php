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

use Atro\Core\Exceptions\NotFound;
use Atro\DTO\QueueItemDTO;
use Atro\Services\File;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;
use Import\FileParsers\FileParserInterface;
use Import\Entities\ImportFeed as ImportFeedEntity;

class ImportJob extends Base
{
    private ?ImportTypeSimple $importService = null;
    protected $mandatorySelectAttributeList = ['message', 'uploadedFileId', 'uploadedFileName', 'attachmentId', 'attachmentName'];

    public function deleteOld(int $days = 14): bool
    {
        if ($days === 0) {
            return true;
        }

        // delete
        while (true) {
            $toDelete = $this->getEntityManager()->getRepository('ImportJob')
                ->where(['modifiedAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s')])
                ->limit(0, 2000)
                ->order('modifiedAt')
                ->find();
            if (empty($toDelete[0])) {
                break;
            }

            foreach ($toDelete as $entity) {
                $this->getEntityManager()->removeEntity($entity);
            }
        }

        // delete queue items
        while (true) {
            $toDeleteItem = $this->getEntityManager()->getRepository('QueueItem')
                ->where([
                    'modifiedAt<' => (new \DateTime())->modify("-$days days")->format('Y-m-d H:i:s'),
                    'serviceName' => ['ImportJobCreator', 'ImportTypeSimple'],
                    'status'      => ['Success', 'Failed', 'Canceled']
                ])
                ->limit(0, 2000)
                ->order('modifiedAt')
                ->find();
            if (empty($toDeleteItem[0])) {
                break;
            }

            foreach ($toDeleteItem as $entity) {
                $this->getEntityManager()->removeEntity($entity);
            }
        }

        // delete forever
        $daysToDeleteForever = $this->getConfig()->get('importJobsDeletedMaxDays', 14);
        $maxDate = (new \DateTime())->modify("-$daysToDeleteForever days")->format('Y-m-d H:i:s');
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->delete('import_job')
            ->where('modified_at <= :maxDate')
            ->andWhere('deleted = :true')
            ->setParameter('maxDate', $maxDate)
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeStatement();

        // delete forever logs
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->delete('import_job_log')
            ->where('deleted = :deleted')
            ->andWhere('modified_at <= :maxDate')
            ->setParameter('deleted', true, ParameterType::BOOLEAN)
            ->setParameter('maxDate', $maxDate)
            ->executeStatement();

        return true;
    }

    public function generateErrorsAttachment(string $jobId): array
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $jobId);
        if (empty($importJob)) {
            throw new BadRequest("Import job '$jobId' does not exist.");
        }

        /** @var \Import\Repositories\ImportJobLog $importJobLogRepo */
        $importJobLogRepo = $this->getEntityManager()->getRepository('ImportJobLog');

        $errorLogs = $importJobLogRepo
            ->where([
                'importJobId' => $importJob->get('id'),
                'type'        => 'error'
            ])
            ->find();

        if (empty($errorLogs[0])) {
            throw new BadRequest($this->translate('errorFileCreatingFailed', 'exceptions', 'ImportJob'));
        }

        if (empty($feed = $importJob->get('importFeed'))) {
            throw new BadRequest("ImportFeed for import job '{$importJob->get('id')}' does not exist.");
        }

        $errorsRowsNumbers = [];

        switch ($feed->getFeedField('format')) {
            case 'CSV':
            case 'Excel':
                $isFileHeaderRow = !empty($feed->getFeedField('isFileHeaderRow'));
                $attachmentId = $importJob->get('attachmentId');
                $delimiter = $feed->getDelimiter();
                $enclosure = $feed->getEnclosure();
                $format = $feed->getFeedField('format');
                break;
            default:
                $isFileHeaderRow = true;
                $attachmentId = $importJob->get('convertedFileId');
                if (empty($attachmentId)) {
                    throw new BadRequest($this->translate('convertedFileNotExist', 'exceptions', 'ImportJob'));
                }
                $delimiter = ",";
                $enclosure = '"';
                $format = 'CSV';
        }

        // add header row if it needs
        if ($isFileHeaderRow) {
            $errorsRowsNumbers[1] = 'Import Errors';
        }

        foreach ($errorLogs as $log) {
            $importJobLogRepo->prepareMessage($log);
            $rowNumber = (int)$log->get('rowNumber');
            $errorsRowsNumbers[$rowNumber] = $log->get('message');
        }

        if (empty($attachmentId) || empty($attachment = $this->getEntityManager()->getEntity('File', $attachmentId))) {
            throw new BadRequest("Attachment '$attachmentId' does not exist.");
        }

        $fileParser = $this->createFileParser($format);
        $fileParser->setData([
            'delimiter' => $delimiter,
            'enclosure' => $enclosure
        ]);

        $data = $fileParser->getFileData($attachment);

        // collect errors rows
        $errorsRows = [];
        foreach ($data as $k => $row) {
            $key = $k + 1;
            if (isset($errorsRowsNumbers[$key])) {
                $row[] = $errorsRowsNumbers[$key];
                $errorsRows[] = $row;
            }
        }

        // prepare attachment name
        $nameParts = explode('.', $importJob->get('attachment')->get('name'));
        array_pop($nameParts);
        $name = 'errors-' . implode('.', $nameParts);

        $inputData = new \stdClass();
        $inputData->hidden = true;
        $inputData->folderId = $this->getImportFeedService()->createImportFileFolder($feed)->get('id');
        switch ($format) {
            case 'CSV':
                $inputData->name = "{$name}.csv";
                break;
            case 'Excel':
                $inputData->name = "{$name}.xlsx";
                break;
            default:
                throw new \Error('Unknown file format');
        }

        $fileParser->setData(['isFileHeaderRow' => $isFileHeaderRow]);
        $fileArr = $this->getFileService()->createFileViaContents($inputData, $fileParser->createFileContent($errorsRows));

        $importJob->set('errorsAttachmentId', $fileArr['id']);
        $this->getEntityManager()->saveEntity($importJob);

        return $fileArr;
    }

    public function generateConvertedFile(string $jobId): array
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $jobId);
        if (empty($importJob)) {
            throw new BadRequest("ImportJob '$jobId' does not exist.");
        }

        $qmJob = $this->getEntityManager()->getRepository('ImportJob')->getQmJob($importJob);
        if (empty($qmJob)) {
            throw new BadRequest("QueueItem for ImportJob '{$importJob->get('id')}' does not exist.");
        }

        // prepare job data
        $jobData = json_decode(json_encode($qmJob->get('data')), true);
        $this->getImportTypeSimpleService()->prepareConfigurator($jobData);

        $idFields = $this->getImportTypeSimpleService()->getLinkFields($jobData['data']['entity'], $jobData['data']['idField']);

        $rows = [];
        while (!empty($inputData = $this->getImportTypeSimpleService()->getInputData($jobData))) {
            $this->getMemoryStorage()->set('importRowsPart', $inputData);

            // add column converted_{field} to the row
            foreach ($inputData as $i => $row) {
                $this->getImportTypeSimpleService()->processConvertedFileRow($jobData, $row, $idFields);
                if ($row === null) {
                    unset($inputData[$i]);
                    continue;
                }
                $inputData[$i] = $row;
            }

            $rows = array_merge($rows, $inputData);
        }

        if (empty($rows)) {
            return [];
        }

        if ($jobData['isFileHeaderRow'] ?? false) {
            $rows = array_merge([array_keys($rows[0])], $rows);
        } else {
            $rows[0] = array_keys($rows[0]);
        }

        $inputData = new \stdClass();
        $inputData->name = str_replace(' ', '_', $importJob->get('name')) . '.csv';
        $inputData->hidden = true;
        $inputData->folderId = $this->getImportFeedService()->createImportFileFolder($importJob->get('importFeed'))->get('id');
        $fileParser = $this->createFileParser('CSV');
        $fileParser->setData($jobData);
        $fileArr = $this->getFileService()->createFileViaContents($inputData, $fileParser->createFileContent($rows));

        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $importJob->set('convertedFileId', is_array($fileArr) ? $fileArr['id'] : $fileArr->get('id'));
            $this->getEntityManager()->saveEntity($importJob);
        }

        return is_array($fileArr) ? $fileArr : $fileArr->toArray();
    }

    public function getImportJobsViaScope(string $scope): array
    {
        return $this
            ->getEntityManager()
            ->getRepository('ImportJob')
            ->getImportJobsViaScope($scope);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $this->prepareCounts($collection);
    }

    protected function createFileParser(string $format): FileParserInterface
    {
        return $this->getInjection('container')->get(ImportFeedEntity::getFileParserClass($format));
    }

    protected function getImportTypeSimpleService(): ImportTypeSimple
    {
        if (!$this->importService) {
            $this->importService = $this->getServiceFactory()->create('ImportTypeSimple');
        }

        return $this->importService;
    }

    protected function getImportFeedService(): ImportFeed
    {
        return $this->getServiceFactory()->create('ImportFeed');
    }

    protected function getFileService(): File
    {
        return $this->getServiceFactory()->create('File');
    }

    public function prepareCounts(EntityCollection $collection): void
    {
        $data = $this->getRepository()->getJobsCounts(array_column($collection->toArray(), 'id'));

        foreach ($collection as $entity) {
            $entity->set('createdCount', $data[$entity->get('id')]['created_count'] ?? 0);
            $entity->set('updatedCount', $data[$entity->get('id')]['updated_count'] ?? 0);
            $entity->set('deletedCount', $data[$entity->get('id')]['deleted_count'] ?? 0);
            $entity->set('skippedCount', $data[$entity->get('id')]['skipped_count'] ?? 0);
            $entity->set('errorsCount', $data[$entity->get('id')]['errors_count'] ?? 0);
        }
    }

    public function readEntity($id)
    {
        $entity = parent::readEntity($id);

        if (!empty($entity)) {
            $children = $entity->get('children');
            $this->prepareCounts(new EntityCollection([$entity], $entity->getEntityType()));
            $entity->set('hasConvertedFile', empty($children[0]));
        }

        return $entity;
    }

    public function reCreateImportJob(string $id, ?string $attachmentId = null): bool
    {
        $job = $this->getEntity($id);
        if (empty($job)) {
            throw new NotFound();
        }

        if ($job->get('state') !== 'Success') {
            throw new \Atro\Core\Exceptions\BadRequest("Status must be Success");
        }

        $importService = $this->getServiceFactory()->create('ImportFeed');

        $feed = $this->getEntityManager()->getEntity('ImportFeed', $job->get('importFeedId'));
        // if job is pav or child job
        if (!empty($job->get('parentId'))) {
            $queueItem = $job->get('queueItem');
            if (!empty($queueItem)) {
                $importJob = $this->getRepository()->get();
                $importJob->set('id', Util::generateId());
                $importJob->set('status', 'Pending');
                $importJob->set('name', $job->get('name'));
                $importJob->set('entityName', $job->get('entityName'));
                $importJob->set('importFeedId', $job->get('importFeedId'));
                $importJob->set('parentId', $job->get('parentId'));
                $importJob->set('attachmentId', !empty($attachmentId) ? $attachmentId : $job->get('attachmentId'));
                $this->getEntityManager()->saveEntity($importJob);

                $data = $queueItem->get('data');
                $data->data->importJobId = $importJob->get('id');
                $data->attachmentId = $importJob->get('attachmentId');
                $importService->push(new QueueItemDTO($queueItem->get('name'), $queueItem->get('serviceName'), json_decode(json_encode($data), true)));
            }
            return true;
        }

        return $importService->runImport($job->get('importFeedId'), !empty($attachmentId) ? $attachmentId : $job->get('attachmentId'));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function translate(string $key, string $label, string $scope): string
    {
        return $this->getInjection('container')->get('language')->translate($key, $label, $scope);
    }
}
