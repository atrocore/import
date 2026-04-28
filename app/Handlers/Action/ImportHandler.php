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
    path: '/Action/{id}/import',
    methods: [
        'POST',
    ],
    summary: 'Execute Import action',
    description: 'Executes the specified Import action synchronously. Runs the configured import feed.',
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
                            'description' => 'ID of the entity record that provides context for condition evaluation and template rendering. Its fields are accessible as `{{ entity.* }}` in Twig expressions. Does not select which records the action operates on — use `where` for that.',
                        ],
                        'where'        => [
                            'type'        => 'array',
                            'description' => 'Standard AtroCore filter conditions that select the records the action operates on. The action runs once for every matching record.',
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
            'description' => 'Execution result.',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'success' => [
                                'type' => 'boolean',
                            ],
                            'message' => [
                                'type'     => 'string',
                                'nullable' => true,
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
class ImportHandler extends AbstractActionTypeSyncHandler
{
}
