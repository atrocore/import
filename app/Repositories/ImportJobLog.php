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

use Atro\Core\Templates\Repositories\Archive;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ImportJobLog extends Archive
{
    protected bool $cacheable = false;

    protected array $cachedImportJobs = [];

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->createParentJobLog($entity, $options);
    }

    public function createParentJobLog(Entity $entity, array $options): void
    {
        if (!empty($options['skipParentLog'])) {
            return;
        }

        if (empty($entity->get('importJobId'))) {
            return;
        }

        $importJob = $this->getCachedImportJob($entity->get('importJobId'));
        if (empty($importJob->get('parentId'))) {
            return;
        }

        $parentJob = $this->getCachedImportJob($importJob->get('parentId'));
        if (empty($parentJob)) {
            return;
        }

        $rowNumberPart = $this->getMemoryStorage()->get("import_job_{$importJob->get('id')}_rowNumberPart") ?? 0;
        $rowNumber = $rowNumberPart + $entity->get('rowNumber');

        if ($parentJob->get('entityName') === $entity->get('entityName')) {
            $parentLog = $this->getEntityManager()->getEntity('ImportJobLog');
            $parentLog->set('entityName', $entity->get('entityName'));
            $parentLog->set('entityId', $entity->get('entityId'));
            $parentLog->set('importJobId', $importJob->get('parentId'));
            $parentLog->set('type', $entity->get('type'));
            $parentLog->set('skippedByScript', $entity->get('skippedByScript'));
            $parentLog->set('rowNumber', $rowNumber);
            $parentLog->set('row', $entity->get('row'));
            $parentLog->set('message', $entity->get('message'));
            try {
                $this->getEntityManager()->saveEntity($parentLog, ['skipParentLog' => true]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    protected function getCachedImportJob(string $importJobId): ?Entity
    {
        if (!isset($this->cachedImportJobs[$importJobId])) {
            $this->cachedImportJobs[$importJobId] = $this->getEntityManager()->getRepository('ImportJob')->get($importJobId);
        }

        return $this->cachedImportJobs[$importJobId];
    }

    public function getEntityIds(string $entityName, string $type, ?array $importJobIds, $limit = 65000): array
    {
        $con = $this->hasClickHouse() ? $this->getClickHouseConnection() : $this->getConnection();

        $importJobPart = '';

        if (!empty($importJobIds)) {
            //clickhouse does not support Connection::PARAM_STR_ARRAY
            $inList = "'" . implode("','", $importJobIds) . "'";
            $importJobPart = " AND ijl.import_job_id IN ($inList)";
        }

        $result =  $con->createQueryBuilder()
            ->select('ijl.entity_id')
            ->from('import_job_log', 'ijl')
            ->where('ijl.deleted=:false AND ijl.type=:type')
            ->andWhere("ijl.entity_name=:entityName $importJobPart")
            ->setMaxResults($limit)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('entityName', $entityName)
            ->setParameter('type', $type)
            ->fetchAllAssociative();

        return array_column($result, 'entity_id');
    }

    public function getNotFoundEntityIdsByJobId(string $jobId, string $entityName, int $limit = 0, int $offset = 0): array
    {
        // cannot perform cross-db subquery
        if ($this->hasClickHouse()) {
            return [];
        }

        $subquery = $this->getConnection()->createQueryBuilder()
            ->select('ijl.entity_id')
            ->from('import_job_log', 'ijl')
            ->where('ijl.deleted = :false')
            ->andWhere('ijl.import_job_id = :jobId')
            ->andWhere('ijl.type IN (:type)')
            ->andWhere('ijl.entity_id IS NOT NULL');

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from($this->getMapper()->toDb($entityName))
            ->where('deleted = :false')
            ->andWhere('id NOT IN (' . $subquery->getSQL() . ')')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('jobId', $jobId)
            ->setParameter('type', ['create', 'update', 'skip'], Connection::PARAM_STR_ARRAY);

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        return $qb->executeQuery()
            ->fetchFirstColumn();
    }


    protected function getClickHouseConnection(): Connection
    {
        return $this->getInjection('container')->get('clickhouseConnection');
    }
}
