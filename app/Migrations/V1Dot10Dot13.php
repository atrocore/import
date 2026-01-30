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

class V1Dot10Dot13 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-30 18:10:00');
    }

    public function up(): void
    {
        $this->migrateConfiguratorItems();
        $this->migrateImportBy();
        $this->migrateData();
    }

    protected function migrateConfiguratorItems(): void
    {
        try {
            $connection = $this->getConnection();

            $feedsIds = $connection
                ->createQueryBuilder()
                ->select('id')
                ->from('import_feed')
                ->where('data LIKE :data')
                ->setParameter('data', "%\"entity\":\"Product\"%")
                ->fetchAllAssociative();

            if (!empty($feedsIds)) {
                $feedsIds = array_column($feedsIds, 'id');

                $connection
                    ->createQueryBuilder()
                    ->update('import_configurator_item', 'ici')
                    ->set('name', ':newName')
                    ->where('name = :oldName')
                    ->andWhere('import_feed_id IN (:importFeedIds)')
                    ->andWhere('deleted = :false')
                    ->setParameter('newName', 'status')
                    ->setParameter('oldName', 'productStatus')
                    ->setParameter('importFeedIds', $feedsIds, Mapper::getParameterType($feedsIds))
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->executeStatement();
            }
        } catch (\Throwable $e) {

        }
    }

    protected function migrateImportBy(): void
    {
        $items = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id, import_by')
            ->from('import_configurator_item')
            ->where('import_by LIKE :search')
            ->andWhere('deleted = :false')
            ->setParameter('search', '%"productStatus"%')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($items as $item) {
            $importBy = @json_decode($item['import_by'], true);

            if (is_array($importBy)) {
                $key = array_search('productStatus', $importBy);

                if ($key !== false) {
                    $importBy[$key] = 'status';

                    try {
                        $this
                            ->getConnection()
                            ->createQueryBuilder()
                            ->update('import_configurator_item')
                            ->set('import_by', ':importBy')
                            ->where('id = :id')
                            ->setParameter('id', $item['id'])
                            ->setParameter('importBy', json_encode($importBy))
                            ->executeQuery();
                    } catch (\Throwable $e) {

                    }
                }
            }
        }
    }

    protected function migrateData(): void
    {
        $feeds = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'data')
            ->from('import_feed')
            ->where('data IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($feeds as $feed) {
            if (strpos($feed['data'], 'productStatus') !== false) {
                $feed['data'] = str_replace('.productStatus', '.status', $feed['data']);
                $feed['data'] = str_replace('"productStatus"', '"status"', $feed['data']);
                $feed['data'] = str_replace("'productStatus'", "'status'", $feed['data']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('import_feed')
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('id', $feed['id'])
                        ->setParameter('data', $feed['data'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }
}
