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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportFeed/action/getFileSheets',
    methods: ['POST'],
    summary: 'Get file sheets',
    description: 'Returns the list of sheets from the provided file for an import feed.',
    tag: 'ImportFeed',
    responses: [
        200 => ['description' => 'List of sheets', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['type' => 'string']]]]],
    ],
)]
class GetFileSheetsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ImportFeed', 'read')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        return new JsonResponse($this->getRecordService('ImportFeed')->getFileSheets($data));
    }
}
