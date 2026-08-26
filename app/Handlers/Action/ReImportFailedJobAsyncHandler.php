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
    path: '/Action/{id}/reImportFailedJobAsync',
    methods: [
        'POST',
    ],
    summary: 'Execute Re-import failed job action asynchronously',
    description: 'Schedules the specified Re-import failed job action as a background job and returns immediately with the job ID.',
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
                        'entityId' => [
                            'type'        => 'string',
                            'description' => 'ID of a single ImportJob record to retry. Only used when the action is configured to apply to preselected records.',
                        ],
                        'where'    => [
                            'type'        => 'array',
                            'description' => 'Standard AtroCore filter conditions that select the ImportJob records to retry. Only used when the action is configured to apply to preselected records.',
                            'items'       => ['type' => 'object'],
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
class ReImportFailedJobAsyncHandler extends AbstractActionTypeAsyncHandler
{
}
