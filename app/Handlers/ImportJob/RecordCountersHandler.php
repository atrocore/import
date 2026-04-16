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

namespace Import\Handlers\ImportJob;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportJob/{id}/recordCounters',
    methods: [
        'GET',
    ],
    summary: 'Get record counters',
    description: 'Returns created/updated/deleted/skipped/error counters for the specified import job.',
    tag: 'ImportJob',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Import job record ID',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Record counters',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'id'           => [
                                'type' => 'string',
                            ],
                            'state'        => [
                                'type' => 'string',
                            ],
                            'createdCount' => [
                                'type' => 'integer',
                            ],
                            'updatedCount' => [
                                'type' => 'integer',
                            ],
                            'deletedCount' => [
                                'type' => 'integer',
                            ],
                            'skippedCount' => [
                                'type' => 'integer',
                            ],
                            'errorsCount'  => [
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'id is required',
        ],
        403 => [
            'description' => 'Access denied',
        ],
        404 => [
            'description' => 'Import job not found',
        ],
    ],
)]
class RecordCountersHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        if (empty($id)) {
            throw new BadRequest("'id' is required.");
        }

        return new JsonResponse($this->getRecordService('ImportJob')->getRecordCounters($id));
    }
}
