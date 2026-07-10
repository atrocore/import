/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/entity-identifier', 'views/fields/bool',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.onModelReady(() => {
                this.listenTo(this.model, 'change:name change:entityAttributeId', () => {
                    this.reRender();
                });
            })
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const readOnlyTypes = ['file', 'linkMultiple', 'jsonObject'];

            if (this.model.get('entityAttributeId')) {
                this.isListView() ? this.setReadOnly() : this.hide();
                return;
            }

            const type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`) || 'varchar';
            if (this.isListView() && readOnlyTypes.includes(type)) {
                this.setReadOnly();
                return;
            }

            if (readOnlyTypes.includes(type)) {
                this.hide();
            } else {
                this.show();
            }
        },
    })
);