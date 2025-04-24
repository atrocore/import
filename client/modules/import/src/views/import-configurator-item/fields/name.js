/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/name', 'views/fields/enum',
    Dep => Dep.extend({

        listTemplate: 'import:import-configurator-item/fields/name/list',

        setup() {
            this.prepareListOptions();
            this.listenTo(this.model, 'change:entityAttributeId', () => {
                this.prepareListOptions();
                this.reRender();
            });

            Dep.prototype.setup.call(this);

            if (this.model.isNew() && !this.model.get(this.name) && this.model.get('type') === 'Field') {
                this.model.set(this.name, 'id');
            }

            this.listenTo(this.model, `change:${this.name}`, () => {
                this.model.set('createIfNotExist', false);

                if (this.model.get(this.name) === '_addAttribute') {
                    this.actionSelectAttribute();
                }
            });
        },

        prepareListOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            let entity = this.model.get('entity');

            $.each(this.getEntityFields(entity), field => {
                this.params.options.push(field);
                this.translatedOptions[field] = this.translate(field, 'fields', entity);
            });


            if (this.getMetadata().get(`scopes.${entity}.hasAttribute`)) {
                this.params.options.push('_addAttribute');
                this.translatedOptions['_addAttribute'] = this.translate('_addAttribute', 'labels', 'ImportConfiguratorItem');

                this.params.groupOptions = [
                    {
                        name: "attributes",
                        options: ['_addAttribute']
                    },
                    {
                        name: "fields",
                        options: this.params.options.filter(o => !(["_addAttribute"].includes(o)))
                    }
                ]

                if (this.model.get('entityAttributeId')) {
                    this.wait(true);
                    this.notify('Loading...');
                    this.ajaxGetRequest('Attribute/action/attributesDefs', {
                        entityName: entity,
                        attributesIds: [this.model.get('entityAttributeId')]
                    }, {async: false}).success(res => {
                        $.each(res, (field, fieldDefs) => {
                            if (this.model.get(this.name) === '_addAttribute') {
                                this.model.set(this.name, field);
                            }

                            this.params.options.push(field);
                            this.translatedOptions[field] = fieldDefs.label;

                            this.getMetadata().data.entityDefs[entity].fields[field] = fieldDefs;
                            this.getLanguage().data[entity].fields[field] = fieldDefs.label;

                            this.params.groupOptions[0].options.push(field);
                        });


                        this.wait(false);
                        this.notify(false);
                    })
                }
            }

            this.params.options.sort((a, b) => {
                return this.translatedOptions[a].localeCompare(this.translatedOptions[b])
            });
        },

        actionSelectAttribute() {
            const scope = 'Attribute';
            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: false,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: false,
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', model => {
                    this.model.set('entityAttributeId', model.id);
                    this.model.set('entityAttributeName', model.get('name'));
                });
            });
        },

        data() {
            let data = Dep.prototype.data.call(this);

            if (this.mode === 'list') {
                data.isRequired = !!this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'required']);
                data.extraInfo = this.getExtraInfo();
            }

            return data;
        },

        getValueForDisplay() {
            let name = this.model.get('name');

            if (this.mode !== 'list') {
                return name;
            }

            if (this.model.get('type') === 'Field') {
                name = this.translate(name, 'fields', this.model.get('entity'));
            }

            if (this.model.get('type') === 'Attribute' && this.model.get('attributeData') && this.model.get('attributeData').isMultilang && this.model.get('locale') !== 'main') {
                name += ' / ' + this.model.get('locale');
            }

            return name;
        },

        getExtraInfo() {
            let extraInfo = null;

            if (this.model.get('type') === 'Field') {
                let type = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']);
                if (['file', 'link', 'linkMultiple', 'extensibleEnum', 'extensibleMultiEnum', 'measure'].includes(type)) {
                    let entityName = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);
                    if (type === 'file') {
                        entityName = 'File'
                    }
                    if (type === 'measure') {
                        entityName = 'Unit'
                    }
                    let translated = [];
                    this.model.get('importBy').forEach(field => {
                        if (field.endsWith('Id')) {
                            translated.push(this.translate(field.slice(0, -2), 'fields', entityName) + ` (${this.translate('id', 'fields', 'Global')})`);
                        } else {
                            translated.push(this.translate(field, 'fields', entityName));
                        }
                    });
                    extraInfo = `<span class="text-muted small">${this.translate('importBy', 'fields', 'ImportConfiguratorItem')}: ${translated.join(', ')}</span>`;
                    if ((type === 'extensibleMultiEnum' || type === 'linkMultiple' || type === 'array' || type === 'multiEnum') && this.model.get('replaceArray')) {
                        extraInfo += `<br><span class="text-muted small">${this.translate('replaceArray', 'fields', 'ImportConfiguratorItem')}</span>`;
                    }
                }
            }

            if (this.model.get('type') === 'Attribute') {
                extraInfo = '';
                let type = this.model.get('attributeData').type;
                if (['extensibleEnum', 'extensibleMultiEnum'].includes(type)) {
                    let translated = [];
                    this.model.get('importBy').forEach(field => {
                        translated.push(this.translate(field, 'fields', 'ExtensibleEnumOption'));
                    });
                    extraInfo = `<span class="text-muted small">${this.translate('importBy', 'fields', 'ImportConfiguratorItem')}: ${translated.join(', ')}</span><br>`;
                }

                extraInfo += `<span class="text-muted small">${this.translate('code', 'fields', 'Attribute')}: ${this.model.get('attributeData').code}</span>`;
                if (['float', 'int', 'varchar'].includes(type) && this.model.get('attributeValue') === 'valueMain') {
                    let translatedType = this.getLanguage().translate(type, 'fieldTypes', 'Admin');
                    extraInfo += `<br><span class="text-muted small">${this.translate('attributeValue', 'fields', 'ImportConfiguratorItem')}: ${this.getLanguage().translateOption(this.model.get('attributeValue'), 'attributeValue', 'ImportConfiguratorItem').replace('%s', translatedType)}</span>`;
                } else {
                    extraInfo += `<br><span class="text-muted small">${this.translate('attributeValue', 'fields', 'ImportConfiguratorItem')}: ${this.getLanguage().translateOption(this.model.get('attributeValue'), 'attributeValue', 'ImportConfiguratorItem')}</span>`;
                }
                if (this.model.get('channelName')) {
                    extraInfo += `<br><span class="text-muted small">${this.translate('Channel', 'scopeNames', 'Global')}: ${this.model.get('channelName')}</span>`;
                }
            }

            if (this.model.get('createIfNotExist')) {
                extraInfo += `<br><span class="text-muted small">${this.translate('createIfNotExist', 'fields', 'ImportConfiguratorItem')}</span>`;
            }

            return extraInfo;
        },

        getEntityFields(entity) {
            let result = {};
            let notAvailableTypes = [
                'address',
                'attachmentMultiple',
                'currencyConverted',
                'linkParent',
                'personName',
                'autoincrement'
            ];
            let notAvailableFieldsList = [
                'createdAt',
                'modifiedAt'
            ];
            if (entity) {
                let fields = this.getMetadata().get(['entityDefs', entity, 'fields']) || {};
                result.id = {
                    type: 'varchar'
                };
                Object.keys(fields).forEach(name => {
                    let field = fields[name];
                    if (!field.disabled && !notAvailableFieldsList.includes(name) && !notAvailableTypes.includes(field.type) && !field.importDisabled) {
                        result[name] = field;
                    }
                });
            }
            return result;
        },

    })
);