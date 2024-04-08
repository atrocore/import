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

class ValueWithUnit extends Varchar
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? null : $config['default'];

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

            $parts = explode(" ", $value);
            $unitPart = $parts[count($parts) - 1];
            array_splice($parts, count($parts) - 1, 1);
            $floatPart = trim(join($parts, " "));


            $mainField = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $name, 'mainField']);
            $mainUnitField = $mainField . 'Unit';
            $mainFieldType = $this->getMetadata()->get(['entityDefs', $config['entity'], 'fields', $mainField, 'type']);

            $this->getService('ImportConfiguratorItem')->getFieldConverter($mainFieldType)
                ->convert($inputRow, array_merge($config, ['column' => [$mainField]]), array_merge($row, [$mainField => $floatPart]));

            $this->getService('ImportConfiguratorItem')->getFieldConverter('link')
                ->convert($inputRow, array_merge($config, ['column' => [$mainUnitField], 'importBy' => ['id']]), array_merge($row, [$mainUnitField => $unitPart]));
        }
    }
}
