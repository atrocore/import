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
use Atro\Entities\File;

class Xml extends Json
{
    public function getFileData(File $attachment, int $offset = 0, ?int $limit = null): array
    {
        $contents = file_get_contents($attachment->getFilePath());

        if (empty($contents)) {
            return [];
        }

        $json = json_encode(simplexml_load_string($contents));

        $data = \Import\Core\Utils\JsonToVerticalArray::mutate($json, $this->data);

        return $this->getInjection('eventManager')
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'xml']))
            ->getArgument('data');
    }

    public function createFile(string $fileName, array $data): void
    {
    }
}
