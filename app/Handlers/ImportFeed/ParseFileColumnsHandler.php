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
    path: '/ImportFeed/parseFileColumns',
    methods: [
        'POST',
    ],
    summary: 'Parse file source fields',
    description: "Synchronously reads the uploaded file and returns its source fields (column names for CSV/Excel, key paths for JSON/XML).\n\n> Use for files up to **2 MB**. For larger files use `POST /ImportFeed/parseFileColumnsAsync`.",
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
                        'fileId'           => [
                            'type' => 'string',
                        ],
                        'format'           => [
                            'type' => 'string',
                            'enum' => [
                                'CSV',
                                'Excel',
                                'JSON',
                                'XML',
                            ],
                        ],
                        'delimiter'        => [
                            'type'     => 'string',
                            'nullable' => true,
                            'enum'     => [
                                ',',
                                ';',
                                '\t',
                            ],
                        ],
                        'enclosure'        => [
                            'type'     => 'string',
                            'nullable' => true,
                            'enum'     => [
                                'singleQuote',
                                'doubleQuote',
                            ],
                        ],
                        'isHeaderRow'      => [
                            'type'     => 'boolean',
                            'nullable' => true,
                        ],
                        'sheet'            => [
                            'type'     => 'integer',
                            'nullable' => true,
                        ],
                        'rootNode'         => [
                            'type'     => 'string',
                            'nullable' => true,
                        ],
                        'excludedNodes'    => [
                            'type'     => 'array',
                            'nullable' => true,
                            'items'    => [
                                'type' => 'string',
                            ],
                        ],
                        'keptStringNodes'  => [
                            'type'     => 'array',
                            'nullable' => true,
                            'items'    => [
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
            'description' => 'List of source fields detected in the file',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'string',
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
class ParseFileColumnsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $this->getRequestBody($request);

        if (empty($body->fileId)) {
            throw new BadRequest("'fileId' is required.");
        }

        if (empty($body->format)) {
            throw new BadRequest("'format' is required.");
        }

        return new JsonResponse($this->getRecordService('ImportFeed')->getFileColumns($body));
    }
}
