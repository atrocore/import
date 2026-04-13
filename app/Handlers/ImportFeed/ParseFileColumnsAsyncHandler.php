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
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportFeed/parseFileColumnsAsync',
    methods: [
        'POST',
    ],
    summary: 'Parse file source fields as background job',
    description: "Queues a background job to parse the source fields from the uploaded file (column names for CSV/Excel, key paths for JSON/XML). Use for files larger than 2 MB.\n\n**How to retrieve the result:**\n1. Call this endpoint to receive a `jobId`\n2. Poll `GET /Job/{jobId}` every few seconds\n3. When `status` is `Success`, the parsed source fields are available in `payload.sourceFields`\n4. When `status` is `Canceled`, the parsing failed",
    tag: 'ImportFeed',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'fileId',
                        'format',
                    ],
                    'properties' => [
                        'fileId'          => [
                            'type' => 'string',
                        ],
                        'format'          => [
                            'type' => 'string',
                            'enum' => [
                                'CSV',
                                'Excel',
                                'JSON',
                                'XML',
                            ],
                        ],
                        'delimiter'       => [
                            'type' => 'string',
                            'enum' => [
                                ',',
                                ';',
                                '\t',
                            ],
                        ],
                        'enclosure'       => [
                            'type' => 'string',
                            'enum' => [
                                'singleQuote',
                                'doubleQuote',
                            ],
                        ],
                        'isHeaderRow'     => [
                            'type' => 'boolean',
                        ],
                        'sheet'           => [
                            'type'     => 'integer',
                            'nullable' => true,
                        ],
                        'rootNode'        => [
                            'type' => 'string',
                        ],
                        'excludedNodes'   => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'keptStringNodes' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Background job queued',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'jobId' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'fileId or format is missing, or the file does not exist',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class ParseFileColumnsAsyncHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'fileId') || empty($data->fileId)) {
            throw new BadRequest("'fileId' is required.");
        }

        if (!property_exists($data, 'format') || empty($data->format)) {
            throw new BadRequest("'format' is required.");
        }

        return new JsonResponse($this->getRecordService('ImportFeed')->queueFileColumnsParse($data));
    }
}
