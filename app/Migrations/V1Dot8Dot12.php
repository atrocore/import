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

namespace Import\Migrations;

use Atro\Core\Migration\Base;

class V1Dot8Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-01-16 12:00:00');
    }

    public function up(): void
    {
        $schema = $this->getCurrentSchema();
        $toSchema = clone $schema;

        $this->addColumn($toSchema, 'import_job', 'created_count', ['type' => 'int', 'default' => 0]);
        $this->addColumn($toSchema, 'import_job', 'updated_count', ['type' => 'int', 'default' => 0]);
        $this->addColumn($toSchema, 'import_job', 'skipped_count', ['type' => 'int', 'default' => 0]);
        $this->addColumn($toSchema, 'import_job', 'deleted_count', ['type' => 'int', 'default' => 0]);
        $this->addColumn($toSchema, 'import_job', 'errors_count', ['type' => 'int', 'default' => 0]);

        foreach ($this->schemasDiffToSql($schema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }

        $offset = 0;
        $limit = 100;
        while (true) {
            $rows = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('import_job')
                ->where('deleted = :false')
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->executeQuery()
                ->fetchAllAssociative();

            $ids = array_column($rows, 'id');
            if (empty($ids)) {
                break;
            }

            $counts = \Import\Repositories\ImportJob::getImportJobsCounters($this->getConnection(), $ids);
            foreach ($ids as $id) {
                if (!isset($counts[$id])) {
                    continue;
                }

                $this->getConnection()->createQueryBuilder()
                    ->update('import_job')
                    ->set('created_count', ':created_count')
                    ->set('updated_count', ':updated_count')
                    ->set('deleted_count', ':deleted_count')
                    ->set('skipped_count', ':skipped_count')
                    ->set('errors_count', ':errors_count')
                    ->where('id = :id')
                    ->setParameter('id', $id)
                    ->setParameter('created_count', $counts[$id]['created_count'])
                    ->setParameter('updated_count', $counts[$id]['updated_count'])
                    ->setParameter('deleted_count', $counts[$id]['deleted_count'])
                    ->setParameter('skipped_count', $counts[$id]['skipped_count'])
                    ->setParameter('errors_count', $counts[$id]['errors_count'])
                    ->executeStatement();
            }

            $offset += $limit;
        }
    }
}
