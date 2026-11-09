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

namespace Import\Migrations;

use Atro\Core\Migration\Base;

class V1Dot11Dot8 extends Base
{
    private const REFERENCE_DATA_DIR = 'data/reference-data';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-08-03 18:00:00');
    }

    public function up(): void
    {
        $this->createFailedImportTemplate();
    }

    private function createFailedImportTemplate(): void
    {
        $filePath = self::REFERENCE_DATA_DIR . DIRECTORY_SEPARATOR . 'EmailTemplate.json';

        $templates = file_exists($filePath) ? (json_decode(file_get_contents($filePath), true) ?? []) : [];

        $templateData = $this->getDefaultTemplateData();
        if (isset($templates[$templateData['code']])) {
            return;
        }

        $templates[$templateData['code']] = $templateData;

        @mkdir(self::REFERENCE_DATA_DIR);
        @file_put_contents($filePath, json_encode($templates));
    }

    private function getDefaultTemplateData(): array
    {
        return [
            'id'    => 'failedImportExecutions',
            'name'  => 'Failed Import Executions',
            'code'  => 'failed_import_executions',
            'subject' => 'Failed Import Executions',
            'isHtml' => true,
            'createdAt' => date('Y-m-d H:i:s'),
            'body' => "<style>
    * {
        font-family: sans-serif;
    }

    table, th, td {
        border: 1px solid #999;
        border-collapse: collapse;
    }

    th, td {
        text-align: center;
        padding: 15px;
    }

    th:first-of-type, 
    td:first-of-type {
        text-align: left;
    }
</style>

<h1>Failed Import Executions</h1>

{% set jobs = findEntities('ImportJob', {'state': 'Failed', 'createdAt>=': 'now' | date_modify('-1 days') | date('Y-m-d H:i:s')}) %}

{% set feeds = {} %}
{% set feedNames = {} %}
{% set total = 0 %}
{% for job in jobs %}
    {% set feeds = feeds|merge({(job.importFeedId): (feeds[job.importFeedId] ?? 0) + 1}) %}
    {% set total = total + 1 %}
{% endfor %}

<br/>

<table>
    <thead>
        <tr>
            <th>Feed</th>
            <th>Failed</th>
        </tr>
    </thead>
    <tbody>
    {% for feedId, count in feeds %}
        {% set feed = findEntityById('ImportFeed', feedId) %}
        <tr>
            <td><a href=\"{{ config.siteUrl }}/#ImportFeed/view/{{ feedId }}\">{{ feed.name ?? feedId }}</a></td>
            <td><b>{{ count }}</b></td>
        </tr>
    {% endfor %}
    {% if total > 0 %}
        <tr>
            <td><b>Total</b></td>
            <td><b>{{ total }}</b></td>
        </tr>
    {% endif %}
</table>
    ",
        ];
    }
}
