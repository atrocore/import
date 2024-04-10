/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/replace-array', 'views/fields/bool',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.reRender();
            });

            this.listenTo(this.model, 'change:importBy', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`);
            if (type === 'linkMultiple' || type === 'array' || type === 'multiEnum' || type === 'extensibleMultiEnum') {
                this.show();
            } else {
                this.hide();
            }
        },

    })
);