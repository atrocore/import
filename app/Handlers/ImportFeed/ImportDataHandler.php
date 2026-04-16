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

namespace Import\Handlers\ImportFeed;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportFeed/importData',
    methods: [
        'POST',
    ],
    summary: 'Import data',
    description: 'Imports data into the system using the specified feed code and JSON payload.',
    tag: 'ImportFeed',
    auth: false,
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema'  => [
                    'type'       => 'object',
                    'required'   => [
                        'code',
                        'json',
                    ],
                    'properties' => [
                        'code' => [
                            'type'        => 'string',
                            'description' => 'The unique code of the import feed to use for processing the data.',
                        ],
                        'json' => [
                            'type'                 => 'object',
                            'additionalProperties' => true,
                        ],
                    ],
                ],
                'example' => [
                    'code' => 'my-import-feed',
                    'json' => [
                        'data' => [
                            [
                                'ID'         => '001',
                                'Name'       => 'Product A',
                                'SKU'        => 'SKU-001',
                                'Amount' => 10,
                            ],
                            [
                                'ID'     => '002',
                                'Name'   => 'Product B',
                                'SKU'    => 'SKU-002',
                                'Amount' => 5,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Import accepted',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => "'code' or 'json' is missing or empty",
        ],
    ],
)]
class ImportDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'code') || empty($data->code)) {
            throw new BadRequest("'code' is required.");
        }

        if (!property_exists($data, 'json')) {
            throw new BadRequest("'json' is required.");
        }

        $this->getRecordService('ImportFeed')->importData((string) $data->code, $data->json);

        return new BoolResponse(true);
    }
}
