<?php

namespace Import;

use Atro\Core\ModuleManager\AfterInstallAfterDelete;
use Espo\Core\Utils\Config;
use Import\Seeders\FailedImportTemplateSeeder;

class Event extends AfterInstallAfterDelete
{
    public function afterInstall(): void
    {
        /** @var Config $config */
        $config = $this->getContainer()->get('config');
        $config->set('importJobsMaxDays', 21);
        $this->addNavigationItems(['ImportFeed']);

        (new FailedImportTemplateSeeder($config, $this->getContainer()->get('connection')))->run();
    }

    public function afterDelete(): void
    {
        $this->removeNavigationItems(['ImportFeed']);
    }
}
