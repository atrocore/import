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

use Espo\ORM\Entity;
use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

class JobEntity extends AbstractListener
{
    public function afterSave(Event $event): void
    {
        $entity = $event->getArgument('entity');
        if (!empty($entity->get('payload')->data->importJobId)) {
            $this->updateImportJobState($entity);
        }
    }

    public function afterRemove(Event $event): void
    {
        $entity = $event->getArgument('entity');
        if (!empty($entity->get('payload')->data->importJobId)) {
            $this->removeImportJob($entity);
        }
    }

    protected function updateImportJobState(Entity $entity): bool
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $entity->get('payload')->data->importJobId);
        if (empty($importJob)) {
            return false;
        }

        if ($importJob->get('state') !== $entity->get('status')) {
            $importJob->set('state', $entity->get('status'));
            $importJob->set('message', $entity->get('message'));
            $this->getEntityManager()->saveEntity($importJob);
        }

        return true;
    }

    protected function removeImportJob(Entity $entity): bool
    {
        $importJob = $this->getEntityManager()->getEntity('ImportJob', $entity->get('payload')->data->importJobId);
        if (empty($importJob)) {
            return false;
        }

        if ($entity->get('status') === 'Pending') {
            $this->getEntityManager()->removeEntity($importJob);
        }

        return true;
    }
}
