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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportFeed/action/importData',
    methods: [
        'POST',
    ],
    summary: 'Import data',
    description: 'Imports data into the system using the specified feed code and JSON payload.',
    tag: 'ImportFeed',
    auth: false,
    requestBody: ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => [
        'code',
        'json',
    ], 'properties' => ['code' => [
        'type' => 'string',
    ], 'json' => [
        'type' => 'object',
    ]]]]]],
    responses: [
        200 => ['description' => 'Import accepted', 'content' => ['application/json' => ['schema' => [
            'type' => 'boolean',
        ]]]],
    ],
)]
class ImportDataHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'code') || !property_exists($data, 'json')) {
            throw new BadRequest();
        }

        $this->getRecordService('ImportFeed')->importData($data);

        return new BoolResponse(true);
    }
}
