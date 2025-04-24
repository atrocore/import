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

            this.listenTo(this.model, 'change:type change:name change:entityAttributeId', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                this.hide();
                if (this.model.get('type') === 'Field' && this.model.get('name') && !this.model.get('entityAttributeId')) {
                    let type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`) || 'varchar';
                    if (!['image', 'asset', 'linkMultiple', 'jsonObject'].includes(type)) {
                        this.show();
                    }
                }
            }
        },
    })
);