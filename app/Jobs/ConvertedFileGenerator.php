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

use Atro\Core\Utils\Util;
use Atro\Entities\Job;
use Atro\Jobs\AbstractJob;
use Atro\Jobs\JobInterface;
use Atro\Services\File;
use Atro\Core\Exceptions\BadRequest;
use Import\Entities\ImportFeed as ImportFeedEntity;
use Import\FileParsers\FileParserInterface;
use Import\Services\ImportFeed;

class ConvertedFileGenerator extends AbstractJob implements JobInterface
{
    protected Job $currentJob;

    public function run(Job $job): void
    {
        $this->currentJob = $job;

        $data = $job->getPayload();

        $method = "generate" . ucfirst($data['type'] . "File");
        if (method_exists($this, $method)) {
            $this->$method((string)$data['importJobId']);
        }
    }

    public function generateCreatedFile(string $jobId): ?string
    {
        return $this->generateFile($jobId, 'create');
    }

    public function generateUpdatedFile(string $jobId): ?string
    {
        return $this->generateFile($jobId, 'update');
    }

    public function generateDeletedFile(string $jobId): ?string
    {
        return $this->generateFile($jobId, 'delete');
    }

    public function generateSkippedBySystemFile(string $jobId): ?string
    {
        return $this->generateFile($jobId, 'skippedBySystem');
    }

    public function generateSkippedByScriptFile(string $jobId): ?string
    {
        return $this->generateFile($jobId, 'skippedByScript', true);
    }

    public function generateErrorsFile(string $jobId): ?string
    {
        return $this->generateFile($jobId, 'error', true);
    }

    public function generateConvertedFile(string $jobId): ?string
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $jobId);
        if (empty($importJob)) {
            throw new BadRequest("Import job '$jobId' does not exist.");
        }

        $importFeed = $this->getEntityManager()->getEntity('ImportFeed', $importJob->get('importFeedId'));
        if (empty($importFeed)) {
            throw new BadRequest("ImportFeed '{$importJob->get('importFeedId')}' does not exist.");
        }

        $jobData = $this->getImportTypeSimpleService()
            ->prepareJobData($importFeed, $importJob->get('attachmentId'));

        // for import type HttpRequest
        if (!empty($importFeed->getFeedField('mergeResponses'))) {
            $jobData['fileFormat'] = 'CSV';
            $jobData['delimiter'] = ";";
            $jobData['enclosure'] = '"';
        }

        $fileId = $this->getImportTypeSimpleService()->createConvertedFileForJob($jobId, $jobData);

        $file = $this->getEntityManager()->getEntity('File', $fileId);

        $this->currentJob->get('payload')->fileName = $file->get('name');
        $this->currentJob->get('payload')->downloadUrl = $file->getDownloadUrl();
        $this->getEntityManager()->saveEntity($this->currentJob);

        return $fileId;
    }

    public function generateFile(string $jobId, string $type, bool $hasReason = false): ?string
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $jobId);
        if (empty($importJob)) {
            throw new BadRequest("Import job '$jobId' does not exist.");
        }

        $feed = $importJob->get('importFeed');
        if (empty($feed)) {
            throw new BadRequest("ImportFeed for import job '{$importJob->get('id')}' does not exist.");
        }

        $logs = $this->getEntityManager()->getRepository('ImportJobLog')
            ->where([
                'importJobId'     => $importJob->get('id'),
                'type'            => in_array($type, ['skippedBySystem', 'skippedByScript']) ? 'skip' : $type,
                'skippedByScript' => $type === 'skippedByScript'
            ])
            ->order('rowNumber')
            ->find();

        $reasonColumn = 'Reason';

        // prepare rows
        foreach ($logs as $log) {
            $row = $log->get('row');
            if (empty($row)) {
                continue;
            }

            $row = json_decode(json_encode($row), true);

            if ($hasReason) {
                if (isset($rows[$log->get('rowNumber')])) {
                    $row[$reasonColumn] = $rows[$log->get('rowNumber')][$reasonColumn] . ' | ' . $log->get('message');
                } else {
                    $row[$reasonColumn] = $log->get('message');
                }
            }

            $rows[$log->get('rowNumber')] = $row;
        }

        $preparedRows = [];
        foreach ($rows as $row) {
            // push header
            if (empty($preparedRows)) {
                $preparedRows[] = array_keys($row);
            }
            $preparedRows[] = array_values($row);
        }

        $fileParser = $this->createFileParser('CSV');
        $fileParser->setData([
            'delimiter' => ";",
            'enclosure' => '"'
        ]);

        $inputData = new \stdClass();
        $inputData->hidden = false;
        $inputData->folderId = $this->getImportFeedService()->createImportFileFolder($feed)->get('id');
        $inputData->name = str_replace('_', '-', Util::toUnderScore($type))
            . '-'
            . str_replace(' ', '-', strtolower($feed->get('name'))) . '.csv';

        $fileParser->setData(['isFileHeaderRow' => true]);
        $fileArr = $this->getFileService()
            ->createFileViaContents($inputData, $fileParser->createFileContent($preparedRows));

        $entity = $this->getEntityManager()->getEntity('ImportJobFile');
        $entity->set([
            'importJobId' => $importJob->get('id'),
            'fileId'      => $fileArr['id']
        ]);
        $this->getEntityManager()->saveEntity($entity);

        $this->currentJob->get('payload')->fileName = $fileArr['name'];
        $this->currentJob->get('payload')->downloadUrl = $fileArr['downloadUrl'];
        $this->getEntityManager()->saveEntity($this->currentJob);

        return $fileArr['id'];
    }

    protected function getImportTypeSimpleService(): ImportTypeSimple
    {
        return $this->getContainer()->get(ImportTypeSimple::class);
    }

    protected function createFileParser(string $format): FileParserInterface
    {
        return $this->getContainer()->get(ImportFeedEntity::getFileParserClass($format));
    }

    protected function getImportFeedService(): ImportFeed
    {
        return $this->getContainer()->get('serviceFactory')->create('ImportFeed');
    }

    protected function getFileService(): File
    {
        return $this->getContainer()->get('serviceFactory')->create('File');
    }
}
