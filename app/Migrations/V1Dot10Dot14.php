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
use Atro\Core\Utils\RegexUtil;
use Doctrine\DBAL\ParameterType;

class V1Dot10Dot14 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-09 12:00:00');
    }

    public function up(): void
    {
        $chunkSize = 20000;
        $offset = 0;

        while (true) {
            $items = $this->getDbal()
                ->createQueryBuilder()
                ->select('id, value_extractor')
                ->from('import_configurator_item')
                ->where('value_extractor IS NOT NULL')
                ->andWhere('deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setFirstResult($offset)
                ->setMaxResults($chunkSize)
                ->fetchAllAssociative();

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $value = $item['value_extractor'];
                $stripped = RegexUtil::stripDelimiters($value);
                if ($stripped === $value) {
                    continue;
                }

                try {
                    $this->getDbal()
                        ->createQueryBuilder()
                        ->update('import_configurator_item')
                        ->set('value_extractor', ':value')
                        ->where('id = :id')
                        ->setParameter('value', $stripped)
                        ->setParameter('id', $item['id'])
                        ->executeStatement();
                } catch (\Throwable $e) {
                }
            }

            $offset += $chunkSize;
        }
    }
}
