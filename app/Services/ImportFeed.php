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

use Atro\Core\AttributeFieldConverter;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\Error;
use Atro\Core\FileStorage\FileStorageInterface;
use Atro\Core\Utils\Language;
use Atro\Entities\File;
use Atro\Jobs\JobInterface;
use Atro\Services\File as FileService;
use Atro\Entities\Folder;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Import\Entities\ImportFeed as ImportFeedEntity;
use Import\Entities\ImportJob;
use Import\Jobs\ImportJobCreator;
use Import\Jobs\ImportTypeSimple;

class ImportFeed extends Base
{
    public const TMP_DIR = 'data/import-tmp';

    protected $mandatorySelectAttributeList = ['sourceFields', 'sheet', 'data'];

    public const PRIORITIES = [
        "Low"    => 50,
        "Normal" => 100,
        "High"   => 150
    ];

    public function putAttributesToMetadata(string $importFeedId): void
    {
        $importFeed = $this->getEntityManager()->getEntity('ImportFeed', $importFeedId);
        if (empty($importFeed)) {
            return;
        }

        $entityName = $importFeed->getFeedField('entity');

        if ($this->getMetadata()->get("scopes.$entityName.hasAttribute")) {
            $conn = $this->getEntityManager()->getConnection();
            $attributes = $conn->createQueryBuilder()
                ->select('a.*, c.name as channel_name')
                ->distinct()
                ->from($conn->quoteIdentifier('attribute'), 'a')
                ->leftJoin('a', $conn->quoteIdentifier('channel'), 'c', 'c.id=a.channel_id')
                ->innerJoin('a', 'import_configurator_item', 'i', 'i.entity_attribute_id=a.id AND i.deleted=:false')
                ->innerJoin('i', 'import_feed', 'e', 'i.import_feed_id=e.id AND e.deleted=:false')
                ->where('a.deleted=:false')
                ->andWhere('e.id=:importFeedId')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('importFeedId', $importFeed->get('id'))
                ->fetchAllAssociative();

            $importEntity = $this->getEntityManager()->getEntity($entityName);

            $attributesDefs = [];
            foreach ($attributes as $k => $row) {
                if (!empty($row['channel_name'])) {
                    $row['name'] = $row['name'] . ' / ' . $row['channel_name'];
                }
                $this->getAttributeFieldConverter()->convert($importEntity, $row, $attributesDefs, true);
            }

            foreach ($attributesDefs as $name => $attributeDefs) {
                $this
                    ->getMetadata()
                    ->set('entityDefs', $entityName, ['fields' => [$name => $attributeDefs]]);

                $this->getLanguage()->set($entityName, 'fields', $name, $attributeDefs['label']);
            }
        }
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        $latestJobsData = $this->getRepository()->getLatestJobData(array_column($collection->toArray(), 'id'));

        foreach ($collection as $entity) {
            $entity->_collectionPrepared = true;
            if (isset($latestJobsData[$entity->get('id')])) {
                $entity->set('lastStatus', $latestJobsData[$entity->get('id')]['state']);
                $entity->set('lastTime', $latestJobsData[$entity->get('id')]['start']);
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        foreach ($entity->getFeedFields() as $name => $value) {
            $entity->set($name, $value);
        }

        if (empty($entity->_collectionPrepared)) {
            $latestJobsData = $this->getRepository()->getLatestJobData([$entity->get('id')]);
            if (isset($latestJobsData[$entity->get('id')])) {
                $entity->set('lastStatus', $latestJobsData[$entity->get('id')]['state']);
                $entity->set('lastTime', $latestJobsData[$entity->get('id')]['start']);
            }
        }
    }

    public function createImportFileFolder(ImportFeedEntity $importFeed): Folder
    {
        /** @var \Atro\Repositories\Folder $folderRepo */
        $folderRepo = $this->getEntityManager()->getRepository('Folder');

        $root = $folderRepo->where(['code' => 'import_feeds'])->findOne();
        if (empty($root)) {
            $root = $folderRepo->get();
            $root->set([
                'name'   => 'Import Feeds',
                'hidden' => true,
                'code'   => 'import_feeds'
            ]);
            $this->getEntityManager()->saveEntity($root);
        }

        $folder = $folderRepo->where(['code' => $importFeed->get('id')])->findOne();
        if (empty($folder)) {
            $folder = $folderRepo->get();
            $folder->set([
                'name'   => $importFeed->get('name'),
                'hidden' => true,
                'code'   => $importFeed->get('id')
            ]);
            $this->getEntityManager()->saveEntity($folder);
            $folderRepo->relate($folder, 'parents', $root);
        }

        return $folder;
    }

    public function parseFileColumns(\stdClass $payload): array
    {
        if (!property_exists($payload, 'attachmentId')) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $attachment = $this->getEntityManager()->getEntity('File', $payload->attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        if (property_exists($payload, 'format')) {
            $method = "validate{$payload->format}File";
            if (method_exists($this, $method)) {
                $this->$method($attachment->get('id'));
            }
        }

        $maxSize = 1024 * 1024 * 2; // 2 MB

        if ($attachment->get('fileSize') > $maxSize) {
            $name = str_replace("{{fileName}}", $attachment->get('name'), $this->translate('parseFile'));

            $jobEntity = $this->getEntityManager()->getEntity('Job');
            $jobEntity->set([
                'name'    => $name,
                'type'    => 'BackgroundFileParser',
                'payload' => [
                    'payload' => $payload
                ]
            ]);
            $this->getEntityManager()->saveEntity($jobEntity);

            return [
                'jobId' => $jobEntity->get('id')
            ];
        }

        return $this->getFileColumns($payload);
    }

    public function getFileSheets(\stdClass $payload): array
    {
        if (!property_exists($payload, 'format') || $payload->format !== 'Excel') {
            return [];
        }

        if (!property_exists($payload, 'attachmentId')) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $attachment = $this->getEntityManager()->getEntity('File', $payload->attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        return $this->getFileParser($payload->format)->getFileSheetsNames($attachment);
    }

    public function getFileColumns(\stdClass $payload): array
    {
        if (!property_exists($payload, 'attachmentId')) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        /** @var File $attachment */
        $attachment = $this->getEntityManager()->getEntity('File', $payload->attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        if (!property_exists($payload, 'format') || empty($payload->format)) {
            throw new BadRequest('Format is required.');
        }

        $method = "validate{$payload->format}File";
        if (method_exists($this, $method)) {
            $this->$method($attachment->get('id'));
        }

        $parser = $this->getFileParser($payload->format);
        $parser->setData([
            'delimiter'       => (property_exists($payload, 'delimiter') && !empty($payload->delimiter)) ? $payload->delimiter : ';',
            'enclosure'       => (property_exists($payload, 'enclosure') && $payload->enclosure == 'singleQuote') ? "'" : '"',
            'isFileHeaderRow' => (property_exists($payload, 'isHeaderRow') && is_null($payload->isHeaderRow)) ? true : !empty($payload->isHeaderRow),
            'sheet'           => property_exists($payload, 'sheet') ? (int)$payload->sheet : 0,
            'rootNode'        => (property_exists($payload, 'rootNode') && !empty($payload->rootNode)) ? $payload->rootNode : null,
            'excludedNodes'   => (property_exists($payload, 'excludedNodes') && !empty($payload->excludedNodes)) ? $payload->excludedNodes : [],
            'keptStringNodes' => (property_exists($payload, 'keptStringNodes') && !empty($payload->keptStringNodes)) ? $payload->keptStringNodes : [],
        ]);

        return $parser->getFileColumns($attachment);
    }

    public function validateXMLFile(string $attachmentId): void
    {
        /** @var File $attachment */
        $attachment = $this->getEntityManager()->getEntity('File', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $contents = $attachment->getContents();

        $data = \simplexml_load_string($contents);
        if (empty($data)) {
            throw new BadRequest($this->getInjection('language')->translate('xmlExpected', 'exceptions', 'ImportFeed'));
        }
    }

    public function validateJSONFile(string $attachmentId): void
    {
        /** @var File $attachment */
        $attachment = $this->getEntityManager()->getEntity('File', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $contents = $attachment->getContents();

        if (is_string($contents)) {
            $data = @json_decode($contents, true);
        }

        if (empty($data)) {
            throw new BadRequest($this->getInjection('language')->translate('jsonExpected', 'exceptions', 'ImportFeed'));
        }
    }

    public function validateCSVFile(string $attachmentId): void
    {
        /** @var File $attachment */
        $attachment = $this->getEntityManager()->getEntity('File', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $csvTypes = [
            "text/csv",
            "text/plain",
            "text/x-csv",
            "application/vnd.ms-excel",
            "text/x-csv",
            "application/csv",
            "application/x-csv",
            "text/comma-separated-values",
            "text/x-comma-separated-values",
            "text/tab-separated-values"
        ];

        if (!in_array($attachment->get('mimeType'), $csvTypes)) {
            throw new BadRequest($this->getInjection('language')->translate('csvExpected', 'exceptions', 'ImportFeed'));
        }

        $contents = $attachment->getContents();
        if (is_string($contents) && !preg_match('//u', $contents)) {
            throw new BadRequest($this->getInjection('language')->translate('utf8Expected', 'exceptions', 'ImportFeed'));
        }
    }

    public function validateExcelFile(string $attachmentId): void
    {
        /** @var File $attachment */
        $attachment = $this->getEntityManager()->getEntity('File', $attachmentId);
        if (empty($attachment)) {
            throw new BadRequest($this->exception("noSuchFile"));
        }

        $excelTypes = [
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/vnd.ms-excel",
        ];

        if (!in_array($attachment->get('mimeType'), $excelTypes)) {
            throw new BadRequest($this->getInjection('language')->translate('excelExpected', 'exceptions', 'ImportFeed'));
        }
    }

    public function runImport(string $importFeedId, string $attachmentId, \stdClass $payload = null, ?string $priority = null): bool
    {
        $event = $this
            ->getInjection('eventManager')
            ->dispatch('ImportFeedService', 'beforeRunImport', new Event(['importFeedId' => $importFeedId, 'attachmentId' => $attachmentId, 'payload' => $payload]));

        $importFeedId = $event->getArgument('importFeedId');
        $attachmentId = $event->getArgument('attachmentId');
        $payload = $event->getArgument('payload');

        $feed = $this->getImportFeed($importFeedId);

        // firstly, validate feed
        $this->getRepository()->validateFeed($feed, true);

        $service = $this->getImportTypeService($feed);

        if (method_exists($service, 'runImport')) {
            return $service->runImport($feed, $attachmentId, $payload, $priority);
        }

        if (empty($attachmentId)) {
            $attachmentId = $feed->get('fileId');
            if (empty($attachmentId)) {
                throw new BadRequest($this->getInjection('language')->translate('fileIdIsEmpty', 'exceptions', 'ImportFeed'));
            }
        }

        $file = $feed->get('file');
        if (!empty($file) && $file->get('id') === $attachmentId) {
            $attachmentId = $this->createFileDuplicate($file, $this->createImportFileFolder($feed)->get('id'));
        }

        $this->pushJobs($feed, $attachmentId, $payload, $priority);

        $this
            ->getInjection('eventManager')
            ->dispatch('ImportFeedService', 'afterImportJobsCreations', new Event(['importFeedId' => $importFeedId]));

        return true;
    }

    public function pushDeleteJobs(ImportJob $parent, array $jobsData, Entity $qmJob): bool
    {
        /** @var ImportFeedEntity $importFeed */
        $importFeed = $parent->get('importFeed');

        $service = $this->getImportTypeService($importFeed);
        $jobStates = array_unique(array_column($jobsData, 'state'));
        $jobIds = array_column($jobsData, 'id');
        $entityName = $parent->get('entityName');
        $maxPerJob = (int)$importFeed->get('maxPerJob');
        $qmData = $qmJob->get('payload');

        if (!ImportTypeSimple::isDeleteAction($qmData->action)) {
            return false;
        }

        // push delete jobs only if all child jobs are succeed
        if (in_array('Success', $jobStates) && count($jobStates) === 1) {
            $qmData = json_decode(json_encode($qmData), true);
            $qmData['action'] = 'delete_found';
            $qmData['fileFormat'] = 'CSV';
            $qmData['isFileHeaderRow'] = true;
            $qmData['offset'] = 1;
            $qmData['data']['idField'] = ['id'];
            $qmData['data']['entity'] = $entityName;

            $confItem = [];
            foreach ($qmData['data']['configuration'] ?? [] as $item) {
                // copy common field configuration
                if ($item['entity'] == $entityName && $item['type'] == 'Field') {
                    foreach ($service->getCommonFieldsList() as $commonField) {
                        $confItem[$commonField] = $item[$commonField];
                    }
                    break;
                }
            }

            // do not push jobs if there are no fields in configurator for some reason
            if (empty($confItem)) {
                return false;
            }

            $confItem['type'] = 'Field';
            $confItem['name'] = 'id';
            $confItem['entity'] = $entityName;
            $confItem['column'] = ['id'];
            $qmData['data']['configuration'] = [$confItem];

            // generate import file
            $cacheFileName = $service->prepareDeleteCache($parent->get('id'), $jobIds);
            $files = $service->generateDeleteFilesFromCache($importFeed, $cacheFileName, $entityName);

            if (empty($files)) {
                return false;
            }

            $payload = new \stdClass();
            $payload->parentJobId = $parent->get('id');
            $rowNumberPart = 0;
            foreach ($files as $file) {
                $fileId = is_array($file) ? $file['id'] : $file->get('id');
                $qmData['attachmentId'] = $fileId;
                $qmData['rowNumberPart'] = $rowNumberPart;
                $rowNumberPart += $maxPerJob;
                $deleteJob = $this->createImportJob($importFeed, $parent->get('entityName'), $fileId, $payload);
                $qmData['data']['importJobId'] = $deleteJob->get('id');
                $this->push($this->getName($importFeed), 'ImportTypeSimple', $qmData);
            }

            return true;
        }

        return false;
    }

    public function hasParentJob(ImportFeedEntity $importFeed): bool
    {
        return (int)$importFeed->get('maxPerJob') > 0 || ImportTypeSimple::isDeleteAction($importFeed->get('fileDataAction') ?? '');
    }

    public function pushJobs(ImportFeedEntity $importFeed, string $attachmentId, ?\stdClass $payload = null, ?string $priority = null): void
    {
        $hasParent = $this->hasParentJob($importFeed);

        if ($hasParent) {
            $parentJob = $this->createImportJob($importFeed, $importFeed->getFeedField('entity'), $attachmentId, new \stdClass());
            if ($payload === null) {
                $payload = new \stdClass();
            }
            $payload->parentJobId = $parentJob->get('id');
        }

        $maxPerJob = $payload->maxPerJob ?? (int)$importFeed->get('maxPerJob');
        $format = $payload->format ?? $importFeed->getFeedField('format');

        if ($maxPerJob > 0 && in_array($format, ['CSV', 'Excel'])) {
            $name = $this->getInjection('language')->translate('createImportJobs', 'labels', 'ImportFeed');
            $name = sprintf($name, $importFeed->get('name'));

            $qmJobData = [
                'importFeedId' => $importFeed->get('id'),
                'attachmentId' => $attachmentId,
                'payload'      => $payload,
                'priority'     => $priority
            ];

            if (!empty($payload) && !empty($payload->executeNow)) {
                $this->getInjection('container')->get(ImportJobCreator::class)->runNow($qmJobData);
            } else {
                $jobEntity = $this->getEntityManager()->getEntity('Job');
                $jobEntity->set([
                    'name'    => $name,
                    'type'    => 'ImportJobCreator',
                    'payload' => $qmJobData
                ]);
                $this->getEntityManager()->saveEntity($jobEntity);

                $this->getEntityManager()->getConnection()->createQueryBuilder()
                    ->update('import_job')
                    ->set('queue_item_id', ':queueItemId')
                    ->where('id = :id')
                    ->setParameter('queueItemId', $jobEntity->get('id'))
                    ->setParameter('id', $parentJob->get('id'))
                    ->executeQuery();
            }
        } else {
            $data = $this->getImportTypeService($importFeed)->prepareJobData($importFeed, $attachmentId);
            $data['payload'] = $payload;
            if (!empty($priority)) {
                $data['data']['priority'] = $priority;
            }
            $data['data']['importJobId'] = $this->createImportJob($importFeed, $importFeed->getFeedField('entity'), $attachmentId, $payload)->get('id');
            $this->push($this->getName($importFeed), 'ImportType' . ucfirst($importFeed->get('type')), $data);
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'ImportFeed');
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('filePathBuilder');
        $this->addDependency('container');
    }

    protected function duplicateConfiguratorItems(Entity $entity, Entity $duplicatingEntity): void
    {
        if (empty($items = $duplicatingEntity->get('configuratorItems')) || count($items) === 0) {
            return;
        }

        $service = $this->getServiceFactory()->create('ImportConfiguratorItem');

        foreach ($items as $item) {
            $data = $item->toArray();
            unset($data['id']);
            $data['importFeedId'] = $entity->get('id');
            $newItem = $this->getEntityManager()->getEntity('ImportConfiguratorItem');
            $newItem->set($data);
            $service->prepareDuplicateEntityForSave($entity, $newItem);
            $this->getEntityManager()->saveEntity($newItem);
        }
    }

    protected function duplicateImportHttpHeaders(Entity $entity, Entity $duplicatingEntity): void
    {
        $headers = $duplicatingEntity->get('importHttpHeaders');

        if (empty($headers) || count($headers) === 0) {
            return;
        }

        foreach ($headers as $header) {
            $data = $header->toArray();
            unset($data['id']);
            $data['importFeedId'] = $entity->get('id');

            $newHeader = $this->getEntityManager()->getEntity('ImportHttpHeader');
            $newHeader->set($data);
            $this->getEntityManager()->saveEntity($newHeader);
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function translate(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'labels', 'ImportFeed');
    }

    public function push(string $name, string $type, array $data = []): bool
    {
        if (!empty($data['payload']) && !empty($data['payload']->executeNow)) {
            if (empty($data['data']['importJobId'])) {
                return false;
            }
            $importJob = $this->getEntityManager()->getRepository('ImportJob')->get($data['data']['importJobId']);
            if (empty($importJob)) {
                return false;
            }
            try {
                $className = $this->getMetadata()->get(['app', 'jobTypes', $type, 'handler']);
                $this->getInjection('container')->get($className)->runNow($data);
                $importJob->set('state', 'Success');
            } catch (\Throwable $e) {
                $importJob->set('state', 'Failed');
                $importJob->set('message', $e->getMessage());
            }

            $this->getEntityManager()->saveEntity($importJob);

            return true;
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'     => $name,
            'type'     => $type,
            'priority' => isset($data['data']['priority']) ? self::PRIORITIES[$data['data']['priority']] : 100,
            'payload'  => $data
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->update('import_job')
            ->set('sort_order', ':sortOrder')
            ->set('queue_item_id', ':queueItemId')
            ->where('id = :id')
            ->setParameter('sortOrder', time())
            ->setParameter('queueItemId', $jobEntity->get('id'))
            ->setParameter('id', $data['data']['importJobId'])
            ->executeQuery();

        // waiting because we need a correct next sort number
        sleep(1);

        return !empty($id);
    }

    /**
     * @param string $importFeedId
     *
     * @return ImportFeedEntity
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    protected function getImportFeed(string $importFeedId): ImportFeedEntity
    {
        $feed = $this->getEntityManager()->getEntity('ImportFeed', $importFeedId);
        if (empty($feed)) {
            throw new NotFound($this->exception("No such ImportFeed"));
        }

        // checking rules
        if (!$this->getAcl()->check($feed, 'read')) {
            throw new Forbidden();
        }

        // is feed active ?
        if (!$feed->get('isActive')) {
            throw new BadRequest($this->exception("importFeedIsInactive"));
        }

        return $feed;
    }

    public function getFileParser(string $format): \Import\FileParsers\FileParserInterface
    {
        return $this->getInjection('container')->get(ImportFeedEntity::getFileParserClass($format));
    }

    /**
     * @param ImportFeedEntity $feed
     *
     * @return string
     */
    public function getName(ImportFeedEntity $feed): string
    {
        return $this->translate("Import") . ": <strong>{$feed->get("name")}</strong>";
    }

    public function getImportTypeService(ImportFeedEntity $feed): JobInterface
    {
        $className = $this->getMetadata()->get(['app', 'jobTypes', 'ImportType' . ucfirst($feed->get('type')), 'handler']);

        return $this->getInjection('container')->get($className);
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->getInjection('container')->get(AttributeFieldConverter::class);
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('container')->get('language');
    }

    public function createImportJob(ImportFeedEntity $feed, string $entityType, string $attachmentId, \stdClass $payload = null): ImportJob
    {
        $entityLabel = $this->getInjection('language')->translate($entityType, 'scopeNames');

        $entity = $this->getEntityManager()->getEntity('ImportJob');
        $entity->set('name', "{$entityLabel}: {$feed->get('name')}");
        $entity->set('importFeedId', $feed->get('id'));
        $entity->set('entityName', $entityType);
        $entity->set('attachmentId', $attachmentId);
        $entity->set('sortOrder', time() - (new \DateTime('2023-01-01'))->getTimestamp());

        if (!empty($payload)) {
            $entity->set('payload', $payload);
            if (property_exists($payload, 'parentJobId')) {
                $entity->set('parentId', $payload->parentJobId);
            }
            if (property_exists($payload, 'convertedFileId')) {
                $entity->set('convertedFileId', $payload->convertedFileId);
            }
        }

        $this->getEntityManager()->saveEntity($entity);

        return $entity;
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        parent::beforeUpdateEntity($entity, $data);

        foreach ($entity->getFeedFields() as $name => $value) {
            if (!$entity->has($name)) {
                $entity->set($name, $value);
            }
        }
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    public function createFromExportFeed($exportFeedId)
    {
        $exportFeed = $this->getEntityManager()->getEntity('ExportFeed', $exportFeedId);

        $locale = null;
        if (!empty($exportFeed->get('localeId'))) {
            $locale = $this->getEntityManager()->getEntity('Locale', $exportFeed->get('localeId'));
        }

        if (empty($exportFeed)) {
            throw new NotFound();
        }

        $sourceFields = [];
        foreach ($exportFeed->configuratorItems as $configuratorItem) {
            $this->getRecordService("ExportConfiguratorItem")->prepareEntityForOutput($configuratorItem);

            if ($configuratorItem->type === 'Fixed value' || $configuratorItem->type === 'script') {
                continue;
            }

            if (!empty($configuratorItem->entityAttributeId)) {
                $attribute = $this->getEntityManager()->getEntity('Attribute', $configuratorItem->entityAttributeId);
                if (empty($attribute) || $attribute->get('type') === 'script') {
                    continue;
                }
            }

            if (!empty($configuratorItem->column)) {
                $sourceFields[] = $configuratorItem->column;
            }
        }
        if (empty($sourceFields)) {
            $sourceFields = ['ID'];
        }

        $attachment = new \stdClass();
        $attachment->name = $exportFeed->get('name') . '(From Export)';
        $attachment->description = $exportFeed->get('description');
        $attachment->code = $exportFeed->code;
        $attachment->isActive = $exportFeed->get('isActive');
        $attachment->type = 'simple';
        $attachment->fileDataAction = 'update';
        $format = $exportFeed->get('fileType') === 'xlsx' ? 'Excel' : strtoupper($exportFeed->get('fileType'));
        $attachment->format = $format;
        $attachment->sourceFields = $sourceFields;
        $attachment->entity = $exportFeed->getFeedField('entity');
        $attachment->emptyValue = $exportFeed->getFeedField('emptyValue');
        $attachment->nullValue = $exportFeed->getFeedField('nullValue');
        $attachment->markForNoRelation = $exportFeed->getFeedField('markForNoRelation');
        $attachment->markForUnlinkedAttribute = $exportFeed->getFeedField('markForUnlinkedAttribute');
        if (!empty($locale)) {
            $attachment->thousandSeparator = $locale->get('thousandSeparator');
            $attachment->decimalMark = $locale->get('decimalMark');
        }
        $importFeed = $this->createEntity($attachment);

        foreach ($exportFeed->configuratorItems as $configuratorItem) {
            if ($configuratorItem->type === 'Fixed value' || $configuratorItem->type === 'script') {
                continue;
            }

            if (!empty($configuratorItem->entityAttributeId)) {
                $attribute = $this->getEntityManager()->getEntity('Attribute', $configuratorItem->entityAttributeId);
                if (empty($attribute) || $attribute->get('type') === 'script') {
                    continue;
                }
            }

            $attachment = new \stdClass();
            $attachment->importFeedId = $importFeed->id;
            $attachment->name = $configuratorItem->name;
            if (!empty($configuratorItem->column)) {
                $attachment->column = [$configuratorItem->column];
            }
            $attachment->scope = $configuratorItem->scope;
            $attachment->locale = $configuratorItem->language;
            $attachment->sortOrder = $configuratorItem->sortOrder;
            $attachment->importBy = $configuratorItem->exportBy;
            $attachment->entityAttributeId = $configuratorItem->entityAttributeId;

            if ($configuratorItem->name === 'id') {
                $attachment->entityIdentifier = true;
            }

            $this->getRecordService("ImportConfiguratorItem")->createEntity($attachment);
        }

        return $importFeed;
    }

    public function verifyCodeEasyCatalog(string $code)
    {
        $importFeed = $this->getRepository()->where(['code' => $code])->findOne();
        if (empty($importFeed)) {
            return 'Import Feed code is invalid';
        }

        $hasIdColumn = false;
        foreach ($importFeed->configuratorItems as $configuratorItem) {
            if ($configuratorItem->get('name') == 'id' && !empty($configuratorItem->get('column')) && $configuratorItem->get('column')[0] == "ID") {
                $hasIdColumn = true;
                break;
            }
        }

        if (!$hasIdColumn) {
            return 'This import feed has no ID column';
        }

        return 'Import feed is correctly configured';
    }

    public function importFromEasyCatalog(\stdClass $data)
    {
        $importFeed = $this->getRepository()->where(['code' => $data->code])->findOne();
        if (empty($importFeed)) {
            throw new NotFound();
        }

        $input = new \stdClass();
        $input->name = 'easy-catalog.json';
        $input->hidden = true;

        $file = $this->getFileService()->createFileViaContents($input, json_encode($data->json));

        $this->runImport($importFeed->id, $file['id']);
    }

    public function createFileDuplicate(File $file, ?string $folderId): string
    {
        /** @var FileStorageInterface $storage */
        $storage = $this->getInjection('container')->get($file->getStorage()->get('type') . 'Storage');

        $tmpDir = self::TMP_DIR . DIRECTORY_SEPARATOR . Util::generateId();
        @mkdir($tmpDir, 0777, true);

        $path = $tmpDir . DIRECTORY_SEPARATOR . $file->get('name');

        $stream = $storage->getStream($file);

        $tmpFile = fopen($path, 'w');
        if ($tmpFile === false) {
            throw new \RuntimeException('Failed to open file for writing: ' . $path);
        }
        $stream->rewind();
        while (!$stream->eof()) {
            fwrite($tmpFile, $stream->read(8192));
        }
        fclose($tmpFile);

        $input = new \stdClass();
        $input->name = $file->get('name');
        $input->hidden = true;
        if ($folderId !== null) {
            $input->folderId = $folderId;
        }

        $fileData = $this->getFileService()->moveLocalFileToFileEntity($input, $path);

        @rmdir($tmpDir);

        if (empty($fileData['id'])) {
            throw new Error('File duplicate was not created!');
        }

        return $fileData['id'];
    }

    protected function getFileService(): FileService
    {
        return $this->getServiceFactory()->create('File');
    }

    protected function createDir(string $fileName): void
    {
        $parts = explode('/', $fileName);
        array_pop($parts);
        $dir = implode('/', $parts);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            sleep(1);
        }
    }

}
