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

namespace Import\Entities;

use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Entities\Base;
use Espo\Core\Utils\Json;

class ImportFeed extends Base
{
    protected $entityType = "ImportFeed";

    public static function getFileParserClass(string $format): string
    {
        switch ($format) {
            case 'CSV':
                $className = \Import\FileParsers\Csv::class;
                break;
            case 'Excel':
                $className = \Import\FileParsers\Excel::class;
                break;
            case 'JSON':
                $className = \Import\FileParsers\Json::class;
                break;
            case 'XML':
                $className = \Import\FileParsers\Xml::class;
                break;
            default:
                throw new \Error('Unknown file format');
        }

        return $className;
    }

    public function setFeedField(string $name, $value): void
    {
        $data = [];
        if (!empty($this->get('data'))) {
            $data = Json::decode(Json::encode($this->get('data')), true);
        }

        $data['feedFields'][$name] = $value;

        $this->set('data', $data);
    }

    public function getFeedField(string $name)
    {
        $data = $this->getFeedFields();

        if (!isset($data[$name])) {
            return null;
        }

        return $data[$name];
    }

    public function getFeedFields(): array
    {
        if (!empty($data = $this->get('data'))) {
            $data = Json::decode(Json::encode($data), true);
            if (!empty($data['feedFields']) && is_array($data['feedFields'])) {
                return $data['feedFields'];
            }
        }

        return [];
    }

    public function getDelimiter(): string
    {
        if (empty($this->getFeedField('fileFieldDelimiter'))) {
            return ';';
        }

        return (string)$this->getFeedField('fileFieldDelimiter');
    }

    public function getEnclosure(): string
    {
        if (empty($this->getFeedField('fileTextQualifier'))) {
            return '"';
        }

        return $this->getFeedField('fileTextQualifier') == 'singleQuote' ? "'" : '"';
    }

    public function isFileHeaderRow(): bool
    {
        return !empty($this->getFeedField('isFileHeaderRow'));
    }

    public function getConfiguratorData(): array
    {
        $result = [];

        if ($this->get('processingType') !== 'configurator') {
            return $result;
        }

        if (empty($configuratorItems = $this->get('configuratorItems')) || count($configuratorItems) === 0) {
            $language = $this->getEntityManager()->getRepository('ImportFeed')->getLanguage();
            throw new BadRequest($language->translate('configuratorEmpty', 'exceptions', 'ImportFeed'));
        }

        $result['entity'] = $this->getFeedField('entity');
        $result['idField'] = [];
        $result['priority'] = empty($this->get('priority')) ? 'Normal' : (string)$this->get('priority');
        $result['rootNode'] = $this->getFeedField('rootNode');
        $result['excludedNodes'] = $this->getFeedField('excludedNodes');
        $result['keptStringNodes'] = $this->getFeedField('keptStringNodes');
        $result['delimiter'] = $this->getFeedField('delimiter');
        $result['configuration'] = [];

        foreach ($configuratorItems as $item) {
            if (!empty($item->get('entityIdentifier'))) {
                $result['idField'][] = $item->get('name');
            }

            $result['configuration'][] = [
                'name'                      => $item->get('name'),
                'entityAttributeId'         => $item->get('entityAttributeId'),
                'column'                    => $item->get('column'),
                'createIfNotExist'          => !empty($item->get('createIfNotExist')),
                'replaceArray'              => !empty($item->get('replaceArray')),
                'foreignColumn'             => $item->get('foreignColumn'),
                'foreignImportBy'           => $item->get('foreignImportBy'),
                'default'                   => $item->get('default'),
                'importBy'                  => $item->get('importBy'),
                'type'                      => $item->get('type'),
                'locale'                    => $item->get('locale'),
                'entity'                    => $result['entity'],
                'delimiter'                 => $result['delimiter'],
                'emptyValue'                => $this->getFeedField('emptyValue'),
                'nullValue'                 => $this->getFeedField('nullValue'),
                'skipValue'                 => $this->getFeedField('skipValue'),
                'urlHeaders'                => $item->get('urlHeaders'),
                'decimalMark'               => $this->getFeedField('format') === 'CSV' ? $this->getFeedField('decimalMark') : ".",
                'thousandSeparator'         => $this->getFeedField('thousandSeparator'),
                'markForNoRelation'         => $this->getFeedField('markForNoRelation'),
                'markForUnlinkedAttribute'  => $this->getFeedField('markForUnlinkedAttribute'),
                'fieldDelimiterForRelation' => $this->getFeedField('fieldDelimiterForRelation'),
                'valueExtractor'            => $item->get('valueExtractor'),
            ];
        }

        return $result;
    }
}
