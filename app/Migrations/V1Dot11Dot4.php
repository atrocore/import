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

class V1Dot11Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-07-21 11:00:00');
    }

    public function up(): void
    {
        // Extend the V2Dot3Dot5 system_name rename to import_configurator_item.name.
        // Fix items where name starts with the attribute ID (the fallback prefix when code was empty).
        try {
            $items = $this->getDbal()->createQueryBuilder()
                ->select('i.id', 'i.name', 'i.entity_attribute_id', 'a.system_name')
                ->from('import_configurator_item', 'i')
                ->innerJoin('i', 'attribute', 'a', 'a.id = i.entity_attribute_id AND a.deleted = :false')
                ->where('i.deleted = :false')
                ->andWhere('a.system_name IS NOT NULL')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($items as $item) {
                if ($item['name'] === null) {
                    $newName = $item['system_name'];
                } elseif (str_starts_with($item['name'], $item['entity_attribute_id'])) {
                    $newName = $item['system_name'] . substr($item['name'], strlen($item['entity_attribute_id']));
                } else {
                    continue;
                }

                $this->getDbal()->createQueryBuilder()
                    ->update('import_configurator_item')
                    ->set('name', ':name')
                    ->where('id = :id')
                    ->setParameter('name', $newName)
                    ->setParameter('id', $item['id'])
                    ->executeStatement();
            }
        } catch (\Throwable $e) {
        }
    }
}
