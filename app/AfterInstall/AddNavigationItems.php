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
use Atro\Core\ModuleManager\Navigation;

/**
 * Adds the entities of the module to the navigation.
 */
class AddNavigationItems implements AfterInstallInterface
{
    private const MENU_ITEMS = [
        'ImportFeed'
    ];

    public function __construct(private readonly Navigation $navigation)
    {
    }

    public static function getSortOrder(): int
    {
        return 20;
    }

    public function run(): void
    {
        $this->navigation->addItems(self::MENU_ITEMS);
    }
}
