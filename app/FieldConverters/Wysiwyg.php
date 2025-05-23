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

namespace Import\FieldConverters;

use Atro\Core\Exceptions\NotModified;
use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\Services\Base;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\Core\Container;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Import\Jobs\ImportTypeSimple;
use Import\Services\ImportConfiguratorItem;

class Wysiwyg
{
    protected Container $container;
    protected ImportConfiguratorItem $configuratorItem;

    public function __construct(Container $container, ImportConfiguratorItem $configuratorItem)
    {
        $this->container = $container;
        $this->configuratorItem = $configuratorItem;
    }

    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? '' : $config['default'];
        $emptyValue = empty($config['emptyValue']) ? '' : (string)$config['emptyValue'];
        $nullValue = empty($config['nullValue']) ? 'Null' : (string)$config['nullValue'];

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            if (strtolower((string)$value) === strtolower($emptyValue) || $value === '') {
                $value = $default;
            }
            if (strtolower((string)$value) === strtolower($nullValue)) {
                $value = null;
            }
        } else {
            $value = $default;
        }

        $inputRow->{$config['name']} = $value != null ? (string)$value : $value;
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $restore->{$item['name']} = $entity->get($item['name']);
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
        $inputRow = new \stdClass();
        $this->convert($inputRow, $configuration, $row);

        $where[$configuration['name']] = $inputRow->{$configuration['name']};
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getEntityManager(bool $alternativeContainer = false): EntityManager
    {
        if ($alternativeContainer){
            return $this->configuratorItem->getContainer2()->get('entityManager');
        }

        return $this->container->get('entityManager');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        return $this->getLanguage()->translate($label, $category, $scope);
    }

    protected function getService(string $name, bool $alternativeContainer = false): Base
    {
        $key = "service_{$name}";

        $container = $this->container;

        if ($alternativeContainer) {
            $key .= "_container2";
            $container = $this->configuratorItem->getContainer2();
        }

        if (!$this->getMemoryStorage()->has($key)) {
            $this->getMemoryStorage()->set($key, $container->get('serviceFactory')->create($name));
        }

        return $this->getMemoryStorage()->get($key);
    }

    protected function getEntityById(string $scope, string $id): Entity
    {
        /** @var ImportTypeSimple $service */
        $service = $this->container->get(ImportTypeSimple::class);

        return $service->getEntityById($scope, $id);
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }
}
