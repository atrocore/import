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

class V1Dot4Dot28 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE import_feed ADD source_fields LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");
        $feeds = $this->getPDO()->query("SELECT * FROM import_feed WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($feeds as $feed) {
            $data = @json_decode($feed['data'], true);
            if (is_array($data) && !empty($data['feedFields']['allColumns'])) {
                $sourceFields = $this->getPDO()->quote(json_encode($data['feedFields']['allColumns']));
                $this->getPDO()->exec("UPDATE import_feed SET source_fields=$sourceFields WHERE id='{$feed['id']}'");
            }
        }
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }
}
