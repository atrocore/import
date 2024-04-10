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

        getSearchFilter() {
            return {
                textFilter: '',
                primary: null,
                presetName: null,
                bool: {},
                advanced: {
                    'importJob-1': {
                        type: 'equals',
                        field: 'importJobId',
                        value: this.model.id,
                        data: {
                            type: 'is',
                            idValue:  this.model.id,
                            nameValue: this.model.get('name')
                        }
                    }
                }
            };
        }

    })
);
