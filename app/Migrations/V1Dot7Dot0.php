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
            $this->exec("CREATE TABLE import_job_file (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, file_id VARCHAR(36) DEFAULT NULL, import_job_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_IMPORT_JOB_FILE_UNIQUE_RELATION ON import_job_file (deleted, file_id, import_job_id)");
            $this->exec("CREATE INDEX IDX_IMPORT_JOB_FILE_CREATED_BY_ID ON import_job_file (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_IMPORT_JOB_FILE_MODIFIED_BY_ID ON import_job_file (modified_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_IMPORT_JOB_FILE_FILE_ID ON import_job_file (file_id, deleted)");
            $this->exec("CREATE INDEX IDX_IMPORT_JOB_FILE_IMPORT_JOB_ID ON import_job_file (import_job_id, deleted)");
            $this->exec("CREATE INDEX IDX_IMPORT_JOB_FILE_CREATED_AT ON import_job_file (created_at, deleted)");
            $this->exec("CREATE INDEX IDX_IMPORT_JOB_FILE_MODIFIED_AT ON import_job_file (modified_at, deleted)");
        } else {
            $this->exec("DROP INDEX IDX_IMPORT_JOB_LOG_UNIQUE_JOB_LOG ON import_job_log");
            $this->exec("DROP INDEX IDX_IMPORT_JOB_LOG_MODIFIED_AT ON import_job_log");
            $this->exec("DROP INDEX IDX_IMPORT_JOB_LOG_NAME ON import_job_log");
            $this->exec("ALTER TABLE import_job_log ADD skipped_by_script TINYINT(1) DEFAULT '0' NOT NULL");
            $this->exec("ALTER TABLE import_job_log ADD `row` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
            $this->exec("ALTER TABLE import_job_log DROP name");
            $this->exec("ALTER TABLE import_job_log DROP restore_data");
            $this->exec("CREATE TABLE import_job_file (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, file_id VARCHAR(36) DEFAULT NULL, import_job_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_IMPORT_JOB_FILE_UNIQUE_RELATION (deleted, file_id, import_job_id), INDEX IDX_IMPORT_JOB_FILE_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_IMPORT_JOB_FILE_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_IMPORT_JOB_FILE_FILE_ID (file_id, deleted), INDEX IDX_IMPORT_JOB_FILE_IMPORT_JOB_ID (import_job_id, deleted), INDEX IDX_IMPORT_JOB_FILE_CREATED_AT (created_at, deleted), INDEX IDX_IMPORT_JOB_FILE_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
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
