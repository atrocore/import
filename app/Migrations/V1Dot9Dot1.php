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

class V1Dot9Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-22 09:00:00');
    }

    public function up(): void
    {
        try {
            $this->getConnection()->createQueryBuilder()
                ->update('import_configurator_item')
                ->set('entity_attribute_id', 'attribute_id')
                ->where('entity_attribute_id IS NULL AND attribute_id IS NOT NULL')
                ->executeStatement();
        } catch (\Throwable $e) {
        }

        $this->exec("ALTER TABLE import_configurator_item DROP type");
        $this->exec("ALTER TABLE import_configurator_item DROP attribute_value");
        $this->exec("ALTER TABLE import_configurator_item DROP attribute_id");
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
