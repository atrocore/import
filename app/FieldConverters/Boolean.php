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
use Espo\ORM\Entity;

class Boolean extends Varchar
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $default = empty($config['default']) ? null : $config['default'];

        if(in_array($default, ['TRUE', 'FALSE'])){
            $default = $default === 'TRUE';
        }

        if (isset($config['column'][0]) && isset($row[$config['column'][0]])) {
            $value = $row[$config['column'][0]];
            if (!is_bool($value) && strtolower((string)$value) === strtolower((string)$config['emptyValue']) || $value === '') {
                $value = $default;
            }
            if (!is_bool($value) && strtolower((string)$value) === strtolower((string)$config['nullValue'])) {
                $value = null;
            }
        } else {
            $value = $default;
        }

        if (is_string($value) && (strtolower($value) === 'no' || strtolower($value) === 'false' || $value === '0' || $value === 'f')) {
            $value = false;
        }

        if (is_string($value) && (strtolower($value) === 'yes' || strtolower($value) === 'true' || $value === '1' || $value === 't')) {
            $value = true;
        }

        if ($value === null) {
            $inputRow->{$config['name']} = null;
            return;
        }

        if (is_null(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
            $type = $this->translate('bool', 'fieldTypes', 'Admin');
            throw new BadRequest(sprintf($this->translate('unexpectedFieldType', 'exceptions', 'ImportFeed'), $value, $type));
        }

        $inputRow->{$config['name']} = !empty($value);
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        $importFeed = $entity->get('importFeed');
        $notNull =   $this->getMetadata()->get(['entityDefs', $importFeed->getFeedField('entity'), 'fields', $entity->get('name'), 'notNull'],  true);
        if(!$notNull && $entity->get('default') === null){
            return;
        }
        $entity->set('default', !empty($entity->get('default')) === true ? "TRUE" : "FALSE");
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        if($entity->get('default') === null){
            return;
        }

        if(in_array($entity->get('default'), ['FALSE','TRUE'])){
            $entity->set('default', $entity->get('default') === 'TRUE');
            return;
        }

        $entity->set('default', !empty($entity->get('default')));
    }
}
