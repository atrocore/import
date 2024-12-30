/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/attribute-value', 'views/fields/enum', Dep => Dep.extend({

    setup() {
        Dep.prototype.setup.call(this);

        this.listenTo(this.model, 'change:name change:type change:attributeData', () => {
            this.setupOptions();
            this.model.set('attributeValue', this.params.options[0] ?? null);
            this.reRender();
        });
    },

    afterRender() {
        Dep.prototype.afterRender.call(this);

        if (this.mode !== 'list') {
            this.checkFieldVisibility();
        }
    },

    checkFieldVisibility() {
        if (this.isRequired()) {
            this.show();
        } else {
            this.hide();
        }
    },

    setupOptions() {
        this.params.options = [];
        this.translatedOptions = {};

        if (this.isRequired()) {
            const type = this.getType();
            this.params.options = ['value'];
            if (['rangeFloat', 'rangeInt'].includes(type)) {
                this.params.options = ['valueFrom', 'valueTo']
                if (this.hasUnit()) {
                    this.params.options.push('valueUnitId')
                }
            } else if (['float', 'int', 'varchar'].includes(type)) {
                if (this.hasUnit()) {
                    this.params.options.push('valueMain')
                    this.params.options.push('valueUnitId')
                }
            }

            this.params.options.forEach(option => {
                if (option === 'valueMain') {
                    let translatedType = this.getLanguage().translate(type, 'fieldTypes', 'Admin')
                    this.translatedOptions[option] = this.getLanguage().translateOption(option, 'attributeValue', 'ImportConfiguratorItem').replace('%s', translatedType);
                } else {
                    this.translatedOptions[option] = this.getLanguage().translateOption(option, 'attributeValue', 'ImportConfiguratorItem');
                }
            });
        }
    },

    isRequired() {
        return this.model.get('type') === 'Attribute' && this.model.get('attributeData');
    },

    getType() {
        if (this.model.get('attributeData')) {
            return this.model.get('attributeData').type;
        }

        return 'varchar';
    },

    hasUnit() {
        return this.model.get('attributeData') && this.model.get('attributeData').measureId
    },

}));