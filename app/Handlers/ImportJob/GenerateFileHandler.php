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
    path: '/ImportJob/{id}/generateFile',
    methods: [
        'POST',
    ],
    summary: 'Generate file',
    description: 'Queues a job to generate the converted or error file for the specified import job.',
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
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'type',
                    ],
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => [
                                'errors',
                                'skippedByScript',
                                'skippedBySystem',
                                'deleted',
                                'updated',
                                'created',
                                'convertedFile',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Queue item ID',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'queueItemId' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'id and type are required',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class GenerateFileHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        if (empty($id)) {
            throw new BadRequest("'id' is required.");
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'type') || empty($data->type)) {
            throw new BadRequest("'type' is required.");
        }

        $queueItemId = $this->getRecordService('ImportJob')->generateFile($id, (string) $data->type);

        return new JsonResponse(['queueItemId' => $queueItemId]);
    }
}
