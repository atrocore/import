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

namespace Import\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ImportJobLog extends Base
{
    protected bool $cacheable = false;

    protected array $cachedImportJobs = [];

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->createParentJobLog($entity, $options);
    }

    public function createParentJobLog(Entity $entity, array $options): void
    {
        if (!empty($options['skipParentLog'])) {
            return;
        }

        if (empty($entity->get('importJobId'))) {
            return;
        }

        $importJob = $this->getCachedImportJob($entity->get('importJobId'));
        if (empty($importJob->get('parentId'))) {
            return;
        }

        $parentJob = $this->getCachedImportJob($importJob->get('parentId'));
        if (empty($parentJob)) {
            return;
        }

        $input = $this->getMemoryStorage()->get("import_job_{$importJob->get('id')}_input");

        $rowNumberPart = $this->getMemoryStorage()->get("import_job_{$importJob->get('id')}_rowNumberPart") ?? 0;
        $rowNumber = $rowNumberPart + $entity->get('rowNumber');

        if ($parentJob->get('entityName') === $entity->get('entityName')) {
            $parentLog = $this->getEntityManager()->getEntity('ImportJobLog');
            $parentLog->set('entityName', $entity->get('entityName'));
            $parentLog->set('entityId', $entity->get('entityId'));
            $parentLog->set('importJobId', $importJob->get('parentId'));
            $parentLog->set('type', $entity->get('type'));
            $parentLog->set('skippedByScript', $entity->get('skippedByScript'));
            $parentLog->set('rowNumber', $rowNumber);
            $parentLog->set('row', $entity->get('row'));
            $parentLog->set('message', $entity->get('message'));
            try {
                $this->getEntityManager()->saveEntity($parentLog, ['skipParentLog' => true]);
            } catch (\Throwable $e) {
                // ignore
            }
        } else {
            if ($entity->get('entityName') === 'ProductAttributeValue') {
                $type = $entity->get('type');
                switch ($type) {
                    case 'create':
                    case 'delete':
                        $type = 'update';
                        break;
                    case 'skip':
                        return;
                }

                if (!property_exists($input, 'productId')) {
                    return;
                }

                $parentLog = $this->getEntityManager()->getEntity('ImportJobLog');
                $parentLog->set('entityName', 'Product');
                $parentLog->set('entityId', $input->productId);
                $parentLog->set('importJobId', $importJob->get('parentId'));
                $parentLog->set('type', $type);
                $parentLog->set('skippedByScript', $entity->get('skippedByScript'));
                $parentLog->set('rowNumber', $rowNumber);
                $parentLog->set('row', $entity->get('row'));
                $parentLog->set('message', $entity->get('message'));
                try {
                    $this->getEntityManager()->saveEntity($parentLog, ['skipParentLog' => true]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }

    protected function getCachedImportJob(string $importJobId): ?Entity
    {
        if (!isset($this->cachedImportJobs[$importJobId])) {
            $this->cachedImportJobs[$importJobId] = $this->getEntityManager()->getRepository('ImportJob')->get($importJobId);
        }

        return $this->cachedImportJobs[$importJobId];
    }
}
