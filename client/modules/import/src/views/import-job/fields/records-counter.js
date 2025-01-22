/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-job/fields/records-counter', 'views/fields/int',
    Dep => Dep.extend({

        listTemplate: 'import:fields/records-counter/detail',

        detailTemplate: 'import:fields/records-counter/detail',

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'importCounterChanged', () => {
                this.updateCounterData();
                this.reRender();
            } );
        },

        updateCounterData() {
            const counterData = this.model.get('lastCounterData');
            if (counterData) {
                this.model.set(this.name, counterData[this.name] || 0);
            }
        },

        getValueForDisplay: function() {
            if (this.model.get(this.name) === null) {
                this.updateCounterData();
            }

            return Dep.prototype.getValueForDisplay.call(this);
        }

    })
);
