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

namespace Import\ProcessingTypes;

abstract class AbstractProcessingType
{
    abstract public static function getTypeLabel(): ?string;

    abstract public static function getDescription(): ?string;

    abstract public static function getEntityName(): string;
}
