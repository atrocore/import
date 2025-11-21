/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/fields/int-with-link-to-list',
    ['import:views/import-job/fields/records-counter'],
    (Dep) => Dep.extend({

        listTemplate: 'import:fields/int-with-link-to-list/detail',

        detailTemplate: 'import:fields/int-with-link-to-list/detail',

        listScope: '',

        events: _.extend({
            'click [data-action="showList"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.actionShowList();
            }
        }, Dep.prototype.events),

        actionShowList() {
            const searchFilter = this.getSearchFilter();
            this.getStorage().set('listQueryBuilder', this.listScope, searchFilter);
            window.open(`#${this.listScope}`, '_blank');
        },

        getSearchFilter() {
            return {};
        }
    })
);
