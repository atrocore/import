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

namespace Import\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;
use Atro\Core\Utils\Language;
use Atro\DTO\QueueItemDTO;

class ImportJob extends Base
{
    public function actionGenerateFile($params, \stdClass $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'id') || !property_exists($data, 'type')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        $type = $data->type === 'convertedFile' ? 'converted' : $data->type;
        $name = $this->getLanguage()->translate('generateFile' . ucfirst($type), 'labels', 'ImportJob');

        $dto = new QueueItemDTO($name, 'ConvertedFileGenerator', [
            'type'        => $type,
            'importJobId' => $data->id,
        ]);
        $dto->setHash($data->id);

        return [
            'queueItemId' => $this->getContainer()->get('queueManager')->createQueueItem($dto)
        ];
    }

    public function actionGetImportJobsViaScope($params, $data, $request): array
    {
        if (!$request->isGet() || empty($request->get('scope'))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            return [];
        }

        return $this->getRecordService()->getImportJobsViaScope((string)$request->get('scope'));
    }

    public function actionReCreate($params, \stdClass $data, $request): bool
    {
        if (!$request->isPost() || !property_exists($data, 'id') || empty($data->id)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->reCreateImportJob((string)$data->id,
            property_exists($data, 'attachmentId') ? $data->attachmentId : null);
    }

    /**
     * @inheritDoc
     */
    public function actionListLinked($params, $data, $request)
    {
        if ($params['link'] == 'importJobLogs') {
            $where = $request->get('where');
            $where[] = [
                'type'      => 'in',
                'attribute' => 'type',
                'value'     => ['error']
            ];
            $request->setQuery('where', $where);
        }

        return parent::actionListLinked($params, $data, $request);
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionCreate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionMassUpdate($params, $data, $request)
    {
        throw new NotFound();
    }

    /**
     * @inheritDoc
     *
     * @throws NotFound
     */
    public function actionCreateLink($params, $data, $request)
    {
        throw new NotFound();
    }

    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }
}
