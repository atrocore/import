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
use Doctrine\DBAL\ParameterType;

class V1Dot11Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-17 11:00:00');
    }

    public function up(): void
    {
        $this->getDbal()->createQueryBuilder()->update('import_configurator_item')
            ->set('import_by', ':importByName')
            ->where('import_by = :importByEmpty AND deleted = :false')
            ->setParameter('importByName', '["name"]')
            ->setParameter('importByEmpty', '[]')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
    }
}
