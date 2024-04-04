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

use Atro\Entities\File;

interface FileParserInterface
{
    public function setData(array $data): void;

    public function getFileColumns(File $attachment): array;

    public function getFileData(File $attachment, int $offset = 0, ?int $limit = null): array;

    public function createFile(string $fileName, array $data): void;
}
