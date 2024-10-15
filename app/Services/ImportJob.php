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
    protected $mandatorySelectAttributeList = [
        'message',
        'attachmentId',
        'attachmentName',
        'convertedFileId',
        'convertedFileName'
    ];

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

    public function generateConvertedFile(string $jobId): array
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $jobId);
        if (empty($importJob)) {
            throw new BadRequest("Import job '$jobId' does not exist.");
        }

        $jobData = $this->getImportTypeSimpleService()
            ->prepareJobData($importJob->get('importFeed'), $importJob->get('attachmentId'));

        $id = $this->getImportTypeSimpleService()
            ->createConvertedFile($jobId, $jobData);

        $file = $this->getEntityManager()->getRepository('File')->get($id);

        return $file->toArray();
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

        $feed = $importJob->get('importFeed');
        if (empty($feed)) {
            throw new BadRequest("ImportFeed for import job '{$importJob->get('id')}' does not exist.");
        }

        $errorsRowsNumbers = [];

        $attachmentId = $importJob->get('convertedFileId');
        if (empty($attachmentId)) {
            $attachmentId = $this->generateConvertedFile($jobId)['id'];
        }

        $attachment = $this->getEntityManager()->getEntity('File', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest("Attachment '$attachmentId' does not exist.");
        }

        // add header row if it needs
        $errorsRowsNumbers[1] = 'Import Errors';

        foreach ($errorLogs as $log) {
            $importJobLogRepo->prepareMessage($log);
            $rowNumber = (int)$log->get('rowNumber');
            $errorsRowsNumbers[$rowNumber] = $log->get('message');
        }

        $fileParser = $this->createFileParser('CSV');
        $fileParser->setData([
            'delimiter' => ",",
            'enclosure' => '"'
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
        $nameParts = explode('.', $attachment->get('name'));
        array_pop($nameParts);
        $name = 'errors-' . implode('.', $nameParts);

        $inputData = new \stdClass();
        $inputData->hidden = true;
        $inputData->folderId = $this->getImportFeedService()->createImportFileFolder($feed)->get('id');
        $inputData->name = "{$name}.csv";

        $fileParser->setData(['isFileHeaderRow' => true]);
        $fileArr = $this->getFileService()->createFileViaContents($inputData, $fileParser->createFileContent($errorsRows));

        $importJob->set('errorsAttachmentId', $fileArr['id']);
        $this->getEntityManager()->saveEntity($importJob);

        return $fileArr;
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
            $this->prepareCounts(new EntityCollection([$entity], $entity->getEntityType()));
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
