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

namespace Import\SelectManagers;

use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;

class ImportFeed extends \Espo\Core\SelectManagers\Base
{
    protected function boolFilterOnlyImportFailed24Hours(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(1)
        ];
    }

    protected function boolFilterOnlyImportFailed7Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(7)
        ];
    }

    protected function boolFilterOnlyImportFailed28Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getImportFeedFilteredIds(28)
        ];
    }

    protected function getImportFeedFilteredIds(int $interval): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $res = $connection->createQueryBuilder()
            ->select('imp.id')
            ->from($connection->quoteIdentifier('import_feed'), 'imp')
            ->innerJoin('imp', $connection->quoteIdentifier('import_job'), 'imj', 'imj.import_feed_id = imp.id')
            ->where('imj.state = :state')
            ->andWhere('imj.start >= :start')
            ->setParameter('state', 'Failed')
            ->setParameter('start', (new \DateTime())->modify("-{$interval} days")->format('Y-m-d H:i:s'))
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }
}
