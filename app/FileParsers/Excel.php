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
use Atro\Core\Utils\Util;
use Espo\Core\Exceptions\BadRequest;
use Atro\Entities\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Excel extends Csv
{
    public function getFileSheetsNames(File $attachment)
    {
        $path = $this->getLocalFilePath($attachment);

        $reader = new Xlsx();
        $reader->setReadDataOnly(true);

        try {
            $data = $reader->listWorksheetNames($path);
        } catch (\Throwable $e) {
            $data = [];
        }

        Util::removeDir(dirname($path));

        return $data;
    }

    public function getFileData(File $attachment, int $offset = 0, ?int $limit = null): ?array
    {
        $sheet = $this->data['sheet'] ?? 0;

        $path = $this->getLocalFilePath($attachment);

        if (!file_exists($path)) {
            throw new BadRequest("File '$path' does not exist.");
        }

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $rowNumber = 0;

        $data = [];

        $worksheet = $reader->load($path)->getSheet($sheet);
        foreach ($worksheet->getRowIterator() as $row) {
            $dataRow = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            foreach ($cellIterator as $cell) {
                $dataRow[] = $cell->getValue();
            }

            if ($limit !== null && count($data) >= $limit) {
                break;
            }

            if ($offset === null || $rowNumber >= $offset) {
                $skip = true;
                foreach ($dataRow as $v) {
                    if ($v !== null) {
                        $skip = false;
                        break;
                    }
                }
                if (!$skip) {
                    $data[] = $dataRow;
                }
            }
            $rowNumber++;
        }

        Util::removeDir(dirname($path));

        if (empty($data)) {
            return null;
        }

        return $this->getInjection('eventManager')
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'excel']))
            ->getArgument('data');
    }

    public function createFileContent(array $data): string
    {
        $tmpFilePath = tempnam(sys_get_temp_dir(), 'excel_');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $row = 1;
        foreach ($data as $rowData) {
            $column = 1;
            foreach ($rowData as $cellData) {
                $sheet->setCellValueByColumnAndRow($column, $row, $cellData);
                $column++;
            }
            $row++;
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tmpFilePath);

        $fileContent = file_get_contents($tmpFilePath);

        unlink($tmpFilePath);

        return $fileContent;
    }

    public function convertToUTF8(string $filename): void
    {
    }
}
