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
    path: '/ImportJob/getImportJobsViaScope',
    methods: [
        'GET',
    ],
    summary: 'Get import jobs via scope',
    description: 'Returns import jobs filtered by the specified entity scope.',
    tag: 'ImportJob',
    parameters: [
        [
            'name'     => 'scope',
            'in'       => 'query',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'List of import jobs',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'object',
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'scope is required',
        ],
    ],
)]
class GetImportJobsViaScopeHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $scope = $request->getQueryParams()['scope'] ?? '';

        if (empty($scope)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('ImportJob', 'read')) {
            return new JsonResponse([]);
        }

        return new JsonResponse($this->getRecordService('ImportJob')->getImportJobsViaScope($scope));
    }
}
