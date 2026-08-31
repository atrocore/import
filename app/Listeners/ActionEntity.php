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

use Atro\Listeners\AbstractListener;
use Espo\Core\EventManager\Event;

class ActionEntity extends AbstractListener
{
    public function beforeSave(Event $event): void
    {
        $entity = $event->getArgument('entity');

        if ($entity->get('type') === 'reImportFailedJob') {
            $entity->set('targetEntity', 'ImportJob');
            $entity->set('searchEntity', 'ImportJob');
            $entity->set('sourceEntity', 'ImportJob');
        }
    }
}
