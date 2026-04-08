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
    path: '/ImportFeed/parseFileColumnsJob',
    methods: [
        'POST',
    ],
    summary: 'Parse file columns as background job',
    description: 'Queues a background job to parse the column headers from the uploaded file. Use for files larger than 2 MB. Poll GET /Job/{jobId} until status is "Success", then read sourceFields from the job payload.',
    tag: 'ImportFeed',
    responses: [
        200 => [
            'description' => 'Background job queued',
            'content'     => [
                'application/json' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'jobId' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        400 => [
            'description' => 'attachmentId is missing or the file does not exist',
        ],
        403 => [
            'description' => 'Access denied',
        ],
    ],
)]
class ParseFileColumnsJobHandler extends AbstractHandler
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->getAcl()->check('ImportFeed', 'read')) {
            throw new Forbidden();
        }

        $data = $this->getRequestBody($request);

        return new JsonResponse($this->getRecordService('ImportFeed')->queueFileColumnsParse($data));
    }
}
