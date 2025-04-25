/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-job/fields/errors-count', 'import:views/fields/int-with-link-to-list',
    Dep => Dep.extend({

        listScope: 'ImportJobLog',

        setup() {
            Dep.prototype.setup.call(this);

            this.filterName = 'importJobId';
        },

        getSearchFilter() {
            let nameHash = {};
            nameHash[this.model.id] = this.model.get('name');
            return {
                textFilter: '',
                primary: null,
                presetName: null,
                bool: {},
                queryBuilder: {
                    condition: 'AND',
                    rules: [
                        {
                            id: 'importJobId',
                            field: 'importJobId',
                            type: 'string',
                            operator: 'in',
                            value: [this.model.id],
                            data:{
                                nameHash: {
                                    [this.model.id]: this.model.get('name')
                                }
                            }
                        }
                    ],
                    valid: true
                },
                queryBuilderApplied: 'apply'
            };
        }

    })
);
