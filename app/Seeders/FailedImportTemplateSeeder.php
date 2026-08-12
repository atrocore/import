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

namespace Import\Seeders;

use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Seeders\AbstractSeeder;

class FailedImportTemplateSeeder extends AbstractSeeder
{
    public function run(): void
    {
        $filePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'EmailTemplate.json';

        $templates = file_exists($filePath) ? (json_decode(file_get_contents($filePath), true) ?? []) : [];

        $templateData = $this->getDefaultTemplateData();
        if (isset($templates[$templateData['code']])) {
            return;
        }

        $templates[$templateData['code']] = $templateData;

        @mkdir(ReferenceData::DIR_PATH);
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
