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

namespace Import\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Exceptions\Error;

class V1Dot5Dot60 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        // add column key if needed
        if (!array_key_exists('maximum_days_for_job_exist', $fromSchema->getTable('scheduled_job')->getColumns())) {
            $this->addColumn($toSchema, 'scheduled_job', 'maximum_days_for_job_exist', ['type' => 'int', 'min' => 0]);

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->getPDO()->exec($sql);
            }
        }

        // remove unnecessary jobs
        $this
            ->getConnection()
            ->createQueryBuilder()
            ->delete('job')
            ->where('name = :job')
            ->setParameter('job', 'Delete Import Jobs')
            ->executeQuery();

        // remove unnecessary key from settings
        $this->getConfig()->remove('importJobsMaxDays');
        $this->getConfig()->save();
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}