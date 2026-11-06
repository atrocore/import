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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Migration\Base;

class V1Dot4Dot26 extends Base
{
    public function up(): void
    {
        $ids = $this->getPDO()->query('SELECT id FROM import_feed WHERE proceed_already_proceeded=1')->fetchAll(\PDO::FETCH_COLUMN);

        $this->getPDO()->exec("ALTER TABLE import_feed CHANGE proceed_already_proceeded proceed_already_proceeded VARCHAR(255) DEFAULT 'mistake' COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("UPDATE import_feed SET proceed_already_proceeded='mistake' WHERE proceed_already_proceeded='0'");

        foreach ($ids as $id) {
            $this->getPDO()->exec("UPDATE import_feed SET proceed_already_proceeded='repeat' WHERE id='$id'");
        }

        $this->getPDO()->exec("ALTER TABLE import_feed CHANGE proceed_already_proceeded repeat_processing varchar(255) default 'mistake' null");

        $this->getPDO()->exec("DELETE FROM import_job_log WHERE 1");

        $this->getPDO()->exec("ALTER TABLE import_job_log CHANGE type type VARCHAR(10) DEFAULT 'create' COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE import_job_log CHANGE entity_name entity_name VARCHAR(100) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE import_job_log CHANGE entity_id entity_id VARCHAR(30) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $this->getPDO()->exec("ALTER TABLE import_job ADD converted_file_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $this->getPDO()->exec(
            "CREATE UNIQUE INDEX UNIQ_58FA52B877D1F4B181257D5D8CDE572916EFC72DC6B6CC86EB3B4E33 ON import_job_log (import_job_id, entity_id, type, entity_name, `row_number`, deleted)"
        );
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }
}
