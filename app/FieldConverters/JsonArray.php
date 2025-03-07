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

use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

class JsonArray extends Varchar
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) || $config['default'] === 'null' ? null : $config['default'];

        if (!empty($default)) {
            $defaultArray = @json_decode((string)$default, true);
            if (!empty($defaultArray) && is_array($defaultArray)) {
                $default = $defaultArray;
            }
        }

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            $this->deletePAV($value, $config);
            if (strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $value = empty($default) ? [] : $default;
            }
            if (strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }
        } else {
            $value = $default;
        }

        if (is_string($value)) {
            $jsonValue = @json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonValue)) {
                $value = array_values($jsonValue);
            } else {
                $value = explode($config['delimiter'], $value);
            }
        }

        if (!empty($inputRow->{$config['name']}) && is_array($inputRow->{$config['name']}) && is_array($value)) {
            $value = array_merge($inputRow->{$config['name']}, $value);
        }

        $inputRow->{$config['name']} = $value;
        if (empty($config['replaceArray'])) {
            $inputRow->{$config['name'] . 'AddOnlyMode'} = 1;
        }
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->isAttributeChanged('default')) {
            $entity->set('default', empty($entity->get('default')) ? null : Json::encode($entity->get('default')));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $entity->set('default', !empty($entity->get('default')) ? Json::decode($entity->get('default'), true) : []);
    }
}
