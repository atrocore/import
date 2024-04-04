/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/import-by', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.validations = Espo.Utils.clone(this.validations);
            if (!this.validations.includes('columns')) {
                this.validations.push('columns');
            }

            this.prepareImportByOptions();
            this.listenTo(this.model, 'change:name change:type change:attributeData change:attributeValue', () => {
                this.model.set('importBy', null);
                this.prepareImportByOptions(() => {
                    this.reRender();
                });
            });
            this.listenTo(this.model, 'change:defaultId change:defaultIds', () => {
                this.reRender();
            });
        },

        getForeignEntity() {
            let type = null;
            let attribute = null;
            if (this.model.get('type') === 'Attribute' && this.model.get('attributeData')) {
                attribute = this.model.get('attributeData');
                type = attribute.type;
            } else if (this.model.get('entity') && this.model.get('name')) {
                type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`);
            }

            let foreignEntity = null;
            if (type === 'file') {
                foreignEntity = 'File';
            } else if (type === 'measure') {
                foreignEntity = 'Unit'
            } else if (type && ['extensibleEnum', 'extensibleMultiEnum'].includes(type)) {
                foreignEntity = 'ExtensibleEnumOption';
            } else if (this.model.get('name') === 'mainImage' || ['Product', 'Category'].includes(this.model.get('entity')) && this.model.get('name') === 'image') {
                foreignEntity = 'File';
            } else if (attribute) {
                foreignEntity = attribute.entityType;
                if (attribute.measureId && this.model.get('attributeValue') === 'valueUnitId') {
                    foreignEntity = 'Unit';
                }
            } else if (this.model.get('entity') === 'ProductAttributeValue' && this.model.get('name') === 'value') {
                foreignEntity = 'ExtensibleEnumOption';
            } else if (this.model.get('entity') === 'ProductAttributeValue' && this.model.get('name') === 'valueUnitId') {
                foreignEntity = 'Unit';
            } else {
                foreignEntity = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.entity`) || this.getMetadata().get(`entityDefs.${this.model.get('entity')}.links.${this.model.get('name')}.entity`);
            }

            return foreignEntity;
        },

        getImportByOptions() {
            let translatedOptions = {};
            let foreignEntity = this.getForeignEntity();
            if (foreignEntity) {
                translatedOptions['id'] = this.translate('id', 'fields', 'Global');
                $.each(this.getMetadata().get(`entityDefs.${foreignEntity}.fields`) || {}, (name, data) => {
                    if (
                        data.type
                        && ['bool', 'enum', 'varchar', 'email', 'float', 'int', 'text', 'wysiwyg'].includes(data.type)
                        && !data.disabled
                        && !data.importDisabled
                    ) {
                        translatedOptions[name] = this.translate(name, 'fields', foreignEntity);
                    }
                });
            }

            return translatedOptions;
        },

        prepareImportByOptions(callback) {
            this.params.options = [];
            this.translatedOptions = {};

            $.each(this.getImportByOptions(), (name, label) => {
                this.params.options.push(name);
                this.translatedOptions[name] = label;
            });

            if (callback) {
                callback();
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.params.options.length) {
                this.show();
            } else {
                this.hide();
            }

            if (this.model.get('entity') === 'ProductAttributeValue' && this.model.get('name') === 'value') {
                this.$el.append(`<span style="color: #999; font-size: 12px">${this.translate('importByForAttributeValue', 'labels', 'ImportConfiguratorItem')}</span>`)
                this.$el.closest('.cell').find('.label-text').text(this.translate('importByForListAttribute', 'fields', 'ImportConfiguratorItem'))
            } else {
                this.$el.closest('.cell').find('.label-text').text(this.translate('importBy', 'fields', 'ImportConfiguratorItem'))
            }
        },

        validateColumns() {
            let validate = false;

            const columns = (this.model.get('column') || []).length,
                fields = (this.model.get(this.name) || []).length;

            if ((columns > 1 && fields !== columns) || (columns === 1 && fields < 1)) {
                this.showValidationMessage(this.translate('wrongFieldsNumber', 'exceptions', 'ImportConfiguratorItem'), this.$el);
                validate = true;
            }

            return validate;
        },

    })
);