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

use Atro\Core\EntityTypeHandlers\CreateHandler;
use Atro\Core\EntityTypeHandlers\CreateLinkHandler;
use Atro\Core\EntityTypeHandlers\ListHandler;
use Atro\Core\EntityTypeHandlers\ListLinkedHandler;
use Atro\Core\EntityTypeHandlers\MassDeleteHandler;
use Atro\Core\EntityTypeHandlers\MassUpdateHandler;
use Atro\Core\EntityTypeHandlers\MergeHandler;
use Atro\Core\EntityTypeHandlers\RemoveLinkHandler;
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

    public function getEntityTypeHandlerExcludes(): array
    {
        return [
            // ImportConfiguratorItem — all mutation and listing is managed via ImportFeed
            ListHandler::class       => ['ImportConfiguratorItem'],
            ListLinkedHandler::class => ['ImportConfiguratorItem'],
            MassUpdateHandler::class => ['ImportConfiguratorItem', 'ImportJob'],
            MassDeleteHandler::class => ['ImportConfiguratorItem'],
            CreateLinkHandler::class => ['ImportConfiguratorItem'],
            RemoveLinkHandler::class => ['ImportConfiguratorItem'],
            MergeHandler::class      => ['ImportConfiguratorItem'],
            // ImportJob — direct creation is not allowed; use /ImportFeed/action/runImport
            CreateHandler::class     => ['ImportJob'],
        ];
    }
}
