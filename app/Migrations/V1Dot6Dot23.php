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

class V1Dot6Dot23 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->getPDO()->exec("ALTER TABLE import_configurator_item ADD url_headers TEXT DEFAULT NULL");
            $this->getPDO()->exec("COMMENT ON COLUMN import_configurator_item.url_headers IS '(DC2Type:jsonObject)'");
        } else {
            $this->getPDO()->exec("ALTER TABLE import_configurator_item ADD url_headers LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
        }
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_configurator_item DROP url_headers");
    }
}
