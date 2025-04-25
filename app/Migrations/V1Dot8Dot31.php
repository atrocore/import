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

class V1Dot8Dot31 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-04-25 09:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE import_configurator_item ADD entity_attribute_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_IMPORT_CONFIGURATOR_ITEM_ENTITY_ATTRIBUTE_ID ON import_configurator_item (entity_attribute_id, deleted)");
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
