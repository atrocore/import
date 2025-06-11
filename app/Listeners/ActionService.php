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

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractListener;
use Espo\ORM\Entity;

class ActionService extends AbstractListener
{
    public function prepareEntityForOutput(Event $event): void
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if (!empty($entity->get('importFeedId'))) {
            $importFeed = $this->getEntityManager()->getRepository('ImportFeed')->get($entity->get('importFeedId'));
            if (!empty($importFeed)) {
                $entity->set('importFeedName', $importFeed->get('name'));
            }
        }

        if (!is_bool($entity->get('uploadAndImport'))) {
            $entity->set('uploadAndImport', false);
        }
    }
}
