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
        'GET',
    ],
    summary: 'Parse file source fields',
    description: "Synchronously reads the uploaded file and returns its source fields (column names for CSV/Excel, key paths for JSON/XML).\n\n> Use for files up to **2 MB**. For larger files use `POST /ImportFeed/parseFileColumnsAsync`.",
    tag: 'ImportFeed',
    parameters: [
        [
            'name'     => 'fileId',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'format',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
                'enum' => [
                    'CSV',
                    'Excel',
                    'JSON',
                    'XML',
                ],
            ],
        ],
        [
            'name'     => 'delimiter',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
                'enum' => [
                    ',',
                    ';',
                    '\t',
                ],
            ],
        ],
        [
            'name'     => 'enclosure',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
                'enum' => [
                    'singleQuote',
                    'doubleQuote',
                ],
            ],
        ],
        [
            'name'     => 'isHeaderRow',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'boolean',
            ],
        ],
        [
            'name'     => 'sheet',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'integer',
            ],
        ],
        [
            'name'     => 'rootNode',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type' => 'string',
            ],
        ],
        [
            'name'     => 'excludedNodes',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
        ],
        [
            'name'     => 'keptStringNodes',
            'in'       => 'query',
            'required' => false,
            'schema'   => [
                'type'  => 'array',
                'items' => [
                    'type' => 'string',
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
        $params = $request->getQueryParams();

        if (empty($params['fileId'])) {
            throw new BadRequest("'fileId' is required.");
        }

        if (empty($params['format'])) {
            throw new BadRequest("'format' is required.");
        }

        $payload = new \stdClass();
        $payload->fileId      = $params['fileId'];
        $payload->format      = $params['format'];
        $payload->delimiter   = $params['delimiter'] ?? null;
        $payload->enclosure   = $params['enclosure'] ?? null;
        $payload->isHeaderRow = isset($params['isHeaderRow']) ? filter_var($params['isHeaderRow'], FILTER_VALIDATE_BOOLEAN) : null;
        $payload->sheet       = isset($params['sheet']) ? (int) $params['sheet'] : null;
        $payload->rootNode    = $params['rootNode'] ?? null;
        $payload->excludedNodes   = isset($params['excludedNodes']) ? (array) $params['excludedNodes'] : [];
        $payload->keptStringNodes = isset($params['keptStringNodes']) ? (array) $params['keptStringNodes'] : [];

        return new JsonResponse($this->getRecordService('ImportFeed')->getFileColumns($payload));
    }
}
