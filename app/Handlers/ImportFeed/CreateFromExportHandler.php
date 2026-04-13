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
    path: '/ImportFeed/createFromExport',
    methods: [
        'POST',
    ],
    summary: 'Create import feed from export feed',
    description: 'Creates a new simple import feed by mirroring the column mapping of an existing export feed. Only simple-type export feeds are supported. Requires the Export module to be installed.',
    tag: 'ImportFeed',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'exportFeedId',
                    ],
                    'properties' => [
                        'exportFeedId' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Created import feed ID',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'exportFeedId is required or the export feed is not of simple type',
        ],
        403 => [
            'description' => 'Access denied or Export module is not installed',
        ],
        404 => [
            'description' => 'Export feed not found',
        ],
    ],
)]
class CreateFromExportHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'exportFeedId')) {
            throw new BadRequest();
        }

        $importFeed = $this->getRecordService('ImportFeed')->createFromExportFeed($data->exportFeedId);

        return new JsonResponse(['id' => $importFeed->id]);
    }
}
