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

namespace Import\ProcessingTypes;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\Container;
use Atro\Core\Utils\Language;
use Atro\Entities\Job;
use Atro\Services\Record;
use Espo\ORM\EntityManager;
use Import\Entities\ImportFeed;
use Import\FileParsers\FileParserInterface;

abstract class AbstractProcessingType
{
    protected Container $container;

    abstract public static function getTypeLabel(): ?string;

    abstract public static function getDescription(): ?string;

    abstract public static function getEntityName(): string;

    abstract public function runNow(array $data, ?Job $job = null): void;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getInputData(array &$data): array
    {
        $fileData = [];

        if (!empty($data['lastIteration'])) {
            return $fileData;
        }

        $attachment = $this->getEntityManager()->getEntity('File', $data['attachmentId']);

        $fileParser = $this->getFileParser($data['fileFormat']);
        $fileParser->setData($data);

        // for getting header row
        $includedHeaderRow = $data['offset'] === 1 && !empty($data['isFileHeaderRow']);
        if ($includedHeaderRow) {
            $data['offset'] = 0;
        }

        switch ($data['fileFormat']) {
            case 'CSV':
            case 'Excel':
                $columns = $fileParser->getFileColumns($attachment);

                $fileOriginalData = $fileParser->getFileData($attachment, $data['offset'], $data['limit']);
                if ($includedHeaderRow) {
                    array_shift($fileOriginalData);
                }

                foreach ($fileOriginalData as $line => $fileLine) {
                    foreach ($fileLine as $k => $v) {
                        $fileData[$line][$columns[$k]] = $v;
                    }
                }
                $data['offset'] = $data['offset'] + $data['limit'];
                break;
            case 'JSON':
                $fileData = @json_decode($attachment->getContents(), true);
                if (!is_array($fileData)) {
                    $fileData = [];
                }
                $data['lastIteration'] = true;
                break;
            case 'XML':
                $contents = simplexml_load_string($attachment->getContents(), 'SimpleXMLElement', LIBXML_NOCDATA);
                $fileData = @json_decode(json_encode($contents), true);
                if (!is_array($fileData)) {
                    $fileData = [];
                }
                $data['lastIteration'] = true;
                break;
        }

        return $fileData;
    }

    protected function getCodeMessage(int $code): string
    {
        if ($code == 304) {
            return $this->getLanguage()->translate('nothingToUpdate', 'exceptions', 'ImportFeed');
        }

        if ($code == 403) {
            return $this->getLanguage()->translate('permissionDenied', 'exceptions', 'ImportFeed');
        }

        return 'HTTP Code: ' . $code;
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getFileParser(string $format): FileParserInterface
    {
        return $this->container->get(ImportFeed::getFileParserClass($format));
    }

    protected function getService(string $scope): Record
    {
        return $this->container->get('serviceFactory')->create($scope);
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->container->get(AttributeFieldConverter::class);
    }

    protected function getAttributeService(): \Pim\Services\Attribute
    {
        return $this->container->get('serviceFactory')->create('Attribute');
    }
}
