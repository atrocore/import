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

class V1Dot5Dot22 extends Base
{
    public function up(): void
    {
        $feeds = $this->getPDO()->query("SELECT * FROM import_feed WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($feeds as $feed) {
            $data = @json_decode($feed['data'], true);
            if (is_array($data) && isset($data['feedFields']['markForNotLinkedAttribute'])) {
                $data['feedFields']['markForNoRelation'] = $data['feedFields']['markForNotLinkedAttribute'];
                unset($data['feedFields']['markForNotLinkedAttribute']);
                $newData = $this->getPDO()->quote(json_encode($data));
                $this->getPDO()->exec("UPDATE import_feed SET data=$newData WHERE id='{$feed['id']}'");
            }
        }
    }

    public function down(): void
    {
        $feeds = $this->getPDO()->query("SELECT * FROM import_feed WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($feeds as $feed) {
            $data = @json_decode($feed['data'], true);
            if (is_array($data) && isset($data['feedFields']['markForNoRelation'])) {
                $data['feedFields']['markForNotLinkedAttribute'] = $data['feedFields']['markForNoRelation'];
                unset($data['feedFields']['markForNoRelation']);
                $newData = $this->getPDO()->quote(json_encode($data));
                $this->getPDO()->exec("UPDATE import_feed SET data=$newData WHERE id='{$feed['id']}'");
            }
        }
    }
}
