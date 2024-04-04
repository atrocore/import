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

use Espo\ORM\Entity;

class File extends Link
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        $config['relEntityName'] = 'File';

        if (isset($config['attributeId'])) {
            $config['importBy'] = ['url'];
        }

        if ($config['importBy'] === ['url']) {
            $config['createIfNotExist'] = true;
        }

        parent::convert($inputRow, $config, $row);
    }

    public function prepareValue(\stdClass $restore, Entity $entity, array $item): void
    {
        $value = null;

        if (!empty($foreign = $entity->get($item['name']))) {
            $value = is_string($foreign) ? $foreign : $foreign->get('id');
        }

        $restore->{$item['name'] . 'Id'} = $value;
    }

    public function prepareFindExistEntityWhere(array &$where, array $configuration, array $row): void
    {
    }

    public function prepareForSaveConfiguratorDefaultField(Entity $entity): void
    {
        if ($entity->has('defaultId')) {
            $entity->set('default', empty($entity->get('defaultId')) ? null : $entity->get('defaultId'));
        }
    }

    public function prepareForOutputConfiguratorDefaultField(Entity $entity): void
    {
        $entity->set('defaultId', null);
        $entity->set('defaultName', null);
        $entity->set('defaultPathsData', null);

        if (!empty($entity->get('default'))) {
            /** @var \Atro\Entities\File $file */
            $file = $this->getEntityManager()->getEntity('File', $entity->get('defaultId'));

            $entity->set('defaultId', $entity->get('default'));
            $entity->set('defaultName', empty($file) ? $entity->get('defaultId') : $file->get('name'));
            $entity->set('defaultPathsData', $file->getPathsData());
        }
    }
}
