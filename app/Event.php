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

        $tabList = $config->get("tabList", []);
        if (!in_array('ImportFeed', $tabList)) {
            $tabList[] = 'ImportFeed';
        }

        $config->set('tabList', $tabList);
        $config->save();
    }

    public function afterDelete(): void
    {
        /** @var Config $config */
        $config = $this->getContainer()->get('config');

        $tabList = [];
        foreach ($config->get("tabList", []) as $tab) {
            if ($tab != 'ImportFeed') {
                $tabList[] = $tab;
            }
        }

        $config->set('tabList', $tabList);
        $config->save();
    }
}
