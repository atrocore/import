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

use Atro\Core\Exceptions\NotUnique;
use Doctrine\DBAL\Exception\ConstraintViolationException;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Import\Jobs\ImportTypeSimple;

class Link extends Varchar
{
    const ALLOWED_TYPES = ['bool', 'enum', 'varchar', 'float', 'int', 'text', 'wysiwyg'];

    public const MEMORY_FOREIGN_KEYS = 'loaded_exists_foreign_entities_keys';
    public const MEMORY_WHERE_FOREIGN_KEYS = 'loaded_exists_foreign_entities_by_where_keys';

    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? null : $config['default'];

        if (!empty($config['column'])) {
            if (count($config['column']) === 1) {
                $value = $this->getSearchValue($config['column'][0], $config, $row);
            } else {
                $value = [];

                foreach ($config['column'] as $key => $column) {
                    $value[] = $this->getSearchValue($column, $config, $row);
                }
            }

            if ($value !== null && $value !== (string)$config['emptyValue'] && $value !== (string)$config['markForNoRelation']) {
                if (isset($config['relEntityName'])) {
                    $entityName = $config['relEntityName'];
                } else {
                    $entityName = $this->getForeignEntityName($config);
                }

                $input = new \stdClass();

                $where = [];

                $values = !is_array($value) ? explode((string)$config['fieldDelimiterForRelation'], (string)$value) : $value;
                foreach ($config['importBy'] as $k => $field) {
                    $fieldData = $this->getMetadata()->get(['entityDefs', $entityName, 'fields', $field], ['type' => 'varchar']);

                    if (empty($fieldData['type']) || !in_array($fieldData['type'], self::ALLOWED_TYPES)) {
                        continue 1;
                    }

                    if (!array_key_exists($k, $values)) {
                        throw new BadRequest(sprintf($this->translate('wrongImportByValuesCount', 'exceptions', 'ImportFeed'), $entityName));
                    }

                    $this
                        ->getService('ImportConfiguratorItem')
                        ->getFieldConverter($fieldData['type'])
                        ->convert($input, ['name' => $field, 'column' => [0], 'default' => null], [$values[$k]]);

                    if (empty($fieldData['notStorable']) && isset($values[$k]) && $values[$k] !== '' && $values[$k] !== (string)$config['emptyValue']) {
                        $where[$field] = $values[$k];
                    }
                }

                $this->prepareWhere($config, $entityName, $where);

                $entity = null;
                if (!empty($where)) {
                    // load to memory
                    $this->loadToMemory($config, $this->getMemoryStorage()->get('importRowsPart'), $where);

                    // find in memory
                    $entity = $this->findEntityInMemory($where, $config);

                    if (empty($entity) && empty($config['createIfNotExist'])) {
                        throw new BadRequest(
                            sprintf($this->translate('noRecordsFoundFor', 'exceptions', 'ImportFeed'), $this->translate($entityName, 'scopeNames'), json_encode($where))
                        );
                    }
                }

                if (empty($entity) && $input !== new \stdClass() && !empty($config['createIfNotExist'])) {
                    $user = $this->container->get('user');
                    $userId = empty($user) ? null : $user->get('id');

                    $input->ownerUserId = $userId;
                    $input->ownerUserName = $userId;
                    $input->assignedUserId = $userId;
                    $input->assignedUserName = $userId;

                    if (!empty($config['foreignImportBy']) && !empty($config['foreignColumn'])) {
                        $this->prepareInputForCreateIfNotExist($input, $config, $row);
                    }

                    try {
                        $entity = $this->getService($entityName, true)->createEntity($input);
                    } catch (NotUnique|ConstraintViolationException $e) {
                        $entity = $this->findAlreadyExistsEntity($entityName, $where);
                    } catch (\Throwable $e) {
                        $className = get_class($e);

                        $message = sprintf($this->translate('relatedEntityCreatingFailed', 'exceptions', 'ImportFeed'), $this->translate($entityName, 'scopeNames'));
                        $message .= ' ' . $e->getMessage();

                        throw new $className($message);
                    }

                }

                if (!empty($entity)) {
                    $this->beforeSetValue($entity, $config, $row);
                    $value = $entity->get('id');
                } else {
                    $value = $default;
                }
            }
        } else {
            $value = $default;
        }

        if ($value === '' || $value === (string)$config['emptyValue']) {
            $value = $default;
        }

        if ($value === (string)$config['markForNoRelation']) {
            $value = null;
        }

        $fieldName = $this->getFieldName($config);

        $inputRow->$fieldName = $value;
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $value = null;

        if (!empty($foreign = $entity->get($item['name']))) {
            $value = $foreign->get('id');
        }

        $fieldName = $this->getFieldName($item);

        $restore->$fieldName = $value;
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
        $inputRow = new \stdClass();
        $this->convert($inputRow, $configuration, $row);

        $fieldName = $this->getFieldName($configuration);

        if (!property_exists($inputRow, $fieldName)) {
            throw new BadRequest("System cannot find value for '$fieldName'. Please, check configuration.");
        }

