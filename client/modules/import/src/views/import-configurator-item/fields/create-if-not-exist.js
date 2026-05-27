/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/create-if-not-exist', ['views/fields/bool', 'import:views/import-configurator-item/fields/import-by'],
    (Dep, ImportBy) => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name change:entityIdentifier', () => {
                if (this.model.get('entityIdentifier')){
                    this.model.set(this.name, false);
                }
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let type = null;
            if (this.model.get('entity') && this.model.get('name')) {
                type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`);
            }

            if (type && ['file', 'image', 'asset', 'link', 'linkMultiple', 'measure'].includes(type) && !this.model.get('entityIdentifier')) {
                const $input = this.$el.find('input');

                let foreignEntity = ImportBy.prototype.getForeignEntity.call(this);

                if (['File'].includes(foreignEntity)) {
                    $input.attr('disabled', 'disabled');
                    this.model.set('createIfNotExist', (this.model.get('importBy') || []).includes('url'));
                } else {
                    $input.removeAttr('disabled');
                }
                this.show();
            } else {
                this.hide();
            }
        },

    })
);