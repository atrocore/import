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

namespace Import\Handlers\Action;

use Atro\Core\Routing\Route;
use Atro\Handlers\Action\AbstractActionTypeAsyncHandler;

#[Route(
    path: '/Action/{id}/importAsync',
    methods: [
        'POST',
    ],
    summary: 'Execute Import action asynchronously',
    description: 'Schedules the specified Import action as a background job and returns immediately with the job ID.',
    tag: 'Action',
    parameters: [
        [
            'name'        => 'id',
            'in'          => 'path',
            'required'    => true,
            'description' => 'Action record ID.',
            'schema'      => [
                'type' => 'string',
            ],
        ],
    ],
    requestBody: [
        'required' => false,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'entityId'     => [
                            'type'        => 'string',
                            'description' => 'Limit import scope to a specific entity record (by ID). The feed\'s where filter is overridden with a single-ID constraint.',
                        ],
                        'where'        => [
                            'type'        => 'array',
                            'description' => 'Filter conditions for mass import. Applied as the feed\'s scope filter.',
                            'items'       => ['type' => 'object'],
                        ],
                        'attachmentId' => [
                            'type'        => 'string',
                            'description' => 'ID of a previously uploaded file attachment to use as the import source instead of the feed\'s default source.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'The action has been scheduled as a background job.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'jobId' => [
                                'type'        => 'string',
                                'description' => 'ID of the created background job.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
)]
class ImportAsyncHandler extends AbstractActionTypeAsyncHandler
{
}
