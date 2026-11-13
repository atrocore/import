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

class V1Dot2Dot4 extends \Atro\Core\Migration\Base
{
    public function up(): void
    {
        try {
            $this->getPDO()->exec("ALTER TABLE `import_feed` ADD jobs_max INT DEFAULT '10' COLLATE utf8mb4_unicode_ci");
            $this->getPDO()->exec("UPDATE `import_feed` SET `jobs_max`=10 WHERE 1");
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $this->getPDO()->exec("ALTER TABLE `scheduled_job` ADD import_feed_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
            $this->getPDO()->exec("CREATE INDEX IDX_IMPORT_FEED_ID ON `scheduled_job` (import_feed_id)");
        } catch (\Throwable $e) {
            // ignore
        }

        $container = (new \Atro\Core\Application())->getContainer();
        $em = $container->get('entityManager');

        $container->get(\Atro\Core\Authentication\CurrentUser::class)->setSystemUser();

        foreach ($em->getRepository('ImportFeed')->where(['type' => 'simple'])->find() as $feed) {
            $attachment = $feed->get('file');
            if (empty($attachment)) {
                continue;
            }

            $delimiter = (!empty($feed->getFeedField('fileFieldDelimiter'))) ? $feed->getFeedField('fileFieldDelimiter') : ';';
            $enclosure = ($feed->getFeedField('fileTextQualifier') == 'singleQuote') ? "'" : '"';
            $isFileHeaderRow = (is_null($feed->getFeedField('isHeaderRow'))) ? true : !empty($feed->getFeedField('isHeaderRow'));

            try {
                $sourceFields = $container->get('serviceFactory')->create('CsvFileParser')->getFileColumns($attachment, $delimiter, $enclosure, $isFileHeaderRow);
                $feed->set('sourceFields', $sourceFields);
                $em->saveEntity($feed);

                $items = $feed->get('configuratorItems');
                if (empty($items) || count($items) == 0) {
                    continue;
                }

                foreach ($items as $item) {
                    if (!empty($columns = $item->get('column'))) {
                        $newColumns = [];
                        foreach ($columns as $column) {
                            if (isset($sourceFields[$column])) {
                                $newColumns[] = $sourceFields[$column];
                            }
                        }
                        $item->set('column', $newColumns);
                        $em->saveEntity($item);
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down(): void
    {
        $this->getPDO()->exec("DELETE FROM `import_feed` WHERE 1");
        $this->getPDO()->exec("DELETE FROM `import_configurator_item` WHERE 1");
    }
}
