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
    path: '/ImportFeed/verifyFeedByCode',
    methods: [
        'GET',
    ],
    summary: 'Verify feed by code',
    description: 'Checks whether an import feed is correctly configured for the given code. Returns the same message whether the code does not exist or the current user cannot access it, so codes cannot be enumerated.',
    tag: 'ImportFeed',
    parameters: [
        [
            'name'     => 'code',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Verification result',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'code is required',
        ],
    ],
)]
class VerifyFeedByCodeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $code = $request->getQueryParams()['code'] ?? '';

        if (empty($code)) {
            throw new BadRequest("'code' is required.");
        }

        return new JsonResponse(['message' => $this->getRecordService('ImportFeed')->verifyFeedByCode($code)]);
    }
}
