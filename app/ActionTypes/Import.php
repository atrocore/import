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

namespace Import\ActionTypes;

use Atro\ActionTypes\AbstractAction;
use Atro\Core\EventManager\Event;
use Espo\ORM\Entity;

class Import extends AbstractAction
{
    public function executeViaWorkflow(array $workflowData, Event $event): bool
    {
        $action = $this->getActionById($workflowData['id']);
        $action->set('sourceEntity', $event->getArgument('entity')->getEntityType());

        $input = new \stdClass();
        $input->entityId = $event->getArgument('entity')->get('id');

        if (!empty($workflow['_relationData'])) {
            $input->_relationData = $workflow['_relationData'];
        }

        return $this->executeNow($action, $input);
    }

    public function execute(Entity $action, \stdClass $input): bool
    {
        $payload = empty($action->get('payload')) ? '' : (string)$action->get('payload');
        $templateData = [];

        if (!empty($action->get('sourceEntity'))) {
            $service = $this->getServiceFactory()->create($action->get('sourceEntity'));
            if (property_exists($input, 'entityId')) {
                $params = [
                    'disableCount' => true,
                    'where'        => [['type' => 'in', 'attribute' => 'id', 'value' => [$input->entityId]]],
                    'offset'       => 0,
                    'maxSize'      => 1,
                    'sortBy'       => 'createdAt',
                    'asc'          => true
                ];
            }

            if (property_exists($input, 'where')) {
                $params = [
                    'disableCount' => true,
                    'where'        => json_decode(json_encode($input->where), true),
                    'offset'       => 0,
                    'maxSize'      => 60000,
                    'sortBy'       => 'createdAt',
                    'asc'          => true
                ];
            }

            if (!empty($params)) {
                $res = $service->findEntities($params);
                if (!empty($res['collection'][0])) {
                    $templateData['sourceEntities'] = $res['collection'];
                    $templateData['sourceEntitiesIds'] = array_column($res['collection']->toArray(), 'id');

                    $templateData['entity'] = $res['collection'][0]; // for backward compatibility
                }
            }
        }

        $payload = $this->getTwig()->renderTemplate($payload, $templateData);
        $payload = @json_decode((string)$payload, true);

        if (!empty($input->_relationData)) {
            $payload['relation'] = [
                'action'       => $input->_relationData['action'],
                'relationName' => $input->_relationData['relationName'],
                'foreignId'    => $input->_relationData['foreignId']
            ];
        }

        /** @var \Import\Services\ImportFeed $service */
        $service = $this->getServiceFactory()->create('ImportFeed');

        $importFeed = $service->getEntity($action->get('importFeedId'));
        if (empty($importFeed) || empty($importFeed->get('isActive'))) {
            return false;
        }

        $payload['executeNow'] = empty($action->get('inBackground'));
        if (property_exists($input, 'actionSetLinkerId')) {
            $payload['actionSetLinkerId'] = $input->actionSetLinkerId;

            if (property_exists($input, 'where')) {
                $payload['where'] = json_decode(json_encode($input->where), true);
            } elseif (property_exists($input, 'entityId')) {
                $payload['where'] = [['type' => 'in', 'attribute' => 'id', 'value' => [$input->entityId]]];
            }
        }

        $service->runImport($importFeed->get('id'), '', empty($payload) ? null : json_decode(json_encode($payload)));

        return true;
    }

}