        $where[$fieldName] = $inputRow->$fieldName;
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->has('defaultId')) {
            $entity->set('default', empty($entity->get('defaultId')) ? null : $entity->get('defaultId'));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $entity->set('defaultId', null);
        $entity->set('defaultName', null);
        if (!empty($entity->get('default'))) {
            $relEntityName = $this->getForeignEntityName(['entity' => $entity->get('entity'), 'name' => $entity->get('name')]);
            if (!empty($relEntityName)) {
                $entity->set('defaultId', $entity->get('default'));
                $relEntity = $this->getEntityManager()->getEntity($relEntityName, $entity->get('defaultId'));
                $entity->set('defaultName', empty($relEntity) ? $entity->get('defaultId') : $relEntity->get('name'));
            }
        }
    }

    protected function getSearchValue($column, array $config, array $row)
    {
        $value = $row[$column] ?? null;
        if (strtolower((string)$value) === strtolower((string)$config['emptyValue'])) {
            $value = (string)$config['emptyValue'];
        }
        if (strtolower((string)$value) === strtolower((string)$config['nullValue'])
            || strtolower((string)$value) === strtolower((string)$config['markForNoRelation'])) {
            $value = null;
        }

        return $value;
    }

    protected function getForeignEntityName(array $config): string
    {
        if (!empty($config['relEntityName'])) {
            return $config['relEntityName'];
        }

        $res = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $config['name'], 'entity']);
        if (empty($res)) {
            $res = $this->getMetadata()->get(['entityDefs', $config['entity'], 'links', $config['name'], 'entity']);
        }

        return $res;
    }

    protected function getFieldName(array $config): string
    {
        return $config['name'] . 'Id';
    }

    protected function prepareWhere(array $config, string $entityName, array &$where): void
    {
        if ($entityName === 'File' && in_array('url', $config['importBy'])) {
            $where = [];
        }
    }

    protected function prepareInputForCreateIfNotExist($input, array $config, array $row): void
    {
        $foreignValues = [];
        $foreignColumn = $config['foreignColumn'];
        $foreignImportBy = $config['foreignImportBy'];

        if (count($foreignColumn) === 1) {
            $foreignValues = explode($config['fieldDelimiterForRelation'], (string)$row[$foreignColumn[0]]);
        } else {
            foreach ($foreignColumn as $column) {
                $foreignValues[] = $row[$column];
            }
        }

        foreach ($foreignImportBy as $key => $field) {
            if (isset($foreignValues[$key])) {
                $input->{$field} = $foreignValues[$key];
            }
        }
    }

    protected function loadToMemory(array $configuration, array $rows, array $where): void
    {
        /** @var ImportTypeSimple $service */
        $service = $this->container->get(ImportTypeSimple::class);

        $entityName = $this->getForeignEntityName($configuration);

        $foreignKeys = $this->getMemoryStorage()->get(self::MEMORY_FOREIGN_KEYS);

        $foreignWhereKeys = $this->getMemoryStorage()->get(self::MEMORY_WHERE_FOREIGN_KEYS) ?? [];

        ksort($where);
        $jsonWhere = json_encode($where);
        if (isset($foreignWhereKeys[$configuration['pos']][$entityName][$jsonWhere])) {
            return;
        }

        if (!empty($configuration['importBy']) && !empty($configuration['column'])) {
            $whereForCollection = $this->prepareWhereForCollection($configuration, $rows);
            $collection = $this->getEntityManager()->getRepository($entityName)->where($whereForCollection)->find();
            $collection = $this->prepareCollectionBeforeWhereKeysCreated($collection);

            foreach ($collection as $entity) {
                $itemKey = $service->createMemoryKey($entity->getEntityType(), $entity->get('id'));
                $this->getMemoryStorage()->set($itemKey, $entity);
                $foreignKeys[$configuration['pos']][$entityName][] = $itemKey;
                $whereKey = $service->createWhereKey(array_keys($where), $entity);
                $foreignWhereKeys[$configuration['pos']][$entityName][$whereKey] = $itemKey;
            }

            $this->getMemoryStorage()->set(self::MEMORY_FOREIGN_KEYS, $foreignKeys);
            $this->getMemoryStorage()->set(self::MEMORY_WHERE_FOREIGN_KEYS, $foreignWhereKeys);
        }
    }

    protected function prepareWhereForCollection(array $configuration, array $rows): array
    {
        $res = [];
        foreach ($configuration['importBy'] as $k => $field) {
            $columnName = empty($configuration['mainConfig']['column']) ? $configuration['column'][$k] : $configuration['mainConfig']['column'][$k];
            foreach ($rows as $row) {
                if ($row[$columnName] === $configuration['nullValue']) {
                    $res[$field][] = null;
                    continue;
                }
                if ($row[$columnName] === $configuration['emptyValue']) {
                    $res[$field][] = '';
                    continue;
                }

                $values = explode($configuration['delimiter'], (string)$row[$columnName]);
                foreach ($values as $value) {
                    $res[$field][] = $value;
                }
            }
            $res[$field] = array_values(array_unique($res[$field]));
        }

        return $res;
    }

    protected function findAlreadyExistsEntity(string $entityName, array $where): ?Entity
    {
        return $this->getEntityManager()->getRepository($entityName)->where($where)->findOne();
    }

    protected function findEntityInMemory(array $where, array $config): ?Entity
    {
        $entityName = $this->getForeignEntityName($config);
        $foreignWhereKeys = $this->getMemoryStorage()->get(self::MEMORY_WHERE_FOREIGN_KEYS) ?? [];

        ksort($where);
        $jsonWhere = json_encode($where);

        if (isset($foreignWhereKeys[$config['pos']][$entityName][$jsonWhere])) {
            return $this->getMemoryStorage()->get($foreignWhereKeys[$config['pos']][$entityName][$jsonWhere]);
        }

        return null;
    }

    protected function prepareCollectionBeforeWhereKeysCreated($collection): EntityCollection
    {
        return $collection;
    }

    protected function beforeSetValue(Entity $entity, array $config, array $row): void
    {
    }
}
