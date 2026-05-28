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
use Espo\Core\Injectable;
use Atro\Entities\File;

class Json extends Injectable implements FileParserInterface
{
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
        $data = $this->getFileData($attachment);
        if (empty($data[0])) {
            return [];
        }

        return array_keys($data[0]);
    }

    public function getFileData(File $attachment, int $offset = 0, ?int $limit = null): ?array
    {
        $contents = file_get_contents($attachment->getFilePath());

        if (empty($contents)) {
            return [];
        }

        $data = \Import\Core\Utils\JsonToVerticalArray::mutate($contents, $this->data);

        return $this->getInjection('eventManager')
            ->dispatch('ImportFileParser', 'afterGetFileData', new Event(['data' => $data, 'attachment' => $attachment, 'type' => 'json']))
            ->getArgument('data');
    }

    public function hasFileData(File $attachment): bool
    {
        $contents = file_get_contents($attachment->getFilePath());
        if (empty($contents)) {
            return true;
        }

        $data = @json_decode($contents, true);
        if (empty($data)) {
            return true;
        }

        $rootNode = $this->data['rootNode'] ?? null;
        if (!empty($rootNode)) {
            $parts = explode('.', $rootNode);
            foreach ($parts as $part) {
                $data = $data[$part] ?? [];
            }
        }

        return !empty($data);
    }

    public function createFileContent(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT);
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
