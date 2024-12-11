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

namespace Import;

use Espo\Core\OpenApiGenerator;
use Atro\Core\ModuleManager\AbstractModule;
use Espo\Core\Utils\Util;

/**
 * Class Module
 */
class Module extends AbstractModule
{
    /**
     * @inheritdoc
     */
    public static function getLoadOrder(): int
    {
        return 5110;
    }

    public static function afterUpdate(): void
    {
        \Import\Jobs\ImportTypeSimple::clearCache();
        Util::removeDir(\Import\Services\ImportFeed::TMP_DIR);
    }

    public function prepareApiDocs(array &$data, array $schemas): void
    {
        parent::prepareApiDocs($data, $schemas);

        $data['paths']["/ImportFeed/action/runImport"]['post'] = [
            'tags'        => ['ImportFeed'],
            "summary"     => "Run import",
            "description" => "Run import",
            "operationId" => "runImport",
            'security'    => [['Authorization-Token' => []]],
            'requestBody' => [
                'required' => true,
                'content'  => [
                    'application/json' => [
                        'schema' => [
                            "type"       => "object",
                            "properties" => [
                                "importFeedId" => [
                                    "type" => "string",
                                ],
                                "attachmentId" => [
                                    "type" => "string",
                                ],
                            ],
                        ]
                    ]
                ],
            ],
            "responses"   => OpenApiGenerator::prepareResponses(["type" => "boolean"]),
        ];
    }
}
