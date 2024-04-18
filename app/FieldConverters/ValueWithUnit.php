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

namespace Import\FieldConverters;

use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class ValueWithUnit extends Varchar
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = @json_decode($config['default'], true);
        if (!empty($default)) {
            $default = "{$default['value']} {$default['unitId']}";
        } else {
            $default = null;
        }

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];

            if (strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $value = $default;
            }
            if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }
        } else {
            $value = $default;
        }

        if ($value !== null) {
            $name = $config['name'];

            $parts = explode(" ", (string)$value);
            if (count($parts) >= 2) {
                $unitPart = $parts[count($parts) - 1];
                array_splice($parts, count($parts) - 1, 1);
                $floatPart = trim(join(" ", $parts));
            } else {
                $unitPart = "";
                $floatPart = $value;
            }


            $mainField = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $name, 'mainField']);
            $mainFieldType = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $mainField, 'type']);
            $measureId = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $mainField, 'measureId']);

            $this->getService('ImportConfiguratorItem')->getFieldConverter($mainFieldType)
                ->convert($inputRow, array_merge($config, ['name' => $mainField, 'column' => [$mainField]]), array_merge($row, [$mainField => $floatPart]));

            if (!empty($unitPart)) {
                // validate unit
                $unit = $this->getEntityManager()->getRepository('Unit')->where(['OR' => ['name' => $unitPart, 'id' => $unitPart], 'measureId' => $measureId])->findOne();
                if (empty($unit)) {
                    throw new BadRequest("Invalid unit value ($unitPart) for measure $measureId");
                }
                $inputRow->{$mainField . 'UnitId'} = $unit->get('id');
            } else {
                $inputRow->{$mainField . 'UnitId'} = null;
            }
        }
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if (strpos((string)$entity->getFetched('default'), '{') === false) {
            $data = [];
        } else {
            $data = @json_decode($entity->getFetched('default'), true);
        }

        if ($entity->has('defaultUnitId')) {
            $data['unitId'] = $entity->get('defaultUnitId');
        }
        if ($entity->has('default') && strpos((string)$entity->get('default'), '{') === false) {
            $data['value'] = $entity->get('default');
        }
        $entity->set('default', Json::encode($data));
    }

    public
    function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $data = Json::decode($entity->get('default'), true);
        if (!empty($data)) {
            $entity->set('default', $data['value']);
            $entity->set('defaultUnitId', $data['unitId']);
        }
    }
}
