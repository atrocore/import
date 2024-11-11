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

use Atro\Services\File;
use Atro\Services\QueueManagerBase;
use Espo\Core\Exceptions\BadRequest;
use Import\Entities\ImportFeed as ImportFeedEntity;
use Import\FileParsers\FileParserInterface;

class ConvertedFileGenerator extends QueueManagerBase
{
    public function run(array $data = []): bool
    {
        if ($data['field'] === 'convertedFile') {
            $this->generateConvertedFile((string)$data['importJobId']);
        } elseif ($data['field'] === 'errorsAttachment') {
            $this->generateErrorsAttachment((string)$data['importJobId']);
        }

        return true;
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

    public function generateErrorsAttachment(string $jobId): ?string
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
            ->order('rowNumber')
            ->find();

        if (empty($errorLogs[0])) {
            throw new BadRequest($this->translate('errorFileCreatingFailed', 'exceptions', 'ImportJob'));
        }

        $feed = $importJob->get('importFeed');
        if (empty($feed)) {
            throw new BadRequest("ImportFeed for import job '{$importJob->get('id')}' does not exist.");
        }

        $errorColumn = 'Import Errors';

        foreach ($errorLogs as $errorLog) {
            $row = $errorLog->get('row');
            if (empty($row)) {
                continue;
            }

            $row = json_decode(json_encode($row), true);
            if (isset($rows[$errorLog->get('rowNumber')])) {
                $row[$errorColumn] = $rows[$errorLog->get('rowNumber')][$errorColumn] . ' | ' . $errorLog->get('message');
            } else {
                $row[$errorColumn] = $errorLog->get('message');
            }

            // push header
            if (empty($errorsRows)) {
                $errorsRows[] = array_keys($row);
            }
            $rows[$errorLog->get('rowNumber')] = $row;
        }

        if (empty($rows)) {
            throw new BadRequest($this->translate('errorFileCreatingFailed', 'exceptions', 'ImportJob'));
        }

        foreach ($rows as $row) {
            $errorsRows[] = array_values($row);
        }

        $fileParser = $this->createFileParser('CSV');
        $fileParser->setData([
            'delimiter' => ";",
            'enclosure' => '"'
        ]);

        $inputData = new \stdClass();
        $inputData->hidden = true;
        $inputData->folderId = $this->getImportFeedService()->createImportFileFolder($feed)->get('id');
        $inputData->name = 'errors-' . $feed->get('name') . '.csv';

        $fileParser->setData(['isFileHeaderRow' => true]);
        $fileArr = $this->getFileService()
            ->createFileViaContents($inputData, $fileParser->createFileContent($errorsRows));

        $importJob->set('errorsAttachmentId', $fileArr['id']);
        $this->getEntityManager()->saveEntity($importJob);

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
