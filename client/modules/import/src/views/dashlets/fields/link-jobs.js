/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/dashlets/fields/link-jobs', 'import:views/fields/int-with-link-to-list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.listScope = 'ImportJob';
        },

        getSearchFilter() {
            let bool = {};

            switch (parseInt(this.model.attributes.interval)) {
                case 1:
                    bool.onlyImportFailed24Hours = true;
                    break;
                case 7:
                    bool.onlyImportFailed7Days = true;
                    break;
                default:
                    bool.onlyImportFailed28Days = true;
                    break;
            }

            return {
                textFilter: '',
                primary: null,
                bool: bool,
                queryBuilderApplied: false
            };
        }

    })
);
