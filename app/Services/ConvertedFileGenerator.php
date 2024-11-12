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

use Atro\Core\Utils\Util;
use Atro\Services\File;
use Atro\Services\QueueManagerBase;
use Espo\Core\Exceptions\BadRequest;
use Import\Entities\ImportFeed as ImportFeedEntity;
use Import\FileParsers\FileParserInterface;

class ConvertedFileGenerator extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        $method = "generate" . ucfirst($data['type'] . "File");
        if (method_exists($this, $method)) {
            $this->$method((string)$data['importJobId']);
        }

        return true;
    }

    public function generateCreatedFile(string $jobId): ?string
    {
        return null;
    }

    public function generateUpdatedFile(string $jobId): ?string
    {
        return null;
    }

    public function generateDeletedFile(string $jobId): ?string
    {
        return null;
    }

    public function generateSkippedBySystemFile(string $jobId): ?string
    {
        return null;
    }

    public function generateSkippedByScriptFile(string $jobId): ?string
    {
        return null;
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

        return $this->getImportTypeSimpleService()->createConvertedFileForJob($jobId, $jobData);
    }

    public function generateFile(string $jobId, string $type, bool $hasReason = false): ?string
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $jobId);
        if (empty($importJob)) {
            throw new BadRequest("Import job '$jobId' does not exist.");
        }

        $errorLogs = $this->getEntityManager()->getRepository('ImportJobLog')
            ->where([
                'importJobId' => $importJob->get('id'),
                'type'        => $type
            ])
            ->order('rowNumber')
            ->find();

        if (empty($errorLogs[0])) {
            return null;
        }

        $feed = $importJob->get('importFeed');
        if (empty($feed)) {
            throw new BadRequest("ImportFeed for import job '{$importJob->get('id')}' does not exist.");
        }

        $reasonColumn = 'Reason';

        // prepare rows
        foreach ($errorLogs as $errorLog) {
            $row = $errorLog->get('row');
            if (empty($row)) {
                continue;
            }

            $row = json_decode(json_encode($row), true);

            if ($hasReason) {
                if (isset($rows[$errorLog->get('rowNumber')])) {
                    $row[$reasonColumn] = $rows[$errorLog->get('rowNumber')][$reasonColumn] . ' | ' . $errorLog->get('message');
                } else {
                    $row[$reasonColumn] = $errorLog->get('message');
                }
            }

            $rows[$errorLog->get('rowNumber')] = $row;
        }

        if (empty($rows)) {
            return null;
        }

        $errorsRows = [];
        foreach ($rows as $row) {
            // push header
            if (empty($errorsRows)) {
                $errorsRows[] = array_keys($row);
            }
            $errorsRows[] = array_values($row);
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
            ->createFileViaContents($inputData, $fileParser->createFileContent($errorsRows));

        $entity = $this->getEntityManager()->getEntity('ImportJobFile');
        $entity->set([
            'importJobId' => $importJob->get('id'),
            'fileId'      => $fileArr['id']
        ]);
        $this->getEntityManager()->saveEntity($entity);

        return $fileArr['id'];
    }

    protected function getImportTypeSimpleService(): ImportTypeSimple
    {
        return $this->getContainer()->get('serviceFactory')->create('ImportTypeSimple');
    }

    protected function createFileParser(string $format): FileParserInterface
    {
        return $this->getInjection('container')->get(ImportFeedEntity::getFileParserClass($format));
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
