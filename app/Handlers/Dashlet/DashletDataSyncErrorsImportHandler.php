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

namespace Import\Handlers\Dashlet;

use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/Dashlet/dataSyncErrorsImport',
    methods: ['GET'],
    summary: 'Get import data sync errors dashlet data',
    description: 'Returns failed import job counts grouped by time interval (1, 7, 28 days).',
    tag: 'Dashlet',
    responses: [
        200 => [
            'description' => 'Dashlet data',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'total' => [
                                'type'        => 'integer',
                                'description' => 'Total number of rows',
                            ],
                            'list'  => [
                                'type'        => 'array',
                                'description' => 'Import feed error rows grouped by interval',
                                'items'       => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'id'       => [
                                            'type'        => 'string',
                                            'description' => 'Row identifier',
                                        ],
                                        'name'     => [
                                            'type'        => 'string',
                                            'description' => 'Import feed name',
                                        ],
                                        'feeds'    => [
                                            'type'        => 'integer',
                                            'description' => 'Number of import feeds with errors',
                                        ],
                                        'jobs'     => [
                                            'type'        => 'integer',
                                            'description' => 'Number of failed import jobs',
                                        ],
                                        'interval' => [
                                            'type'        => 'integer',
                                            'description' => 'Time interval in days (1, 7 or 28)',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class DashletDataSyncErrorsImportHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return new JsonResponse($this->getServiceFactory()->create('DataSyncErrorsImportDashlet')->getDashlet());
    }
}
