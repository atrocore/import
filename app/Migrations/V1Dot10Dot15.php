<?php
/*
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

class V1Dot10Dot15 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-12 15:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE action ADD COLUMN import_feed_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_ACTION_IMPORT_FEED_ID ON action (import_feed_id, deleted)");

        $rows = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from($this->getDbal()->quoteIdentifier('action'))
            ->where('data IS NOT NULL')
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $data = json_decode($row['data'], true);
            if (!is_array($data)) {
                continue;
            }

            $importFeedId = $data['field']['importFeedId'] ?? null;
            $hasName = isset($data['field']['importFeedName']);

            if (!$importFeedId && !$hasName) {
                continue;
            }

            unset($data['field']['importFeedId'], $data['field']['importFeedName']);

            $qb = $this->getDbal()->createQueryBuilder()
                ->update($this->getDbal()->quoteIdentifier('action'))
                ->set('data', ':data')
                ->setParameter('data', json_encode($data))
                ->where('id = :id')
                ->setParameter('id', $row['id']);

            if ($importFeedId) {
                $qb->set('import_feed_id', ':val')
                    ->setParameter('val', $importFeedId);
            }

            $qb->executeStatement();
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}