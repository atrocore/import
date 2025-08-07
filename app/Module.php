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

namespace Import;

use Atro\Core\ModuleManager\AbstractModule;
use Espo\Core\Utils\Util;
use Import\Console\CreateImportProcessingType;

/**
 * Class Module
 */
class Module extends AbstractModule
{
    /**
     * @inheritdoc
     */
    public static function getLoadOrder(): int
    {
        return 5110;
    }

    public static function afterUpdate(): void
    {
        \Import\Jobs\ImportTypeSimple::clearCache();
        Util::removeDir(\Import\Services\ImportFeed::TMP_DIR);
    }

    public function getConsoleCommands(): array
    {
        return [
            "create import processing type <className>" => CreateImportProcessingType::class
        ];
    }
}
