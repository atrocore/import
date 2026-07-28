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

class V1Dot11Dot6 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-07-28 18:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX IDX_IMPORT_JOB_NAME ON pim.export_job");
            $this->exec("ALTER TABLE import_job DROP name");

            $this->exec("DROP SEQUENCE IF EXISTS import_job_number_seq");
            $this->exec("CREATE SEQUENCE import_job_number_seq INCREMENT BY 1 MINVALUE 1 START 1");
            $this->exec("ALTER TABLE import_job ADD number INT DEFAULT nextval('import_job_number_seq') NOT NULL");
        } else {
            $this->exec("DROP INDEX IDX_IMPORT_JOB_NAME ON import_job");
            $this->exec("ALTER TABLE import_job DROP name");

            $this->exec("ALTER TABLE import_job ADD number INT AUTO_INCREMENT NOT NULL, ADD UNIQUE INDEX UNIQ_6FB5407896901F54 (number)");
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