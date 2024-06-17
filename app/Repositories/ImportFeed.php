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

use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
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

    public function removeInvalidConfiguratorItems(ImportFeedEntity $feed): void
    {
        $this->getConnection()->createQueryBuilder()
            ->delete('import_configurator_item')
            ->where('import_feed_id = :id')
            ->andWhere('type = :type')
            ->andWhere("attribute_id NOT IN (SELECT id FROM {$this->getConnection()->quoteIdentifier('attribute')} WHERE deleted=:false)")
            ->setParameter('id', $feed->get('id'))
            ->setParameter('type', 'Attribute')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        $fetchedEntity = $entity->getFeedField('entity');

        $this->setFeedFieldsToDataJson($entity);

        $this->validateFeed($entity);

        if ($entity->get('type') === 'simple') {
            // remove configurator items on Entity change
            if (!$entity->isNew() && $entity->has('entity') && $fetchedEntity !== $entity->get('entity')) {
                $this->getEntityManager()->getRepository('ImportConfiguratorItem')->where(['importFeedId' => $entity->get('id')])->removeCollection();
            }
        }
    }

    public function validateFeed(Entity $entity): void
    {
        $delimiters = [
            $entity->getFeedField('delimiter'),
            $entity->getFeedField('decimalMark'),
            //$entity->getFeedField('thousandSeparator'),
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

        if (empty($entity->get('sourceFields'))) {
            throw new BadRequest($this->getLanguage()->translate("sourceFieldsEmpty", "exceptions", "ImportFeed"));
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
                        $data['feedFields'][$field] = (int)$data['feedFields'][$field];
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
