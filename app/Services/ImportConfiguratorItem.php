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

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Import\FieldConverters\Varchar;
use Import\Repositories\ImportConfiguratorItem as ImportConfiguratorItemRepository;

class ImportConfiguratorItem extends Base
{
    protected $mandatorySelectAttributeList
        = [
            'importFeedId',
            'importBy',
            'createIfNotExist',
            'replaceArray',
            'default',
            'type',
            'entityAttributeId',
            'locale',
            'sortOrder',
            'foreignColumn',
            'foreignImportBy'
        ];


    protected ?Container $container2 = null;

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        if (!empty($collection[0])) {
            $this->getImportFeedService()->putAttributesToMetadata($collection[0]->get('importFeedId'));
            foreach ($collection as $entity) {
                $entity->_withAttributesMetadata = true;
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($importFeed = $entity->get('importFeed'))) {
            return;
        }

        if (empty($entity->_withAttributesMetadata)) {
            $this->getImportFeedService()->putAttributesToMetadata($importFeed->get('id'));
        }

        $entity->set('entity', $importFeed->getFeedField('entity'));
        $entity->set('sourceFields', $importFeed->get('sourceFields'));

        if ($this->getMetadata()->get("scopes.{$entity->get('entity')}.hasAttribute")) {
            $name = $entity->get('name');
            if (!empty($entity->get('entityAttributeId')) &&
                (empty($name) || empty($this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields.{$name}")))) {
                foreach ($this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields") ?? [] as $field => $def) {
                    if (!empty($def['attributeId']) && $def['attributeId'] === $entity->get('entityAttributeId')) {
                        $entity->set('name', $field);
                        break;
                    }
                }
            }
        }

        // prepare field defs
        $entity->set('fieldDefs', $this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields.{$entity->get('name')}"));


        $fieldType = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $entity->get('name'), 'type'], 'varchar');


        $this->prepareDefaultField($fieldType, $entity);
    }

    public function getFieldConverter($type)
    {
        $class = $this->getMetadata()->get(['import', 'configurator', 'fields', $type, 'converter'], Varchar::class);

        return new $class($this->getInjection('container'), $this);
    }

    public function updateEntity(string $id, \stdClass $data): bool
    {
        if (property_exists($data, '_previousItemId') && property_exists($data, '_itemId')) {
            $this->getRepository()->updatePosition((string)$data->_itemId, (string)$data->_previousItemId);
            return true;
        }

        return parent::updateEntity($id, $data);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    /**
     * Returns alternative Container
     *
     * @return Container
     */
    public function getContainer2(): Container
    {
        if (is_null($this->container2)) {
            $this->container2 = (new \Atro\Core\Application())->getContainer();

            $auth = new \Espo\Core\Utils\Auth($this->container2);
            $auth->useNoAuth();

            $importJobId = $this->getMemoryStorage()->get('importJobId');
            if (!empty($importJobId)) {
                /** @var StorageInterface $memoryStorage */
                $memoryStorage = $this->container2->get('memoryStorage');
                $memoryStorage->set('importJobId', $importJobId);
            }
        }

        return $this->container2;
    }

    protected function prepareDefaultField(string $type, Entity $entity): void
    {
        $converter = $this->getFieldConverter(ImportConfiguratorItemRepository::prepareConverterType($type));
        if (!empty($converter)) {
            $converter->prepareForOutputConfiguratorDefaultField($entity);
        }
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    public function prepareDuplicateEntityForSave(Entity $entity, Entity $newImportConfiguratorEntity): void
    {
        $fieldType = $this->getFieldType($entity, $newImportConfiguratorEntity);
        $this->prepareDefaultField($fieldType, $newImportConfiguratorEntity);
    }

    public function getFieldType(Entity $entity, Entity $importConfiguratorEntity): string
    {
        return $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $importConfiguratorEntity->get('name'), 'type'], 'varchar');
    }

    protected function getImportFeedService(): ImportFeed
    {
        return $this->getServiceFactory()->create('ImportFeed');
    }
}
