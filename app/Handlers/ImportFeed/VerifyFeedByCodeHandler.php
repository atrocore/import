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
    path: '/ImportFeed/action/verifyFeedByCode',
    methods: [
        'GET',
    ],
    summary: 'Verify feed by code',
    description: 'Verifies an import feed by its code.',
    tag: 'ImportFeed',
    auth: false,
    parameters: [
        ['name' => 'code', 'in' => 'query', 'required' => true, 'schema' => [
            'type' => 'string',
        ]],
    ],
    responses: [
        200 => ['description' => 'Verification result', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['message' => [
            'type' => 'string',
        ]]]]]],
    ],
)]
class VerifyFeedByCodeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $code = $request->getQueryParams()['code'] ?? '';

        if (empty($code)) {
            throw new BadRequest();
        }

        return new JsonResponse(['message' => $this->getRecordService('ImportFeed')->verifyFeedByCode($code)]);
    }
}
