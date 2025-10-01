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

use Atro\Core\Templates\Controllers\Base;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Slim\Http\Request;

class ImportFeed extends Base
{
    public function actionParseFileColumns($params, $data, Request $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->parseFileColumns($data);
    }

    public function actionGetFileSheets($params, $data, Request $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }
        return $this->getRecordService()->getFileSheets($data);
    }

    public function actionRunImport($params, $data, Request $request): bool
    {
        // checking request
        if (!$request->isPost() || !property_exists($data, 'importFeedId')) {
            throw new BadRequest();
        }

        // checking rules
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (!$this->getAcl()->check('ImportJob', 'create')) {
            throw new Forbidden();
        }

        $attachmentId = property_exists($data, 'attachmentId') ? (string)$data->attachmentId : '';

        return $this->getRecordService()->runImport((string)$data->importFeedId, $attachmentId);
    }

    public function actionCreateFromExport($params, $data, Request $request)
    {
        if (!$this->getMetadata()->isModuleInstalled('Export')) {
            throw new Forbidden();
        }

        if (!$request->isPost() || !property_exists($data, 'exportFeedId')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        $importFeed = $this->getRecordService()->createFromExportFeed($data->exportFeedId);

        return ["id" => $importFeed->id];
    }

    public function actionVerifyFeedByCode($params, $data, Request $request)
    {
        if (!$request->isGet() || empty($request->get("code"))) {
            throw new BadRequest();
        }
        return ['message' => $this->getRecordService()->verifyFeedByCode($request->get('code'))];
    }

    public function actionImportData($params, $data, Request $request)
    {
        if (!$request->isPost() || !property_exists($data, 'code') || !property_exists($data, 'json')) {
            throw new BadRequest();
        }

        $this->getRecordService()->importData($data);
        return true;
    }

    /* For backward compatibility */
    public function actionEasyCatalogVerifyCode($params, $data, Request $request)
    {
        return $this->actionVerifyFeedByCode($params, $data, $request);
    }

    /* For backward compatibility */
    public function actionEasyCatalog($params, $data, Request $request)
    {
        return $this->actionImportData($params, $data, $request);
    }


}
