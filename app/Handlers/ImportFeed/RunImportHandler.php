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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportFeed/runImport',
    methods: [
        'POST',
    ],
    summary: 'Run import',
    description: 'Triggers an import job for the specified import feed.',
    tag: 'ImportFeed',
    requestBody: [
        'required' => true,
        'content'  => [
            'application/json' => [
                'schema' => [
                    'type'       => 'object',
                    'required'   => [
                        'importFeedId',
                    ],
                    'properties' => [
                        'importFeedId' => [
                            'type' => 'string',
                        ],
                        'attachmentId' => [
                            'type'     => 'string',
                            'nullable' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Import started',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'importFeedId is required',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class RunImportHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ImportFeed', 'read')) {
            throw new Forbidden();
        }

        if (!$this->getAcl()->check('ImportJob', 'create')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'importFeedId')) {
            throw new BadRequest();
        }

        $attachmentId = property_exists($data, 'attachmentId') ? (string) $data->attachmentId : '';

        return new BoolResponse($this->getRecordService('ImportFeed')->runImport((string) $data->importFeedId, $attachmentId));
    }
}
