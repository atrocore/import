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
use Espo\ORM\EntityCollection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportJob/{id}/action/getRecordCounters',
    methods: ['GET'],
    summary: 'Get record counters',
    description: 'Returns created/updated/deleted/skipped/error counters for the specified import job.',
    tag: 'ImportJob',
    parameters: [
        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Record counters', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
        404 => ['description' => 'Import job not found'],
    ],
)]
class ImportJobGetRecordCountersHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ImportJob', 'read')) {
            throw new Forbidden();
        }

        $id = (string) $request->getAttribute('id');

        if ($id === '') {
            throw new NotFound();
        }

        $importJob = $this->getEntityManager()->getEntity('ImportJob', $id);
        if (empty($importJob)) {
            throw new NotFound();
        }

        $result = ['id' => $importJob->id, 'state' => $importJob->get('state')];
        $fields = ['createdCount', 'updatedCount', 'deletedCount', 'skippedCount', 'errorsCount'];

        foreach ($fields as $field) {
            if ($importJob->get($field) === null) {
                $this->getRecordService('ImportJob')->prepareCounts(new EntityCollection([$importJob]));
            }
            $result[$field] = $importJob->get($field) ?? 0;
        }

        if (!in_array($importJob->get('state'), ['Pending', 'Running'])) {
            $this->getEntityManager()->saveEntity($importJob);
        }

        return new JsonResponse($result);
    }
}
