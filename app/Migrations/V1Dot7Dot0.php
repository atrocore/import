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

class V1Dot7Dot0 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX idx_import_job_log_unique_job_log");
            $this->exec("ALTER TABLE import_job_log ADD row TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN import_job_log.row IS '(DC2Type:jsonObject)'");
            $this->exec("DROP INDEX idx_import_job_log_modified_at");
            $this->exec("DROP INDEX idx_import_job_log_name");
            $this->exec("ALTER TABLE import_job_log ADD skipped_by_script BOOLEAN DEFAULT 'false' NOT NULL");
            $this->exec("ALTER TABLE import_job_log DROP name");
            $this->exec("ALTER TABLE import_job_log DROP restore_data");
        } else {
            $this->exec("DROP INDEX IDX_IMPORT_JOB_LOG_UNIQUE_JOB_LOG ON import_job_log");
            $this->exec("DROP INDEX IDX_IMPORT_JOB_LOG_MODIFIED_AT ON import_job_log");
            $this->exec("DROP INDEX IDX_IMPORT_JOB_LOG_NAME ON import_job_log");
            $this->exec("ALTER TABLE import_job_log ADD skipped_by_script TINYINT(1) DEFAULT '0' NOT NULL");
            $this->exec("ALTER TABLE import_job_log ADD `row` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
            $this->exec("ALTER TABLE import_job_log DROP name");
            $this->exec("ALTER TABLE import_job_log DROP restore_data");
        }
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
