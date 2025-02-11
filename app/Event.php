<?php

namespace Import;

use Atro\Core\ModuleManager\AfterInstallAfterDelete;
use Espo\Core\Utils\Config;

class Event extends AfterInstallAfterDelete
{
    public function afterInstall(): void
    {
        /** @var Config $config */
        $config = $this->getContainer()->get('config');
        $config->set('importJobsMaxDays', 21);
        $this->addNavigationItems(['ImportFeed']);
    }

    public function afterDelete(): void
    {
        $this->removeNavigationItems(['ImportFeed']);
    }
}
