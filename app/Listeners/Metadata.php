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
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Listeners\AbstractListener;

class Metadata extends AbstractListener
{
    public function modify(Event $event)
    {
        $data = $event->getArgument('data');

        foreach ($data['entityDefs'] as $scope => $scopeData) {
            if (empty($scopeData['fields'])) {
                continue;
            }

            $data['entityDefs'][$scope]['fields']['filterCreateImportJob'] = [
                'type'                      => 'enum',
                'notStorable'               => true,
                'view'                      => 'import:views/fields/filter-import-job',
                'scope'                     => $scope,
                'layoutDetailDisabled'      => true,
                'layoutDetailSmallDisabled' => true,
                'layoutListDisabled'        => true,
                'layoutListSmallDisabled'   => true,
                'layoutMassUpdateDisabled'  => true,
                'exportDisabled'            => true,
                'importDisabled'            => true,
                'textFilterDisabled'        => true,
                'emHidden'                  => true,
            ];

            $data['entityDefs'][$scope]['fields']['filterUpdateImportJob'] = [
                'type'                      => 'enum',
                'notStorable'               => true,
                'view'                      => 'import:views/fields/filter-import-job',
                'scope'                     => $scope,
                'layoutDetailDisabled'      => true,
                'layoutDetailSmallDisabled' => true,
                'layoutListDisabled'        => true,
                'layoutListSmallDisabled'   => true,
                'layoutMassUpdateDisabled'  => true,
                'exportDisabled'            => true,
                'importDisabled'            => true,
                'textFilterDisabled'        => true,
                'emHidden'                  => true,
            ];
        }

        if (!empty($data['clientDefs']['ImportFeed']['relationshipPanels']['configuratorItems'])) {
            $data['clientDefs']['ImportFeed']['relationshipPanels']['configuratorItems']['dragDrop']['maxSize'] = $this->getConfig()->get('recordsPerPageSmall', 20);
        }

        $data['entityDefs']['ImportFeed']['fields']['lastStatus'] = [
            'type'           => 'enum',
            'notStorable'    => true,
            'filterDisabled' => true,
            'readOnly'       => true,
            'optionsIds'     => $data['entityDefs']['ImportJob']['fields']['state']['optionsIds'],
            'options'        => $data['entityDefs']['ImportJob']['fields']['state']['options'],
            'optionColors'   => $data['entityDefs']['ImportJob']['fields']['state']['optionColors']
        ];

        foreach ($this->getMemoryStorage()->get('dynamic_action') ?? [] as $action) {
            if ($action['type'] === 'import' && !empty($action['source_entity']) && !empty($action['usage'])) {
                $params = [
                    'acl' => [
                        'scope'  => 'ImportFeed',
                        'action' => 'read',
                    ]
                ];

                $defsKey = "dynamic" . ucfirst($action['usage']) . "Actions";

                foreach ($data['clientDefs'][$action['source_entity']][$defsKey] ?? [] as $key => $recordAction) {
                    if ($recordAction['id'] === $action['id']) {
                        $data['clientDefs'][$action['source_entity']][$defsKey][$key] = array_merge($recordAction, $params);
                        break;
                    }
                }
            }
        }

        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['value'][] = 'import';

        $data['clientDefs']['Action']['dynamicLogic']['fields']['inBackground']['visible']['conditionGroup'][0]['value'][] = 'import';

        $data['clientDefs']['Action']['dynamicLogic']['fields']['executeAs']['visible']['conditionGroup'][0]['value'][] = 'import';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['executeAs']['required']['conditionGroup'][0]['value'][] = 'import';

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ImportFeed']
            ];
        } else {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0]['value'][] = 'ImportFeed';
        }

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible'])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ImportJobRemove']
            ];
        } else {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible']['conditionGroup'][0]['value'][] = 'ImportJobRemove';
        }


        $data['entityDefs']['ExtensibleEnumOption']['fields']['extensibleEnumId'] = [
            'type'                      => 'varchar',
            'notStorable'               => true,
            'layoutDetailDisabled'      => true,
            'layoutDetailSmallDisabled' => true,
            'layoutListDisabled'        => true,
            'layoutListSmallDisabled'   => true,
            'layoutMassUpdateDisabled'  => true,
            'exportDisabled'            => true,
            'importDisabled'            => true,
            'textFilterDisabled'        => true,
            'emHidden'                  => true,
        ];

        $event->setArgument('data', $data);
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->getContainer()->get('memoryStorage');
    }
}
