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

namespace Import\Listeners;

use Atro\Listeners\AbstractLayoutListener;
use Atro\Core\EventManager\Event;

class Layout extends AbstractLayoutListener
{
    protected function modifyActionDetail(Event $event): void
    {
        $result = $event->getArgument('result');

        if (strpos(json_encode($result[0]['rows']), '"name":"importFeed"') === false) {
            $result[0]['rows'][] = [['name' => 'importFeed'], false];
        }

        if (strpos(json_encode($result[0]['rows']), '"name":"payload"') !== false) {
            $result[0]['rows'] = json_decode(str_replace(',[{"name":"payload","fullWidth":true}]', '', json_encode($result[0]['rows'])), true);
        }

        $result[0]['rows'][] = [['name' => 'payload', 'fullWidth' => true]];

        $event->setArgument('result',  $result);
    }

    protected function modifyScheduledJobDetail(Event $event): void
    {
        $result = $event->getArgument('result');

        $newRows = [];
        foreach ($result[0]['rows'] as $row) {
            $newRows[] = $row;
            if ($row[0]['name'] === 'type') {
                $newRows[] = [['name' => 'importFeed'], false];
                if(!$this->checkIfFieldExists('maximumHoursToLookBack', $result[0]['rows'])){
                    $newRows[] = [['name' => 'maximumHoursToLookBack'], false];
                }
                if (!$this->checkIfFieldExists('maximumDaysForJobExist', $result[0]['rows'])) {
                    $newRows[] = [['name' => 'maximumDaysForJobExist'], false];
                }
            }
        }

        $result[0]['rows'] = $newRows;

        $event->setArgument('result',  $result);
    }

    public function checkIfFieldExists(string $fieldName, array $array): bool
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($this->checkIfFieldExists($fieldName, $value)) {
                    return true;
                }
            } else if ($key === 'name' && $value === $fieldName) {
                return true;
            }
        }
        return false;
    }
}
