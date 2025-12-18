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

namespace Import\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class Entity extends AbstractListener
{
    private ?string $importJobEntity = null;
    public array $filterData;

    public function beforeGetSelectParams(Event $event): void
    {
        $entityType = $event->getArgument('entityType');
        $params = $event->getArgument('params');
        if(!empty($params['where'])) {
            foreach ($params['where'] as $k => $item) {
                if(!empty($item['condition']) && !empty($item['rules'])) {
                    foreach ($item['rules'] as $rk => $rule) {
                        if(empty($rule['id']) || empty($rule['type']) || empty($rule['value'])) {
                            continue;
                        }
                        $itemRule = [
                            "attribute" => $rule['id'],
                            "type" => $rule['type'],
                            "value" => $rule['value'],
                        ];
                        if (is_array($rule) && !empty($callback = $this->prepareImportJobFilterCallback($entityType, $itemRule))) {
                            $params['filterCallbacks'][] = $callback;
                            unset($params['where'][$k]['rules'][$rk]);
                            $params['where'][$k]['rules'] = array_values($params['where'][$k]['rules']);
                        }
                    }
                }else{
                    if (is_array($item) && !empty($callback = $this->prepareImportJobFilterCallback($entityType, $item))) {
                        $params['filterCallbacks'][] = $callback;
                        unset($params['where'][$k]);
                        $params['where'] = array_values($params['where']);
                    }
                }

            }
        }

        $event->setArgument('params', $params);
    }

    public function afterGetSelectParams(Event $event): void
    {
        $params = $event->getArgument('params');
        if (!empty($params['filterCallbacks'])) {
            $result = $event->getArgument('result');
            foreach ($params['filterCallbacks'] as $callback) {
                $result['callbacks'][] = $callback;
            }
            $event->setArgument('result', $result);
        }
    }

    public function applyFilterByImportJob(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        $alias = $mapper->getQueryConverter()->getMainTableAlias();

        if ($this->getEntityManager()->getRepository('ImportJobLog')->hasClickHouse()) {
            $entityIds = $this->getEntityManager()->getRepository('ImportJobLog')
                ->getEntityIds($this->filterData['scope'], $this->filterData['action'], $this->filterData['value']);
            $qb
                ->andWhere("$alias.id {$this->filterData['type']} (:entityIds)")
                ->setParameter("entityIds", $entityIds, Connection::PARAM_STR_ARRAY);
        } else {
            $importJobPart = '';

            if (isset($this->filterData['value'])) {
                $importJobPart = ' AND ijl.import_job_id IN (:importJobIds)';
                $qb->setParameter('importJobIds', $this->filterData['value'], Connection::PARAM_STR_ARRAY);
            }

            $qb->andWhere(
                "$alias.id {$this->filterData['type']} (SELECT ijl.entity_id FROM import_job_log ijl WHERE ijl.deleted=:false AND ijl.type=:filterAction AND ijl.entity_name=:filterScope $importJobPart)"
            );

            $qb->setParameter('false', false, ParameterType::BOOLEAN);
            $qb->setParameter('filterAction', $this->filterData['action']);
            $qb->setParameter('filterScope', $this->filterData['scope']);
        }
    }

    public function applyFilterByEntityName(QueryBuilder $qb, IEntity $relEntity, array $params, Mapper $mapper): void
    {
        if (!$this->importJobEntity) {
            return;
        }

        $alias = $mapper->getQueryConverter()->getMainTableAlias();
        $logQb = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $logQb->distinct()
            ->select('ijl.import_job_id')
            ->from('import_job_log', 'ijl')
            ->where("ijl.import_job_id = $alias.id")
            ->andWhere('ijl.deleted = :false')
            ->andWhere('ijl.entity_name = :entity_name');

        $qb->andWhere($logQb->expr()->in("$alias.id", $logQb->getSQL()));
        $qb->setParameter('false', false, ParameterType::BOOLEAN);
        $qb->setParameter('entity_name', $this->importJobEntity);
    }

    protected function prepareImportJobFilterCallback(string $scope, array $item): array
    {
        if ($scope == 'ImportJob' && ($item['attribute'] ?? null) == 'entityName' && !empty($item['value'])) {
            $this->importJobEntity = $item['value'];
            return [$this, 'applyFilterByEntityName'];
        }

        if (
            isset($item['attribute'])
            && in_array($item['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            $this->filterData = [
                'type'   => 'IN',
                'scope'  => $scope,
                'action' => $this->getJobType($item['attribute']),
                'value'  => (array)$item['value'],
            ];
            return [$this, 'applyFilterByImportJob'];
        }

        if (
            !empty($item['value'][1]['type'])
            && $item['value'][1]['type'] === 'notIn'
            && in_array($item['value'][1]['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            $this->filterData = [
                'type'   => 'NOT IN',
                'scope'  => $scope,
                'action' => $this->getJobType($item['value'][1]['attribute']),
                'value'  => (array)$item['value'][1]['value'],
            ];
            return [$this, 'applyFilterByImportJob'];
        }

        if (
            !empty($item['value'][1]['type'])
            && $item['value'][1]['type'] === 'equals'
            && in_array($item['value'][1]['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            $this->filterData = [
                'type'   => 'NOT IN',
                'scope'  => $scope,
                'action' => $this->getJobType($item['value'][1]['attribute']),
                'value'  => null
            ];
            return [$this, 'applyFilterByImportJob'];
        }

        if (
            !empty($item['value'][1]['type'])
            && $item['value'][1]['type'] === 'notEquals'
            && in_array($item['value'][1]['attribute'], ['filterCreateImportJob', 'filterUpdateImportJob'])
        ) {
            $this->filterData = [
                'type'   => 'IN',
                'scope'  => $scope,
                'action' => $this->getJobType($item['value'][1]['attribute']),
                'value'  => null
            ];
            return [$this, 'applyFilterByImportJob'];
        }

        return [];
    }

    protected function getJobType(string $name): string
    {
        return $name === 'filterCreateImportJob' ? 'create' : 'update';
    }
}
