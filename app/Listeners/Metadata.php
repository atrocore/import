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

        if (empty($data['scopes']['Channel'])) {
            unset($data['entityDefs']['ImportConfiguratorItem']['fields']['channel']);
            unset($data['entityDefs']['ImportConfiguratorItem']['links']['channel']);
        }

        if (empty($data['scopes']['Attribute'])) {
            unset($data['entityDefs']['ImportConfiguratorItem']['fields']['attribute']);
            unset($data['entityDefs']['ImportConfiguratorItem']['links']['attribute']);
        }

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
            'type' => 'enum',
            'notStorable' => true,
            'filterDisabled' => true,
            'readOnly' => true,
            'optionsIds' => $data['entityDefs']['ImportJob']['fields']['state']['optionsIds'],
            'options' => $data['entityDefs']['ImportJob']['fields']['state']['options'],
            'optionColors' => $data['entityDefs']['ImportJob']['fields']['state']['optionColors']
        ];

        foreach ($this->getMemoryStorage()->get('dynamic_action') ?? [] as $action) {
            if ($action['type'] === 'import') {
                if ($action['usage'] === 'record' && !empty($action['source_entity'])) {
                    $data['clientDefs'][$action['source_entity']]['dynamicRecordActions'][] = [
                        'id'         => $action['id'],
                        'name'       => $action['name'],
                        'display'    => $action['display'],
                        'massAction' => !empty($action['mass_action']),
                        'acl'        => [
                            'scope'  => 'ImportFeed',
                            'action' => 'read',
                        ]
                    ];
                }
                if ($action['usage'] === 'entity' && !empty($action['source_entity'])) {
                    $data['clientDefs'][$action['source_entity']]['dynamicEntityActions'][] = [
                        'id'      => $action['id'],
                        'name'    => $action['name'],
                        'display' => $action['display'],
                        'massAction' => true,
                        'acl'     => [
                            'scope'  => 'ImportFeed',
                            'action' => 'read',
                        ]
                    ];
                }
            }
        }

        $data['clientDefs']['Action']['dynamicLogic']['fields']['sourceEntity']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['sourceEntity']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['sourceEntity']['visible']['conditionGroup'][0]['value'][] = 'import';

        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['clientDefs']['Action']['dynamicLogic']['fields']['payload']['visible']['conditionGroup'][0]['value'][] = 'import';

        $data['clientDefs']['Action']['dynamicLogic']['fields']['inBackground']['visible']['conditionGroup'][0]['value'][] = 'import';

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0] = [
                'type' => 'in',
                'attribute' => 'job',
                'value' => ['ImportFeed']
            ];
        } else {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumHoursToLookBack']['visible']['conditionGroup'][0]['value'][] = 'ImportFeed';
        }

        if (empty($data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible'])) {
            $data['clientDefs']['ScheduledJob']['dynamicLogic']['fields']['maximumDaysForJobExist']['visible']['conditionGroup'][0] = [
                'type' => 'in',
                'attribute' => 'job',
                'value' => ['ImportJobRemove']
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
