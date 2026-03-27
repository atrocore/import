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
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportJob/action/reCreate',
    methods: [
        'POST',
    ],
    summary: 'Re-create import job',
    description: 'Re-creates an import job from an existing one, optionally with a new attachment.',
    tag: 'ImportJob',
    requestBody: ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => [
        'id',
    ], 'properties' => ['id' => [
        'type' => 'string',
    ], 'attachmentId' => [
        'type' => 'string',
    ]]]]]],
    responses: [
        200 => ['description' => 'Job re-created', 'content' => ['application/json' => ['schema' => [
            'type' => 'boolean',
        ]]]],
    ],
)]
class ReCreateHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ImportJob', 'create')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        if (!property_exists($data, 'id') || empty($data->id)) {
            throw new BadRequest();
        }

        $attachmentId = property_exists($data, 'attachmentId') ? $data->attachmentId : null;

        return new BoolResponse($this->getRecordService('ImportJob')->reCreateImportJob((string) $data->id, $attachmentId));
    }
}
