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

use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\FilePathBuilder;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use PhpOffice\PhpSpreadsheet\IOFactory as PhpSpreadsheet;
use Import\Entities\ImportFeed as ImportFeedEntity;

class ImportJob extends Base
{
    protected $mandatorySelectAttributeList = ['message', 'uploadedFileId', 'uploadedFileName', 'attachmentId', 'attachmentName'];

    public function deleteOld(int $days): bool
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
        $daysToDeleteForever = $days + 14;
        $maxDate = (new \DateTime())->modify("-$daysToDeleteForever days");
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->delete('import_job')
            ->where('modified_at < :maxDate')
            ->andWhere('deleted = :true')
            ->setParameter('maxDate', $maxDate->format('Y-m-d H:i:s'))
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->executeStatement();

        // delete forever logs
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $qb
            ->delete('import_job_log')
            ->where('deleted = :deleted')
            ->andWhere('modified_at < :maxDate')
            ->setParameter('deleted', true, ParameterType::BOOLEAN)
            ->setParameter('maxDate', $maxDate->format('Y-m-d H:i:s'))
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

        if (empty($attachmentId) || empty($attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId))) {
            throw new BadRequest("Attachment '$attachmentId' does not exist.");
        }

        /** @var \Import\FileParsers\FileParserInterface $fileParser */
        $fileParser = $this->getInjection('container')->get(ImportFeedEntity::getFileParserClass($format));
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

        /** @var \Espo\Services\Attachment $attachmentService */
        $attachmentService = $this->getInjection('serviceFactory')->create('Attachment');

        // prepare attachment name
        $nameParts = explode('.', $importJob->get('attachment')->get('name'));
        array_pop($nameParts);
        $name = 'errors-' . implode('.', $nameParts);

        $inputData = new \stdClass();
        $inputData->name = "{$name}.csv";
        $inputData->contents = \Import\Core\Utils\Util::generateCsvContents($errorsRows, $feed->getDelimiter(), $feed->getEnclosure());
        $inputData->type = 'text/csv';
        $inputData->relatedType = 'ImportJob';
        $inputData->field = 'errorsAttachment';
        $inputData->role = 'Attachment';

        $attachment = $attachmentService->createEntity($inputData);

        // create xlsx
        if ($format === 'Excel') {
            $filePath = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);
            $cacheDir = 'data/cache';

            Util::createDir($cacheDir);
            $cacheFile = "{$cacheDir}/{$name}.xlsx";

            $reader = PhpSpreadsheet::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setDelimiter($feed->getDelimiter());
            $reader->setEnclosure($feed->getEnclosure());
            $writer = PhpSpreadsheet::createWriter($reader->load($filePath), "Xlsx");
            $writer->save($cacheFile);

            $inputData->name = "{$name}.xlsx";
            $inputData->type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $inputData->contents = file_get_contents($cacheFile);

            // remove csv
            $this->getEntityManager()->removeEntity($attachment);

            // remove cache file
            unlink($cacheFile);

            $attachment = $attachmentService->createEntity($inputData);
        }

        $importJob->set('errorsAttachmentId', $attachment->get('id'));
        $this->getEntityManager()->saveEntity($importJob);

        return $attachment->toArray();
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

        /** @var \Import\Services\ImportTypeSimple $importService */
        $importService = $this->getServiceFactory()->create('ImportTypeSimple');

        /** @var \Espo\Repositories\Attachment $attachmentRepository */
        $attachmentRepository = $this->getEntityManager()->getRepository('Attachment');

        // prepare converted file attachment
        $convertedFileAttachment = $attachmentRepository->get();
        $convertedFileAttachment->set([
            'name'            => str_replace(' ', '_', $importJob->get('name')) . '.csv',
            'role'            => 'Attachment',
            'field'           => 'convertedFile',
            'relatedType'     => 'ImportJob',
            'relatedId'       => $importJob->get('id'),
            'storage'         => 'UploadDir',
            'type'            => 'text/csv',
            'storageFilePath' => $this->getInjection('container')->get('filePathBuilder')->createPath(FilePathBuilder::UPLOAD),
        ]);

        // create dir for converted file
        $convertedFileDirPath = trim($this->getConfig()->get('filesPath', 'upload/files'), '/') . '/' . $convertedFileAttachment->get('storageFilePath');
        while (!file_exists($convertedFileDirPath)) {
            mkdir($convertedFileDirPath, 0777, true);
            usleep(100);
        }

        // create converted file
        $convertedFile = fopen($convertedFileDirPath . '/' . $convertedFileAttachment->get('name'), 'w');

        while (!empty($inputData = $importService->getInputData($jobData))) {
            while (!empty($inputData)) {
                $row = array_shift($inputData);

                // push header to converted file
                if (empty($convertedFileHeaderPushed)) {
                    fputcsv($convertedFile, array_keys($row));
                    $convertedFileHeaderPushed = true;
                }

                // push row to converted file
                fputcsv($convertedFile, array_values($row));
            }
        }

        // save converted file attachment
        fclose($convertedFile);
        $convertedFileAttachment->set('size', \filesize($attachmentRepository->getFilePath($convertedFileAttachment)));
        $this->getEntityManager()->saveEntity($convertedFileAttachment);

        // set converted file attachment to import job
        $importJob->set('convertedFileId', $convertedFileAttachment->get('id'));
        $this->getEntityManager()->saveEntity($importJob);

        return $convertedFileAttachment->toArray();
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
