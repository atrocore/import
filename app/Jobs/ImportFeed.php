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

class ImportFeed extends AbstractJob implements JobInterface
{
    public function run(Job $job): void
    {
        if (empty($job->get('scheduledJobId'))) {
            return;
        }

        $scheduledJob = $this->getEntityManager()->getEntity('ScheduledJob', $job->get('scheduledJobId'));
        if (empty($scheduledJob) || empty($scheduledJob->get('importFeedId'))) {
            return;
        }

        $this->getServiceFactory()->create('ImportFeed')->runImport($scheduledJob->get('importFeedId'), '');
    }
}
