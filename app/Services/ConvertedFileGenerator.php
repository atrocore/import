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
            $jobData['delimiter'] = ",";
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
            $attachmentId = $this->generateConvertedFile($jobId);
        }

        if (!empty($attachmentId)) {
            $attachment = $this->getEntityManager()->getRepository('File')->get($attachmentId);
            if (empty($attachment)) {
                throw new BadRequest("Attachment '$attachmentId' does not exist.");
            }
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
        $fileArr = $this->getFileService()->createFileViaContents($inputData,
            $fileParser->createFileContent($errorsRows));

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
