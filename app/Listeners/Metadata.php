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
use Atro\Core\Utils\Util;
use Atro\Listeners\AbstractMetadataListener;
use Import\Console\CreateImportProcessingType;
use Import\ProcessingTypes\AbstractProcessingType;

class Metadata extends AbstractMetadataListener
{
    public function modify(Event $event)
    {
        $data = $event->getArgument('data');

        if (!empty($data['scopes']['Attribute'])) {
            $data['entityDefs']['ImportConfiguratorItem']['fields']['entityAttribute'] = [
                'type' => 'link'
            ];
            $data['entityDefs']['ImportConfiguratorItem']['links']['entityAttribute'] = [
                'type'   => 'belongsTo',
                'entity' => 'Attribute'
            ];
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
                'openApiDisabled'           => true
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
                'openApiDisabled'           => true
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

                if ($action['usage'] === 'entity') {
                    $actionData = @json_decode($action['data'], true);
                    if (!empty($actionData['field']['uploadAndImport'])) {
                        $params['type'] = 'uploadAndImport';
                        $params['importFeedId'] = $action['importFeedId'] ?? null;
                    }
                }

                $defsKey = "dynamic" . ucfirst($action['usage']) . "Actions";

                foreach ($data['clientDefs'][$action['source_entity']][$defsKey] ?? [] as $key => $recordAction) {
                    if ($recordAction['id'] === $action['id']) {
                        $data['clientDefs'][$action['source_entity']][$defsKey][$key] = array_merge($recordAction, $params);
                        break;
                    }
                }
            }
        }

        $data['entityDefs']['Action']['fields']['payload']['conditionalProperties']['visible']['conditionGroup'][0]['type'] = 'in';
        $data['entityDefs']['Action']['fields']['payload']['conditionalProperties']['visible']['conditionGroup'][0]['attribute'] = 'type';
        $data['entityDefs']['Action']['fields']['payload']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'import';

        if (empty($data['entityDefs']['ScheduledJob']['fields']['maximumHoursToLookBack']['conditionalProperties']['visible']['conditionGroup'][0])) {
            $data['entityDefs']['ScheduledJob']['fields']['maximumHoursToLookBack']['conditionalProperties']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ImportFeed']
            ];
        } else {
            $data['entityDefs']['ScheduledJob']['fields']['maximumHoursToLookBack']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'ImportFeed';
        }

        if (empty($data['entityDefs']['ScheduledJob']['fields']['maximumDaysForJobExist']['conditionalProperties']['visible'])) {
            $data['entityDefs']['ScheduledJob']['fields']['maximumDaysForJobExist']['conditionalProperties']['visible']['conditionGroup'][0] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['ImportJobRemove']
            ];
        } else {
            $data['entityDefs']['ScheduledJob']['fields']['maximumDaysForJobExist']['conditionalProperties']['visible']['conditionGroup'][0]['value'][] = 'ImportJobRemove';
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

        foreach (Util::scanDir(CreateImportProcessingType::DIR) as $fileName) {
            $type = str_replace('.php', '', $fileName);

            $className = "\\ImportProcessingTypes\\$type";
            if (is_a($className, AbstractProcessingType::class, true)) {
                $data['app']['processingTypes'][$type] = [
                    'label'       => $className::getTypeLabel(),
                    'description' => $className::getDescription(),
                    'entityName'  => $className::getEntityName(),
                    'className'   => $className,
                ];
            }
        }

        $event->setArgument('data', $data);
    }
}
