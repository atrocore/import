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
use Atro\Handlers\Action\AbstractActionTypeSyncHandler;

#[Route(
    path: '/Action/{id}/reImportFailedJob',
    methods: [
        'POST',
    ],
    summary: 'Execute Re-import failed job action',
    description: 'Executes the specified Re-import failed job action synchronously. Retries every matching ImportJob currently in the Failed state.',
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
            'description' => 'Execution result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'success' => [
                                'type'        => 'boolean',
                                'description' => 'Whether at least one matching ImportJob was successfully retried.',
                            ],
                            'message' => [
                                'type'        => 'string',
                                'nullable'    => true,
                                'description' => 'Optional status message.',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        404 => [
            'description' => 'Action record not found.',
        ],
    ],
)]
class ReImportFailedJobHandler extends AbstractActionTypeSyncHandler
{
}
