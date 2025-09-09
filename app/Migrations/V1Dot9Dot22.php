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

class V1Dot9Dot22 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-09-09 12:00:00');
    }

    public function up(): void
    {
        $items = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id, import_by')
            ->from('import_configurator_item')
            ->where('import_by LIKE :search')
            ->andWhere('deleted = :false')
            ->setParameter('search', '%"sku"%')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($items as $item) {
            $importBy = @json_decode($item['import_by'], true);

            if (is_array($importBy)) {
                $key = array_search('sku', $importBy);

                if ($key !== false) {
                    $importBy[$key] = 'number';

                    try {
                        $this
                            ->getConnection()
                            ->createQueryBuilder()
                            ->update('import_configurator_item')
                            ->set('import_by', ':importBy')
                            ->where('id = :id')
                            ->setParameter('id', $item['id'])
                            ->setParameter('importBy', json_encode($importBy))
                            ->executeQuery();
                    } catch (\Throwable $e) {

                    }
                }
            }
        }
    }
}
