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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportJob/{id}/importJobLogs',
    methods: ['GET'],
    summary: 'List import job error logs',
    description: 'Returns only error-type log entries linked to the specified import job.',
    tag: 'ImportJob',
    parameters: [
        ['name' => 'id',      'in' => 'path',  'required' => true,  'schema' => ['type' => 'string']],
        ['name' => 'offset',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 0]],
        ['name' => 'maxSize', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'example' => 50]],
        ['name' => 'sortBy',  'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
        ['name' => 'asc',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'boolean']],
    ],
    responses: [
        200 => ['description' => 'Collection of error log entries', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['total' => ['type' => 'integer'], 'list' => ['type' => 'array', 'items' => ['type' => 'object']]]]]]],
        404 => ['description' => 'Import job not found'],
    ],
)]
class ImportJobListImportJobLogsHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ImportJob', 'read')) {
            throw new Forbidden();
        }

        $routeResult = $request->getAttribute(RouteResult::class);
        $id          = (string) ($routeResult?->getMatchedParams()['id'] ?? '');

        if ($id === '') {
            throw new NotFound();
        }

        $params = $this->buildListParams($request);
        $params['where'][] = [
            'type'      => 'in',
            'attribute' => 'type',
            'value'     => ['error'],
        ];

        $result = $this->getRecordService('ImportJob')->findLinkedEntities($id, 'importJobLogs', $params);

        return new JsonResponse($this->buildListResult($result, $params));
    }
}
