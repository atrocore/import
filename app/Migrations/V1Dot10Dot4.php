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

class V1Dot10Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-10-04 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE file ADD import_job_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_FILE_IMPORT_JOB_ID ON file (import_job_id, deleted)");

        $this->exec("ALTER TABLE file ADD import_feed_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_FILE_IMPORT_FEED_ID ON file (import_feed_id, deleted)");

        $this->exec("ALTER TABLE import_feed ADD folder_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_IMPORT_FEED_FOLDER_ID ON import_feed (folder_id, deleted)");

        $offset = 0;
        $limit = 10000;

        while (true) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('import_job')
                ->where('attachment_id IS NOT NULL OR converted_file_id IS NOT NULL')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->fetchAllAssociative();

            $offset = $offset + $limit;

            if (empty($res)) {
                break;
            }

            foreach ($res as $row) {
                foreach (['attachment_id', 'converted_file_id'] as $column) {
                    if (empty($row[$column])) {
                        continue;
                    }

                    $this->getConnection()->createQueryBuilder()
                        ->update('file')
                        ->set('import_job_id', ':importJobId')
                        ->set('import_feed_id', ':importFeedId')
                        ->where('id=:fileId')
                        ->setParameter('importJobId', $row['id'])
                        ->setParameter('importFeedId', $row['id'])
                        ->setParameter('fileId', $row[$column])
                        ->executeQuery();
                }
            }
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
