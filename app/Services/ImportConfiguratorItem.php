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
            'attributeId',
            'locale',
            'sortOrder',
            'foreignColumn',
            'foreignImportBy',
            'attributeValue'
        ];

    protected array $attributes = [];

    protected ?Container $container2 = null;

    public function getSelectAttributeList($params)
    {
        $res = parent::getSelectAttributeList($params);

        if ($res !== null && !empty($this->getMetadata()->get(['scopes', 'Channel']))) {
            $res = array_merge($res, ['channelId', 'channelName']);
        }

        return $res;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($importFeed = $entity->get('importFeed'))) {
            return;
        }

        $entity->set('entity', $importFeed->getFeedField('entity'));
        $entity->set('sourceFields', $importFeed->get('sourceFields'));

        // prepare field defs
        if ($entity->get('type') === 'Field') {
            $entity->set('fieldDefs', $this->getMetadata()->get("entityDefs.{$entity->get('entity')}.fields.{$entity->get('name')}"));
        }

        if ($entity->get('type') === 'Attribute') {
            $attribute = $this->getServiceFactory()->create('Attribute')->getEntity($entity->get('attributeId'));
            if (empty($attribute)) {
                throw new BadRequest('No such Attribute.');
            }
            $entity->set('name', $attribute->get('name'));
            $entity->set('attributeData', $attribute->toArray());
            $fieldType = $attribute->get('type');
        } else {
            $fieldType = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $entity->get('name'), 'type'], 'varchar');
        }

        $this->prepareDefaultField($fieldType, $entity);
    }

    public function getFieldConverter($type)
    {
        $class = $this->getMetadata()->get(['import', 'configurator', 'fields', $type, 'converter'], Varchar::class);

        return new $class($this->getInjection('container'), $this);
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_previousItemId') && property_exists($data, '_itemId')) {
            $this->getRepository()->updatePosition((string)$data->_itemId, (string)$data->_previousItemId);
            return $this->readEntity($id);
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
            if(!empty($importJobId)) {
                /** @var StorageInterface $memoryStorage */
                $memoryStorage = $this->container2->get('memoryStorage');
                $memoryStorage->set('importJobId', $importJobId);
            }
        }

        return $this->container2;
    }

    protected function prepareDefaultField(string $type, Entity $entity): void
    {
        if ($type === 'varchar' && !empty($this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $entity->get('name'), 'unitField']))) {
            $type = 'valueWithUnit';
        }
        if (!empty($attribute = $entity->get('attribute')) && $entity->get('attributeValue') == 'value' && !empty($attribute->get('measureId'))) {
            $type = 'valueWithUnit';
        }
        $converter = $this->getFieldConverter(ImportConfiguratorItemRepository::prepareConverterType($type, $entity->get('attributeValue')));
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
        if ($importConfiguratorEntity->get('type') === 'Attribute') {
            if (empty($attribute = $this->getEntityManager()->getEntity('Attribute', $importConfiguratorEntity->get('attributeId')))) {
                throw new BadRequest('No such Attribute.');
            }
            $fieldType = $attribute->get('type');
        } else {
            $fieldType = $this->getMetadata()->get(['entityDefs', $entity->get('entity'), 'fields', $importConfiguratorEntity->get('name'), 'type'], 'varchar');
        }

        return $fieldType;
    }

    public function getAttributeById(string $id): ?Entity
    {
        if (!isset($this->attributes[$id])) {
            $this->attributes[$id] = $this->getEntityManager()->getEntity('Attribute', $id);
        }

        return $this->attributes[$id];
    }
}
