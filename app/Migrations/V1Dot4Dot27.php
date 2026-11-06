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

class V1Dot4Dot27 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_configurator_item CHANGE replace_relation replace_array tinyint(1) default 0 not null");
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }
}
