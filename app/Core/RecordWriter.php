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

namespace Import\Core;

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;

/**
 * Creates and updates the imported records. A record of an entity with subtypes is written via its subtype record:
 * the subtype is defined by the type of the record.
 */
class RecordWriter
{
    private array $services = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function create(string $entityName, \stdClass $input): string
    {
        if (empty($this->container->get('metadata')->get(['scopes', $entityName, 'hasSubtypes']))) {
            return $this->getService($entityName)->createEntity($input);
        }

        $type = (string)($input->type ?? '');
        $subtype = $entityName . 'Type' . ucfirst($type);
        if ($type === '' || $this->container->get('metadata')->get(['scopes', $subtype, 'type']) !== 'Subtype') {
            throw new BadRequest(sprintf($this->container->get('language')->translate('noSuchType', 'exceptions', 'ImportFeed'), $type));
        }

        $subtypeId = $this->getService($subtype)->createEntity($input);

        return (string)$this->container->get('entityManager')->getRepository($subtype)->get($subtypeId)->get('supertypeId');
    }

    public function update(string $entityName, string $id, \stdClass $input): bool
    {
        $subtypeRecord = null;
        if (!empty($this->container->get('metadata')->get(['scopes', $entityName, 'hasSubtypes']))) {
            $subtypeRecord = $this->container->get('entityManager')->getRepository($entityName)->get($id)?->getSubtype();
        }

        if (empty($subtypeRecord)) {
            return $this->getService($entityName)->updateEntity($id, $input);
        }

        return $this->getService($subtypeRecord->getEntityName())->updateEntity($subtypeRecord->get('id'), $input);
    }

    protected function getService(string $name)
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->container->get('serviceFactory')->create($name);
        }

        return $this->services[$name];
    }
}
