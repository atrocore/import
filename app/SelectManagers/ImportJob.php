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

use Espo\Core\SelectManagers\Base;

class ImportJob extends Base
{
    protected function boolFilterOnlyImportFailed24Hours(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedImportJobFilteredIds(1)
        ];
    }

    protected function boolFilterOnlyImportFailed7Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedImportJobFilteredIds(7)
        ];
    }

    protected function boolFilterOnlyImportFailed28Days(array &$result): void
    {
        $result['whereClause'][] = [
            'id' => $this->getFailedImportJobFilteredIds(28)
        ];
    }

    protected function getFailedImportJobFilteredIds(int $interval): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $res = $connection->createQueryBuilder()
            ->select('t.id')
            ->from($connection->quoteIdentifier('import_job'), 't')
            ->where('t.state = :state')
            ->andWhere('t.start >= :start')
            ->setParameter('state', 'Failed')
            ->setParameter('start', (new \DateTime())->modify("-{$interval} days")->format('Y-m-d H:i:s'))
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }
}
