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

class Text extends Wysiwyg
{
    public function convert(\stdClass $inputRow, array $config, array $row): void
    {
        parent::convert($inputRow, $config, $row);

        if (!property_exists($inputRow, $config['name']) || $inputRow->{$config['name']} === null) {
            return;
        }

        $value = (string)$inputRow->{$config['name']};

//        $value = str_replace(['<br>', '<br/>', '<br />', '\n'], ["\n", "\n", "\n", "\n"], $value);
//        $inputRow->{$config['name']} = trim(html_entity_decode(strip_tags($value)));

        if (!empty($config['urlHeaders']) && !property_exists($inputRow, 'urlHeaders')) {
            $inputRow->urlHeaders = $config['urlHeaders'];
        }

        $inputRow->{$config['name']} = $value;
    }
}
