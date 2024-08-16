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

namespace Import\Acl;

use Espo\Core\Acl\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;

class ImportJob extends Base
{
    public function checkEntity(User $user, Entity $entity, $data, $action)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($action == 'edit') {
            $action = 'read';
        }

        return parent::checkEntity($user, $entity, $data, $action);
    }

    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($action == 'edit') {
            $action = 'read';
        }

        return parent::checkScope($user, $data, $action, $entity, $entityAccessData);
    }
}
