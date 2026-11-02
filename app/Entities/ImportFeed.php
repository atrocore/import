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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Entities\Base;

class ImportFeed extends Base
{
    protected $entityType = "ImportFeed";

    public static function getFileParserClass(string $format): string
    {
        return match ($format) {
            'CSV' => \Import\FileParsers\Csv::class,
            'Excel' => \Import\FileParsers\Excel::class,
            'JSON' => \Import\FileParsers\Json::class,
            'XML' => \Import\FileParsers\Xml::class,
            default => throw new \Error('Unknown file format'),
        };
    }

    public function getDelimiter(): string
    {
        if (empty($this->get('fileFieldDelimiter'))) {
            return ';';
        }

        return (string)$this->get('fileFieldDelimiter');
    }

    public function getEnclosure(): string
    {
        if (empty($this->get('fileTextQualifier'))) {
            return '"';
        }

        return $this->get('fileTextQualifier') == 'singleQuote' ? "'" : '"';
    }

    public function getHeaderRowNumber(): int
    {
        // headerRowNumber isn't a configurable concept outside CSV/Excel, Converted file must have headers
        if (!in_array($this->get('format'), ['CSV', 'Excel'], true)) {
            return 1;
        }

        $value = $this->get('headerRowNumber');

        return $value === null ? 0 : (int)$value;
    }

    public function getDataStartRowNumber(): int
    {
        $value = $this->get('dataStartRowNumber');

        return $value === null ? 1 : (int)$value;
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

        $result['entity'] = $this->get('entity');
        $result['idField'] = [];
        $result['priority'] = empty($this->get('priority')) ? 'Normal' : (string)$this->get('priority');
        $result['rootNode'] = $this->get('rootNode');
        $result['excludedNodes'] = $this->get('excludedNodes');
        $result['keptStringNodes'] = $this->get('keptStringNodes');
        $result['delimiter'] = $this->get('delimiter');
        $result['skipMissingSourceFields'] = !empty($this->get('skipMissingSourceFields'));
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
                'locale'                    => $item->get('locale'),
                'entity'                    => $result['entity'],
                'delimiter'                 => $result['delimiter'],
                'emptyValue'                => $this->get('emptyValue'),
                'nullValue'                 => $this->get('nullValue'),
                'skipValue'                 => $this->get('skipValue'),
                'urlHeaders'                => $item->get('urlHeaders'),
                'decimalMark'               => $this->get('format') === 'CSV' ? $this->get('decimalMark') : ".",
                'thousandSeparator'         => $this->get('format') !== 'Excel' ? $this->get('thousandSeparator') : "",
                'markForNoRelation'         => $this->get('markForNoRelation'),
                'markForUnlinkedAttribute'  => $this->get('markForUnlinkedAttribute'),
                'fieldDelimiterForRelation' => $this->get('fieldDelimiterForRelation'),
                'valueExtractor'            => $item->get('valueExtractor'),
            ];
        }

        return $result;
    }
}
