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
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class V1Dot9Dot18 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-08 18:00:00');
    }

    public function up(): void
    {
        $this->updateConfiguratorItems();
        $this->updateData();
    }

    protected function updateConfiguratorItems(): void
    {
        try {
            $connection = $this->getConnection();

            $importFeedIds = $connection
                ->createQueryBuilder()
                ->select('id')
                ->from('import_feed')
                ->where('data LIKE :data')
                ->setParameter('data', "%\"entity\":\"Product\"%")
                ->fetchAllAssociative();

            if (!empty($importFeedIds)) {
                $importFeedIds = array_column($importFeedIds, 'id');

                $connection
                    ->createQueryBuilder()
                    ->update('import_configurator_item', 'ici')
                    ->set('name', ':newName')
                    ->where('name = :oldName')
                    ->andWhere('import_feed_id IN (:importFeedIds)')
                    ->andWhere('deleted = :false')
                    ->setParameter('newName', 'number')
                    ->setParameter('oldName', 'sku')
                    ->setParameter('importFeedIds', $importFeedIds, Mapper::getParameterType($importFeedIds))
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeStatement();
            }
        } catch (\Throwable $e) {

        }
    }

    protected function updateData(): void
    {
        $actions = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'data')
            ->from('import_feed')
            ->where('data IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($actions as $action) {
            if (strpos($action['data'], 'sku') !== false) {
                $action['data'] = str_replace('.sku', '.number', $action['data']);
                $action['data'] = str_replace('"sku"', '"number"', $action['data']);
                $action['data'] = str_replace("'sku'", "'number'", $action['data']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('import_feed')
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('id', $action['id'])
                        ->setParameter('data', $action['data'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }
}
