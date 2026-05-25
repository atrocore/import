/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/value-extractor', 'views/fields/varchar',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.setDefaultValue()
                this.reRender()
            })
        },

        setDefaultValue() {
            if (this.isVisible()) {
                const type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`)
                if (type === 'link') {
                    this.model.set('valueExtractor', '(\\S+)$')
                } else if (['int', 'float'].includes(type)) {
                    this.model.set('valueExtractor', '[\\d\\s.,]+(?=\\s\\S+|$)')
                }
            } else {
                this.model.set('valueExtractor', null)
            }
        },

        isVisible() {
            let measureId = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measureId`);

            return !!(measureId &&
                (!this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.combinedField`) || this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.attributeId`)));
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.isVisible()) {
                this.show();
            } else {
                this.hide();
            }
        },

    })
);