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

namespace Import\Jobs;

use Atro\Entities\Job;
use Atro\Jobs\AbstractJob;
use Atro\Jobs\JobInterface;

class BackgroundFileParser extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        $data = $job->getPayload();
        if (empty($data['payload'])) {
            return;
        }

        $sourceFields = $this->getServiceFactory()->create('ImportFeed')
            ->getFileColumns(json_decode(json_encode($data['payload'])));

        $job->get('payload')->sourceFields = $sourceFields;
        $this->getEntityManager()->saveEntity($job, ['skipAll' => true]);
    }
}
