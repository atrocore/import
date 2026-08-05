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

        (new FailedImportTemplateSeeder($this->getConfig(), $this->getDbal()))->run();
    }

    public function afterDelete(): void
    {
        $this->removeNavigationItems(['ImportFeed']);
    }

    protected function getConfig(): \Atro\Core\Utils\Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getDbal(): \Doctrine\DBAL\Connection
    {
        return $this->getContainer()->get('dbal');
    }
}
