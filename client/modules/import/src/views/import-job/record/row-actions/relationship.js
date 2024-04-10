/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('import:views/import-job/record/row-actions/relationship', 'views/record/row-actions/relationship', Dep => {

    return Dep.extend({

        getActionList() {
            let list = [];
            if (['Pending', 'Running'].includes(this.model.get('state')) && this.options.acl.edit) {
                list.push({
                    action: 'cancelImportJob',
                    label: 'Cancel',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (['Failed', 'Canceled'].includes(this.model.get('state')) && this.options.acl.edit) {
                list.push({
                    action: 'tryAgainImportJob',
                    label: 'tryAgain',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (this.options.acl.delete) {
                list.push({
                    action: 'removeRelated',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        }

    });

});