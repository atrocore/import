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

use Atro\Core\Exceptions\BadRequest;
use Atro\Listeners\AbstractListener;
use Atro\Core\EventManager\Event;

class AttachmentController extends AbstractListener
{
    public function afterActionCreate(Event $event): void
    {
        $data = $event->getArgument('data');
        $result = $event->getArgument('result');

        $this->validateImportAttachment($data, $result);
    }

    public function afterActionCreateChunks(Event $event): void
    {
        $data = $event->getArgument('data');
        $result = $event->getArgument('result');

        if (!empty($result['attachment'])) {
            $this->validateImportAttachment($data, json_decode(json_encode($result['attachment'])));
        }
    }

    protected function validateImportAttachment($inputData, $attachment): void
    {
        if (empty($attachment) || !is_object($attachment)) {
            return;
        }

        if (!property_exists($attachment, 'relatedType') || $attachment->relatedType !== 'ImportFeed') {
            return;
        }

        if (!property_exists($attachment, 'field') || !in_array($attachment->field, ['importFile', 'file'])) {
            return;
        }

        if (property_exists($inputData, 'modelAttributes') && property_exists($inputData->modelAttributes, 'format')) {
            $method = "validate{$inputData->modelAttributes->format}File";
            $service = $this->getService('ImportFeed');
            if (method_exists($service, $method)) {
                try {
                    $service->$method($attachment->id);
                } catch (BadRequest $e) {
                    if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $attachment->id))) {
                        $this->getEntityManager()->removeEntity($attachment);
                    }
                    throw $e;
                }
            }
        }
    }
}
