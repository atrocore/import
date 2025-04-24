/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/default-container', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'import:import-configurator-item/fields/value-container/base',

        editTemplate: 'import:import-configurator-item/fields/value-container/base',

        typesWithDefaultHash: ['password', 'text', 'varchar'],

        setup() {
            this.defs = this.options.defs || {};
            this.name = this.options.name || this.defs.name;
            this.params = this.options.params || this.defs.params || {};

            this.createDefaultField();

            if (this.mode === 'edit') {
                this.listenTo(this.model, 'change:attributeData', () => {
                    if (this.model.get('attributeData')) {
                        this.clearDefaultField();
                        this.createDefaultField();
                    }
                });

                this.listenTo(this.model, 'change:name change:attributeValue change:createIfNotExist', () => {
                    this.clearDefaultField();
                    this.createDefaultField();
                    this.reRender();
                });

                this.listenTo(this.model, 'change:defaultId change:defaultIds', () => {
                    if (!this.model.get('defaultId') && !this.model.get('defaultIds')) {
                        this.model.set('default', null);
                    }
                });
            }
        },

        clearDefaultField() {
            this.model.set('default', null);

            if (this.model.attributes.defaultCurrency) {
                delete this.model.attributes.defaultCurrency;
            }

            if (this.model.attributes.defaultId) {
                delete this.model.attributes.defaultId;
            }

            if (this.model.attributes.defaultName) {
                delete this.model.attributes.defaultName;
            }

            if (this.model.attributes.defaultIds) {
                delete this.model.attributes.defaultIds;
            }

            if (this.model.attributes.defaultNames) {
                delete this.model.attributes.defaultNames;
            }

            if (this.model.defs.links.default) {
                delete this.model.defs.links.default;
            }

            // clear view
            this.clearView('default');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();
            if (this.model.get('type') === 'Field' && this.model.get('name')) {
                this.$el.parent().show();
            }

            if (this.model.get('type') === 'Attribute' && this.model.get('attributeId') && this.model.get('attributeData')) {
                this.$el.parent().show();
            }
        },

        prepareDefaultModel(type, options) {
            if (['link', 'linkMultiple'].includes(type)) {
                let linkEntity = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.entity`) || this.getMetadata().get(`entityDefs.${this.model.get('entity')}.links.${this.model.get('name')}.entity`);
                this.model.defs.links["default"] = {
                    type: type === 'link' ? 'belongsTo' : 'hasMany',
                    entity: linkEntity
                };
            } else if (['enum', 'multiEnum', 'array', 'language'].includes(type)) {
                this.params.groupOptions = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.groupOptions`)
                this.params.options = options;
                this.params.translatedOptions = {};
                options.forEach(option => {
                    let label = this.getLanguage().translateOption(option, this.model.get('name'), this.model.get('entity'));
                    if (option === label) {
                        label = this.translate(option, 'labels', this.model.get('entity'));
                    }
                    this.params.translatedOptions[option.toString()] = label;
                });

                if (type === 'language') {
                    this.model.defs.fields["default"]['prohibitedEmptyValue'] = true;
                }
            } else if (type === 'unit') {
                this.params.measureId = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measureId`);
                this.model.defs.fields["default"]['extensibleEnumId'] = this.params.measureId;
            } else if (type === 'extensibleEnum' || type === 'extensibleMultiEnum') {
                this.params.extensibleEnumId = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.extensibleEnumId`);
                this.model.defs.fields["default"]['extensibleEnumId'] = this.params.extensibleEnumId;
            } else if (type === 'measure') {
                this.params.measureId = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measureId`);
                this.model.defs.fields["default"]['measureId'] = this.params.measureId;
            } else if (type === 'varchar') {
                if (this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.unitField`)) {
                    this.params.measureId = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measureId`);
                    this.model.defs.fields["default"]['measureId'] = this.params.measureId;
                }
            }

            if (this.model.get('type') === 'Attribute' && this.model.get('attributeId')) {
                let attribute = null;
                if (this.model.get('attributeData')) {
                    attribute = this.model.get('attributeData');
                } else {
                    this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`, null, {async: false}).then(res => {
                        attribute = res;
                    });
                }

                if (attribute) {
                    if (attribute.measureId) {
                        this.params.measureId = attribute.measureId;
                        this.model.defs.fields["default"]['extensibleEnumId'] = this.params.measureId;
                    }

                    if (attribute.extensibleEnumId) {
                        this.params.extensibleEnumId = attribute.extensibleEnumId;
                        this.model.defs.fields["default"]['extensibleEnumId'] = this.params.extensibleEnumId;
                    }

                    if (attribute.type === 'link') {
                        this.model.defs.links["default"] = {
                            type: 'belongsTo',
                            entity: attribute.entityType
                        };
                    }
                }
            }
        },

        createDefaultField() {
            let type = 'varchar';

            let options = [];

            if (this.model.get('type') === 'Field') {
                if (!this.model.get('name')) {
                    return;
                }
                type = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`) || 'varchar';
                options = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.options`) || [];
                if (type === 'bool') {
                    this.params.notNull = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.notNull`);
                }
            }

            if (this.model.get('type') === 'Attribute') {
                if (!this.model.get('attributeId') || !this.model.get('attributeData')) {
                    return;
                }
                type = this.model.get('attributeData').type;
                if (type === 'rangeInt') {
                    type = 'int'
                } else if (type === 'rangeFloat') {
                    type = 'float'
                } else if (type === 'currency') {
                    type = 'float'
                }
                if (this.model.get('attributeValue') === 'valueUnitId') {
                    type = 'unit'
                }

                if (type === 'bool') {
                    this.params.notNull = !!this.model.get('attributeData').notNull;
                }
            }

            this.prepareDefaultModel(type, options);

            if (['file'].includes(this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.type`))) {
                type = 'link';
                this.model.defs.links["default"] = {
                    type: 'belongsTo',
                    entity: 'File'
                };
            }

            /**
             * For Main Image
             */
            if (this.model.get('name') === 'mainImage' || ['Product', 'Category'].includes(this.model.get('entity')) && this.model.get('name') === 'image') {
                type = 'link';
                this.model.defs.links["default"] = {
                    type: 'belongsTo',
                    entity: 'File'
                };
            }

            let viewName = this.getFieldManager().getViewName(type);
            if (type === 'unit') {
                viewName = 'views/fields/unit-link'
            } else if (type === 'extensibleEnum') {
                viewName = 'views/admin/field-manager/fields/extensible-enum-default';
            } else if (type === 'extensibleMultiEnum') {
                viewName = 'views/admin/field-manager/fields/extensible-multi-enum-default';
            } else if (type === 'varchar') {
                if (this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.unitField`)) {
                    viewName = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.view`);
                }
            }

            if (this.model.get('type') === 'Attribute'
                && this.model.get('attributeValue') === 'value'
                && ['int', 'float', 'varchar'].includes(type)
                && this.model.get('attributeData').measureId !== null) {
                viewName = "views/fields/unit-" + type;
            }

            this.createView('default', viewName, {
                el: `${this.options.el} > .field[data-name="default"]`,
                model: this.model,
                name: 'default',
                mode: this.mode,
                defs: this.defs,
                params: this.params,
                inlineEditDisabled: true,
                createDisabled: true,
                labelText: this.translate('default', 'fields', 'ImportConfiguratorItem')
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }
            });
        },

    })
);
