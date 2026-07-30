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

namespace Import\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Import\Jobs\ImportTypeSimple;

class ImportJob extends Base
{
    protected $mandatorySelectAttributeList = [
        'message',
        'attachmentId',
        'attachmentName',
        'convertedFileId',
        'convertedFileName',
        'errorsAttachmentId',
        'errorsAttachmentName'
    ];

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $importFeed = $entity->get('importFeed');
        if (!empty($importFeed)) {
            $entity->set('processingType', $importFeed->get('processingType'));
        }
    }

    public function prepareCounts(EntityCollection $collection): void
    {
        $data = $this->getRepository()->getJobsCounts(array_column($collection->toArray(), 'id'));

        foreach ($collection as $entity) {
            $entity->set('createdCount', $data[$entity->get('id')]['created_count'] ?? 0);
            $entity->set('updatedCount', $data[$entity->get('id')]['updated_count'] ?? 0);
            $entity->set('deletedCount', $data[$entity->get('id')]['deleted_count'] ?? 0);
            $entity->set('skippedCount', $data[$entity->get('id')]['skipped_count'] ?? 0);
            $entity->set('errorsCount', $data[$entity->get('id')]['errors_count'] ?? 0);
        }
    }

    public function generateFile(string $id, string $type): string
    {
        if (!$this->getAcl()->check('ImportJob', 'read')) {
            throw new Forbidden();
        }

        $type = $type === 'convertedFile' ? 'converted' : $type;
        $name = $this->getInjection('container')->get('language')->translate('generateFile' . ucfirst($type), 'labels', 'ImportJob');

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'    => $name,
            'type'    => 'ConvertedFileGenerator',
            'payload' => [
                'type'        => $type,
                'importJobId' => $id,
            ],
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        return $jobEntity->get('id');
    }

    public function getRecordCounters(string $id): array
    {
        if (!$this->getAcl()->check('ImportJob', 'read')) {
            throw new Forbidden();
        }

        $importJob = $this->getEntityManager()->getEntity('ImportJob', $id);
        if (empty($importJob)) {
            throw new NotFound();
        }

        $result = ['id' => $importJob->id, 'state' => $importJob->get('state')];
        $fields = ['createdCount', 'updatedCount', 'deletedCount', 'skippedCount', 'errorsCount'];

        foreach ($fields as $field) {
            if ($importJob->get($field) === null) {
                $this->prepareCounts(new EntityCollection([$importJob]));
            }
            $result[$field] = $importJob->get($field) ?? 0;
        }

        if (!in_array($importJob->get('state'), ['Pending', 'Running'])) {
            $this->getEntityManager()->saveEntity($importJob);
        }

        return $result;
    }

    public function reCreateImportJob(string $id, ?string $attachmentId = null): bool
    {
        if (!$this->getAcl()->check('ImportJob', 'create')) {
            throw new Forbidden();
        }

        $job = $this->getEntity($id);
        if (empty($job)) {
            throw new NotFound();
        }

        if ($job->get('state') !== 'Success') {
            throw new \Atro\Core\Exceptions\BadRequest("Status must be Success");
        }

        $importService = $this->getServiceFactory()->create('ImportFeed');

        if (!empty($job->get('parentId'))) {
            $queueItem = $job->get('queueItem');
            if (!empty($queueItem)) {
                $data = $queueItem->get('payload');
                if (ImportTypeSimple::isDeleteAction((string)($data->action ?? ''))) {
                    $actionsMap = [
                        'create_delete'        => 'create',
                        'update_delete'        => 'update',
                        'create_update_delete' => 'create_update'
                    ];

                    if (!isset($actionsMap[$data->action])) {
                        throw new BadRequest($this->translate('cannotReCreateDeleteOnlyJob', 'exceptions', 'ImportJob'));
                    }

                    $data->action = $actionsMap[$data->action];
                }

                $importJob = $this->getRepository()->get();
                $importJob->set('id', IdGenerator::uuid());
                $importJob->set('state', 'Pending');
                $importJob->set('entityName', $job->get('entityName'));
                $importJob->set('importFeedId', $job->get('importFeedId'));
                $importJob->set('attachmentId', !empty($attachmentId) ? $attachmentId : $job->get('attachmentId'));
                $this->getEntityManager()->saveEntity($importJob);

                $data->data->importJobId = $importJob->get('id');
                $data->attachmentId = $importJob->get('attachmentId');
                if (isset($data->payload->parentJobId)) {
                    unset($data->payload->parentJobId);
                }

                $importService->push($queueItem->get('name'), $queueItem->get('type'), json_decode(json_encode($data), true));
            }
            return true;
        }

        return $importService->runImport($job->get('importFeedId'), !empty($attachmentId) ? $attachmentId : $job->get('attachmentId'));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function translate(string $key, string $label, string $scope): string
    {
        return $this->getInjection('container')->get('language')->translate($key, $label, $scope);
    }
}
