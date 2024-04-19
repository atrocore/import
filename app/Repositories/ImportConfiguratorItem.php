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

namespace Import\Repositories;

use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ImportConfiguratorItem extends Base
{
    public static function prepareConverterType(string $type, ?string $attributeValue): string
    {
        if ($attributeValue === null) {
            $attributeValue = 'value';
        }

        if ($attributeValue === 'valueUnitId') {
            return 'unit';
        }

        if ($type === 'rangeInt') {
            return 'int';
        }

        if ($type === 'rangeFloat') {
            return 'float';
        }

        return $type;
    }

    public function updatePosition(string $itemId, string $previousItemId): void
    {
        $res = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id')
            ->from('import_configurator_item')
            ->where('deleted=:false')
            ->andWhere('import_feed_id=:importFeedId')
            ->setParameter('importFeedId', $this->get($itemId)->get('importFeedId'))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->orderBy('sort_order', 'ASC')
            ->fetchFirstColumn();

        $ids = [];
        if (empty($previousItemId)) {
            $ids[] = $itemId;
        }

        foreach ($res as $id) {
            if (!in_array($id, $ids)) {
                $ids[] = $id;
            }
            if ($id === $previousItemId) {
                $ids[] = $itemId;
            }
        }

        foreach ($ids as $k => $id) {
            $this
                ->getConnection()
                ->createQueryBuilder()
                ->update('import_configurator_item')
                ->set('sort_order', ':sortOrder')
                ->setParameter('sortOrder', $k * 10)
                ->where('id=:id')->setParameter('id', $id)
                ->executeQuery();
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($importFeed = $entity->get('importFeed'))) {
            throw new BadRequest('ImportFeed is required for Configurator item.');
        }

        $this->checkIfVirtualFieldIsIdentifier($entity, $importFeed);

        if ($entity->get('type') === 'Field') {
            $type = $this->getMetadata()->get(['entityDefs', $importFeed->getFeedField('entity'), 'fields', $entity->get('name'), 'type'], 'varchar');
            if ($type === 'varchar' && !empty($this->getMetadata()->get(['entityDefs', $importFeed->getFeedField('entity'), 'fields', $entity->get('name'), 'unitField']))) {
                $type = 'valueWithUnit';
            }
        } elseif ($entity->get('type') === 'Attribute') {
            if (empty($attribute = $entity->get('attribute'))) {
                throw new BadRequest('No such Attribute.');
            }
            $type = self::prepareConverterType($attribute->get('type'), $entity->get('attributeValue'));
        }

        $this->prepareDefaultField($type, $entity);

        if (!empty($entity->get('createIfNotExist'))) {
            $columns = $entity->get('foreignColumn');
            $importBy = $entity->get('foreignImportBy');

            if (empty($columns) || empty($importBy)) {
                throw new BadRequest($this->getInjection('language')->translate('foreignColumnsAndFieldsEmpty', 'exceptions', 'ImportConfiguratorItem'));
            }

            if ((count($columns) === 1 && count($importBy) < 1) || (count($columns) > 1 && count($columns) !== count($importBy))) {
                throw new BadRequest($this->getInjection('language')->translate('wrongFieldsNumber', 'exceptions', 'ImportConfiguratorItem'));
            }
        }

        if ($entity->isNew()) {
            $last = $this
                ->where(['importFeedId' => $entity->get('importFeedId')])
                ->order('sortOrder', 'DESC')
                ->findOne();

            if (is_null($entity->get('sortOrder'))) {
                $entity->set('sortOrder', empty($last) ? 0 : (int)$last->get('sortOrder') + 1);
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
    }

    public function checkIfVirtualFieldIsIdentifier(Entity $entity, Entity $importFeedEntity): void
    {
        $configuratorFieldName = $entity->get('name');
        $isIdentifier = $entity->get('entityIdentifier');

        if (!empty($configuratorFieldName)) {
            $isVirtualField = $this->getMetadata()
                ->get(['entityDefs', $importFeedEntity->getFeedField('entity'), 'fields', $configuratorFieldName, 'notStorable']);

            if ($isIdentifier === true && $isVirtualField === true) {
                throw new BadRequest('Virtual field should not be set as identifier');
            }
        }
    }

    protected function prepareDefaultField(string $type, Entity $entity): void
    {
        $this
            ->getInjection('container')
            ->get('serviceFactory')
            ->create('ImportConfiguratorItem')
            ->getFieldConverter($type)
            ->prepareForSaveConfiguratorDefaultField($entity);
    }
}
