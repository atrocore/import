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

namespace Import\FileParsers;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Util;
use Espo\Core\Injectable;
use Atro\Entities\File;

class Csv extends Injectable implements FileParserInterface
{
    const UTF8_BOM = "\xEF\xBB\xBF";
    const UTF8_BOM_LEN = 3;

    protected array $data = [];

    public function __construct()
    {
        $this->addDependency('eventManager');
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getFileColumns(File $attachment): array
    {
        $isFileHeaderRow = $this->data['isFileHeaderRow'] ?? true;
        $data = $this->data['fileData'] ?? null;

        // prepare result
        $result = [];

        // get data
        if ($data === null) {
            $data = $this->getFileData($attachment, 0, 2);
        }

        if (isset($data[0])) {
            if ($isFileHeaderRow && isset($data[1])) {
                foreach ($data[0] as $k => $value) {
                    $value = trim((string)$value);
                    if (empty($value) && $value !== '0' && $value !== 0) {
                        $value = self::createColumnName($k, $data);
                    }
                    $result[] = $this->removeNonPrintableCharacters($value);
                }
            } else {
                foreach ($data[0] as $k => $value) {
                    $result[] = $this->removeNonPrintableCharacters(self::createColumnName($k, $data));
                }
            }
        }

        return $result;
    }

    public function getFileData(File $attachment, int $offset = 0, ?int $limit = null): ?array
    {
        $delimiter = $this->data['delimiter'] ?? ';';
        $enclosure = $this->data['enclosure'] ?? '"';

        $path = $this->getLocalFilePath($attachment);

        if (!file_exists($path)) {
            throw new BadRequest("File '$path' does not exist.");
        }

        if ($delimiter === '\t') {
            $delimiter = "\t";
        }

        $data = [];

        $file = fopen($path, 'r');

        $this->skipBOM($file);

        $row = 0;
        while (($rowData = fgetcsv($file, 0, $delimiter, $enclosure)) !== false && (is_null($limit) || count($data) < $limit)) {
            if ($row >= $offset) {
                $data[] = $rowData;
            }
            $row++;
        }
        fclose($file);

        if (empty($data)) {
            return null;
        }

        return $this->getInjection('eventManager')
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'csv']))
            ->getArgument('data');
    }

    public function hasFileData(File $attachment): bool
    {
        $delimiter = $this->data['delimiter'] ?? ';';
        $enclosure = $this->data['enclosure'] ?? '"';
        $isFileHeaderRow = $this->data['isFileHeaderRow'] ?? true;

        if ($delimiter === '\t') {
            $delimiter = "\t";
        }

        $path = $this->getLocalFilePath($attachment);
        if (!file_exists($path)) {
            return true;
        }

        $file = fopen($path, 'r');
        $this->skipBOM($file);

        if ($isFileHeaderRow) {
            fgetcsv($file, 0, $delimiter, $enclosure);
        }

        $row = fgetcsv($file, 0, $delimiter, $enclosure);
        fclose($file);

        return $row !== false;
    }

    public function createFileContent(array $data): string
    {
        $delimiter = $this->data['delimiter'] ?? ';';
        $enclosure = $this->data['enclosure'] ?? '"';
        $hasHeader = $this->data['isFileHeaderRow'] ?? false;

        if ($delimiter == '\t') {
            $delimiter = "\t";
        }

        $tmpFilePath = tempnam(sys_get_temp_dir(), 'csv_');

        $fp = fopen($tmpFilePath, 'w');
        if ($hasHeader === false) {
            fputcsv($fp, array_keys($data[0]), $delimiter, $enclosure);
        }

        foreach ($data as $fields) {
            fputcsv($fp, $fields, $delimiter, $enclosure);
        }
        fclose($fp);

        // convert to utf-8
        $this->convertToUTF8($tmpFilePath);

        $fileContent = file_get_contents($tmpFilePath);

        unlink($tmpFilePath);

        return $fileContent;
    }

    public function convertAttachmentToUTF8(File $attachment): void
    {
        $this->convertToUTF8($this->getLocalFilePath($attachment));
    }

    public function convertToUTF8(string $filename): void
    {
        $file = fopen($filename, 'r');

        $tempFilename = tempnam(sys_get_temp_dir(), 'utf8tmp');
        $tempFile = fopen($tempFilename, 'w');

        while (($line = fgets($file)) !== false) {
            $originalEncoding = mb_detect_encoding($line, mb_detect_order(), true);

            if ($originalEncoding === false || $originalEncoding === 'UTF-8') {
                fclose($file);
                fclose($tempFile);
                @unlink($tempFilename);

                return;
            }

            $utf8Line = mb_convert_encoding($line, 'UTF-8', $originalEncoding);

            fwrite($tempFile, $utf8Line);
        }

        fclose($file);
        fclose($tempFile);

        rename($tempFilename, $filename);
    }

    protected function removeNonPrintableCharacters(string $value): string
    {
        return preg_replace('/[\x00-\x1f]/', '', $value);
    }

    protected static function createColumnName(int $k, array $data): string
    {
        return (string)($k + 1);
    }

    public function getLocalFilePath(File $attachment): string
    {
        if ($attachment->getStorage()->get('type') === 'local') {
            return $attachment->getFilePath();
        }

        $tmpFileName = \Import\Services\ImportFeed::TMP_DIR."/{$attachment->id}/{$attachment->get('name')}";

        if (!file_exists($tmpFileName)) {
            Util::createDir(dirname($tmpFileName));
            file_put_contents($tmpFileName, $attachment->getContents());
        }

        return $tmpFileName;
    }

    /**
     * Move file pointer past any BOM marker.
     */
    protected function skipBOM($fileHandle): void
    {
        rewind($fileHandle);

        if (fgets($fileHandle, self::UTF8_BOM_LEN + 1) !== self::UTF8_BOM) {
            rewind($fileHandle);
        }
    }

    protected function createDir(string $fileName): void
    {
        $parts = explode('/', $fileName);
        array_pop($parts);
        $dir = implode('/', $parts);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            sleep(1);
        }
    }
}
