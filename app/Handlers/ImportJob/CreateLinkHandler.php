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
    path: '/ImportJob/{id}/linkFile',
    methods: [
        'POST',
    ],
    summary: 'Attach file to import job',
    description: 'Links an uploaded file attachment to an existing import job.',
    tag: 'ImportJob',
    parameters: [
        [
            'name'     => 'id',
            'in'       => 'path',
            'required' => true,
            'schema'   => [
                'type' => 'string',
            ],
        ],
    ],
    responses: [
        200 => [
            'description' => 'Link created',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type' => 'boolean',
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'id is required',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class CreateLinkHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');

        if (!$this->getAcl()->check('ImportJob', 'edit')) {
            throw new Forbidden();
        }

        if (empty($id)) {
            throw new BadRequest();
        }

        $data = $this->getRequestBody($request);
        $this->getRecordService('ImportJob')->linkEntity($id, 'files', $data->id ?? '');

        return new BoolResponse(true);
    }
}
