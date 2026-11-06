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

namespace Import\Dashlets;

use Atro\Core\Dashlets\DashletInterface;
use Atro\Core\Utils\Language;
use Doctrine\DBAL\Connection;

/**
 * Counts the failed import jobs and their import feeds for the last day, week and four weeks.
 */
class DataSyncErrorsImport implements DashletInterface
{
    private const PERIODS = [
        'importErrorDuring24Hours' => 1,
        'importErrorDuring7Days'   => 7,
        'importErrorDuring28Days'  => 28,
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly Language $language
    ) {
    }

    public function getData(array $params): array
    {
        $list = [];
        foreach (self::PERIODS as $label => $interval) {
            $data = $this->countFailed($interval);
            $list[] = [
                'id'       => $this->language->translate($label),
                'name'     => $this->language->translate($label),
                'feeds'    => $data['feeds'],
                'jobs'     => $data['jobs'],
                'interval' => $interval
            ];
        }

        return ['total' => count($list), 'list' => $list];
    }

    private function countFailed(int $interval): array
    {
        $res = $this->connection->createQueryBuilder()
            ->select('COUNT(j.id) AS jobs, COUNT(DISTINCT f.id) AS feeds')
            ->from($this->connection->quoteIdentifier('import_feed'), 'f')
            ->innerJoin('f', $this->connection->quoteIdentifier('import_job'), 'j', 'j.import_feed_id = f.id')
            ->where('j.state = :state')
            ->andWhere('j.start >= :start')
            ->setParameter('state', 'Failed')
            ->setParameter('start', (new \DateTime())->modify("-{$interval} days")->format('Y-m-d H:i:s'))
            ->fetchAssociative();

        return empty($res) ? ['jobs' => 0, 'feeds' => 0] : $res;
    }
}
