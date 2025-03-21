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

class ImportJobLog extends Base
{
    protected function access(&$result)
    {
        if ($this->getUser()->isAdmin()) {
            return;
        }

        $repository = $this->getEntityManager()->getRepository('ImportJob');

        $sp = $this->createSelectManager('ImportJob')->getSelectParams([], true, true);
        $sp['select'] = ['id'];

        $qb = $repository->getMapper()->createSelectQueryBuilder($repository->get(), $sp);

        $mainTableAlias = $this->getRepository()->getMapper()->getQueryConverter()->getMainTableAlias();
        $innerSql = str_replace($mainTableAlias, "t_ij", $qb->getSql());

        $where = [
            'innerSql' => [
                "sql"        => "$mainTableAlias.import_job_id IN ({$innerSql})",
                "parameters" => $qb->getParameters()
            ]
        ];

        $result['whereClause'][] = ['OR' => $where];
    }
}
