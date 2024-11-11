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
use Doctrine\DBAL\ParameterType;

class V1Dot6Dot45 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            // DROP INDEX idx_import_job_log_row_number;
            //DROP INDEX idx_import_job_log_unique_job_log;
            //ALTER TABLE import_job_log ADD row TEXT DEFAULT NULL;
            //ALTER TABLE import_job_log DROP row_number;
            //COMMENT ON COLUMN import_job_log.row IS '(DC2Type:jsonObject)'
            // DROP INDEX idx_import_job_log_modified_at;
            //DROP INDEX idx_import_job_log_name
            // ALTER TABLE import_job_log ADD skipped_by_script BOOLEAN DEFAULT 'false' NOT NULL
        } else {
            // DROP INDEX IDX_IMPORT_JOB_LOG_UNIQUE_JOB_LOG ON import_job_log;
            //DROP INDEX IDX_IMPORT_JOB_LOG_MODIFIED_AT ON import_job_log;
            //DROP INDEX IDX_IMPORT_JOB_LOG_NAME ON import_job_log;
            //DROP INDEX IDX_IMPORT_JOB_LOG_ROW_NUMBER ON import_job_log;
            //ALTER TABLE import_job_log ADD skipped_by_script TINYINT(1) DEFAULT '0' NOT NULL, ADD `row` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)', DROP `row_number`
        }
    }

    public function down(): void
    {
    }
}
