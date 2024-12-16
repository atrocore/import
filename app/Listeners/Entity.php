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
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Espo\ORM\IEntity;

class Entity extends AbstractListener
{
    public array $filterData;

    public function beforeGetSelectParams(Event $event): void
    {
        $entityType = $event->getArgument('entityType');
        $params = $event->getArgument('params');

        if (!empty($params['where'])) {
            foreach ($params['where'] as $k => $item) {
                if (is_array($item) && !empty($callback = $this->prepareImportJobFilterCallback($entityType, $item))) {
                    $params['filterCallbacks'][] = $callback;
                    unset($params['where'][$k]);
                    $params['where'] = array_values($params['where']);
                }
            }
            $event->setArgument('params', $params);
        }
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

        $importJobPart = '';

        if (isset($this->filterData['value'])) {
            $importJobPart = ' AND ijl.import_job_id=:importJobId';
            $qb->setParameter('importJobId', $this->filterData['value'], Mapper::getParameterType($this->filterData['value']));
        }

        $qb->andWhere(
            "$alias.id {$this->filterData['type']} (SELECT ijl.entity_id FROM import_job_log ijl WHERE ijl.deleted=:false AND ijl.type=:filterAction AND ijl.entity_name=:filterScope $importJobPart)"
        );

        $qb->setParameter('false', false, ParameterType::BOOLEAN);
        $qb->setParameter('filterAction', $this->filterData['action']);
        $qb->setParameter('filterScope', $this->filterData['scope']);
    }

    protected function prepareImportJobFilterCallback(string $scope, array $item): array
    {
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
