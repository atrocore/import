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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportJob/action/generateFile',
    methods: [
        'POST',
    ],
    summary: 'Generate file',
    description: 'Queues a job to generate the converted or error file for the specified import job.',
    tag: 'ImportJob',
    requestBody: ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => [
        'id',
        'type',
    ], 'properties' => ['id' => [
        'type' => 'string',
    ], 'type' => [
        'type' => 'string',
    ]]]]]],
    responses: [
        200 => ['description' => 'Queue item ID', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['queueItemId' => [
            'type' => 'string',
        ]]]]]],
    ],
)]
class GenerateFileHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ImportJob', 'read')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'id') || !property_exists($data, 'type')) {
            throw new BadRequest();
        }

        $type = $data->type === 'convertedFile' ? 'converted' : $data->type;
        $name = $this->getLanguage()->translate('generateFile' . ucfirst($type), 'labels', 'ImportJob');

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'    => $name,
            'type'    => 'ConvertedFileGenerator',
            'payload' => [
                'type'        => $type,
                'importJobId' => $data->id,
            ],
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);

        return new JsonResponse(['queueItemId' => $jobEntity->get('id')]);
    }
}
