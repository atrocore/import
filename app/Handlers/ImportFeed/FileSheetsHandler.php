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
    path: '/ImportFeed/fileSheets',
    methods: [
        'GET',
    ],
    summary: 'Get Excel sheet names',
    description: 'Returns the sheet names for the given file. Only Excel format is supported; other formats return an empty array.',
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
    ],
    responses: [
        200 => [
            'description' => 'List of sheet names',
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
            'description' => 'fileId is required, the file does not exist, or the file is not a valid Excel file',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ]
)]
class FileSheetsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $fileId = $request->getQueryParams()['fileId'] ?? '';

        if (empty($fileId)) {
            throw new BadRequest();
        }

        return new JsonResponse($this->getRecordService('ImportFeed')->getFileSheets($fileId));
    }
}
