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

class V1Dot5Dot69 extends Base
{
    public function up(): void
    {
        $connection = $this->getConnection();

        $feeds = $connection
            ->createQueryBuilder()
            ->select('id, data')
            ->from($connection->quoteIdentifier('import_feed'))
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        if (!empty($feeds)) {
            foreach ($feeds as $feed) {
                $data = @json_decode($feed['data'], true);

                if (is_array($data) && array_key_exists('feedFields', $data) && !array_key_exists('skipValue', $data['feedFields'])) {
                    $data['feedFields']['skipValue'] = 'Skip';

                    $connection
                        ->createQueryBuilder()
                        ->update($connection->quoteIdentifier('import_feed'))
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('data', json_encode($data), ParameterType::STRING)
                        ->setParameter('id', $feed['id'], ParameterType::STRING)
                        ->executeStatement();
                }
            }
        }
    }

    public function down(): void
    {
    }
}
