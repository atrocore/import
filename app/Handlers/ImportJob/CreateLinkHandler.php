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
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Http\Response\BoolResponse;
use Atro\Core\Routing\Route;
use Atro\Handlers\AbstractHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route(
    path: '/ImportJob/{id}/{link}',
    methods: ['POST'],
    summary: 'Create link for import job',
    description: 'Only the "files" link is supported.',
    tag: 'ImportJob',
    parameters: [
        ['name' => 'id',   'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
        ['name' => 'link', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
    ],
    responses: [
        200 => ['description' => 'Link created', 'content' => ['application/json' => ['schema' => ['type' => 'boolean']]]],
        404 => ['description' => 'Link not supported'],
    ],
)]
class CreateLinkHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id   = (string) $request->getAttribute('id');
        $link = (string) $request->getAttribute('link');

        if ($link !== 'files') {
            throw new NotFound();
        }

        if (!$this->getAcl()->check('ImportJob', 'edit')) {
            throw new Forbidden();
        }

        if (empty($id)) {
            throw new BadRequest();
        }

        $data = $this->getRequestBody($request);
        $this->getRecordService('ImportJob')->linkEntity($id, $link, $data->id ?? '');

        return new BoolResponse(true);
    }
}
