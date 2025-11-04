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

use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Import\Entities\ImportFeed as ImportFeedEntity;

class ImportFeed extends Base
{
    public function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    public function getLatestJobData(array $importFeedsIds): array
    {
        $records = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('t1.id, t1.import_feed_id, t1.start, t1.state')
            ->from('import_job', 't1')
            ->innerJoin(
                't1', '(SELECT import_feed_id, MAX(start) AS max_start FROM import_job GROUP BY import_feed_id)', 't2',
                't1.import_feed_id = t2.import_feed_id AND t1.start = t2.max_start'
            )
            ->where('t1.deleted=:false')
            ->andWhere('t1.import_feed_id IN (:ids)')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('ids', $importFeedsIds, $this->getEntityManager()->getConnection()::PARAM_STR_ARRAY)
            ->fetchAllAssociative();

        $res = [];

        foreach ($records as $record) {
            $res[$record['import_feed_id']] = $record;
        }

        return $res;
    }

    public function hasDeletedRecordsToClear(): bool
    {
        Util::removeDir(\Import\Services\ImportFeed::TMP_DIR);

        return parent::hasDeletedRecordsToClear();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $this->setFeedFieldsToDataJson($entity);

        $this->validateFeed($entity);
    }

    public function validateFeed(Entity $entity, bool $checkEntityIdentifier = false): void
    {
        if ($entity->get('processingType') !== 'configurator') {
            return;
        }

        $delimiters = [
            $entity->getFeedField('delimiter'),
            $entity->getFeedField('decimalMark'),
            $entity->getFeedField('fieldDelimiterForRelation')
        ];

        if (count(array_unique($delimiters)) !== count($delimiters)) {
            throw new BadRequest($this->getLanguage()->translate('delimitersMustBeDifferent', 'exceptions', 'ImportFeed'));
        }

        if ($entity->getFeedField('emptyValue') === $entity->getFeedField('nullValue')) {
            throw new BadRequest($this->getLanguage()->translate("nullNoneSame", "exceptions", "ImportFeed"));
        }

        if ($entity->getFeedField('skipValue') === $entity->getFeedField('emptyValue')) {
            throw new BadRequest($this->getLanguage()->translate("skipNoneSameEmpty", "exceptions", "ImportFeed"));
        }

        if ($entity->getFeedField('skipValue') === $entity->getFeedField('nullValue')) {
            throw new BadRequest($this->getLanguage()->translate("skipNoneSameNull", "exceptions", "ImportFeed"));
        }

        if ($checkEntityIdentifier) {
            $idItem = $this->getEntityManager()->getRepository('ImportConfiguratorItem')
                ->where([
                    'entityIdentifier' => true,
                    'importFeedId'     => $entity->get('id')
                ])
                ->findOne();

            if (empty($idItem)) {
                throw new BadRequest($this->getLanguage()->translate("noEntityIdentifier", "exceptions", "ImportFeed"));
            }
        }
    }

    protected function setFeedFieldsToDataJson(Entity $entity): void
    {
        $data = !empty($data = $entity->get('data')) ? Json::decode(Json::encode($data), true) : [];

        foreach ($this->getMetadata()->get(['entityDefs', 'ImportFeed', 'fields'], []) as $field => $row) {
            if (empty($row['notStorable']) || empty($row['dataField'])) {
                continue 1;
            }

            if ($entity->has($field)) {
                $data['feedFields'][$field] = $entity->get($field);

                switch ($row['type']) {
                    case 'int':
                        if ($data['feedFields'][$field] !== null || !empty($row['notNull'])) {
                            $data['feedFields'][$field] = (int)$data['feedFields'][$field];
                        }
                        break;
                    case 'bool':
                        $data['feedFields'][$field] = !empty($data['feedFields'][$field]);
                        break;
                }
            }
        }

        $entity->set('data', $data);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
