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

namespace Import\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Atro\Core\Utils\Config;

/**
 * Sets the number of days the import jobs are kept.
 */
class SetImportJobsMaxDays implements AfterInstallInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public static function getSortOrder(): int
    {
        return 10;
    }

    public function run(): void
    {
        $this->config->set('importJobsMaxDays', 21);
    }
}
