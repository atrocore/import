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

class ExtensibleMultiEnum extends LinkMultiple
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $config['replaceArray'] = true;
        if (empty($config['importBy'])) {
            $config['importBy'] = ['name'];
        }

        parent::convert($inputRow, $config, $row);
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $fieldName = $this->getFieldName($item);
        $restore->$fieldName = $entity->get($fieldName);
    }

    protected function convertItem(array $config, array $column, array $row): ?string
    {
        $input = new \stdClass();
        $this
            ->getService('ImportConfiguratorItem')
            ->getFieldConverter('extensibleEnum')
            ->convert($input, array_merge($config, $column, ['default' => null]), $row);

        $key = $config['entity'] === 'ProductAttributeValue' && $config['name'] == 'value' ? 'referenceValue' : $config['name'];
        if (property_exists($input, $key) && $input->$key !== null) {
            return $input->$key;
        }

        return null;
    }

    protected function getFieldName(array $config): string
    {
        return $config['name'];
    }

    protected function getForeignEntityName(array $config): string
    {
        return 'ExtensibleEnumOption';
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->has('default')) {
            $entity->set('default', !is_array($entity->get('default')) ? null : json_encode($entity->get('default')));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $default = @json_decode((string)$entity->get('default'), true);
        if (!is_array($default)) {
            $default = null;
        }

        $entity->set('default', $default);
        $entity->set('defaultNames', null);

        if (!empty($entity->get('default'))) {
            $names = [];
            $options = $this->getEntityManager()->getRepository('ExtensibleEnumOption')
                ->select(['id', 'name'])
                ->where(['id' => $entity->get('default')])
                ->find();
            foreach ($options as $option) {
                $names[$option->get('id')] = $option->get('name');
            }
            $entity->set('defaultNames', $names);
        }
    }
}
