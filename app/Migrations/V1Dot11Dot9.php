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

class V1Dot11Dot9 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-08 12:00:00');
    }

    public function up(): void
    {
        $importFeeds = $this->getDbal()->createQueryBuilder()
            ->select('id', 'data')
            ->from('import_feed')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($importFeeds as $importFeed) {
            $data = @json_decode((string)$importFeed['data'], true);
            if (!is_array($data)) {
                $data = [];
            }

            // Already migrated (or never had the old flag) - skip, so re-running this migration
            // never stomps a headerRowNumber/dataStartRowNumber value set since.
            if (!array_key_exists('feedFields', $data) || !array_key_exists('isFileHeaderRow', $data['feedFields'])) {
                continue;
            }

            $wasHeaderRowEnabled = $data['feedFields']['isFileHeaderRow'];
            unset($data['feedFields']['isFileHeaderRow']);

            $data['feedFields']['headerRowNumber'] = $wasHeaderRowEnabled ? 1 : 0;
            $data['feedFields']['dataStartRowNumber'] = $wasHeaderRowEnabled ? 2 : 1;

            $this->getDbal()->createQueryBuilder()
                ->update('import_feed')
                ->set('data', ':data')
                ->where('id = :id')
                ->setParameter('data', json_encode($data))
                ->setParameter('id', $importFeed['id'])
                ->executeQuery();
        }
    }
}
