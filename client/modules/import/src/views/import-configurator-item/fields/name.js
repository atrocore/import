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

            Dep.prototype.setup.call(this);

            if (this.model.isNew() && !this.model.get(this.name)) {
                this.model.set(this.name, 'id');
            }

            this.listenTo(this.model, `change:${this.name}`, () => {
                this.model.set('createIfNotExist', false);

                if (this.model.get(this.name) === '_addAttribute') {
                    this.actionSelectAttribute();
                } else {
                    this.model.set('entityAttributeId', this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get(this.name), 'attributeId']));
                }
            });
        },

        prepareListOptions() {
            this.params.options = ['id'];
            this.translatedOptions = { 'id': this.translate('id', 'fields', 'Global') };

            let entity = this.model.get('entity');
            let hasAttribute = this.getMetadata().get(`scopes.${entity}.hasAttribute`);

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

            if (hasAttribute) {
                this.params.groupOptions = [
                    {
                        name: "attributes",
                        options: ['_addAttribute']
                    },
                    {
                        name: "fields",
                        options: []
                    }
                ];
                this.params.options.push('_addAttribute');
                this.translatedOptions['_addAttribute'] = this.translate('_addAttribute', 'labels', 'ImportConfiguratorItem');
            }

            $.each(this.getMetadata().get(['entityDefs', entity, 'fields'], {}), (field, fieldDefs) => {
                if (!fieldDefs.disabled && !notAvailableFieldsList.includes(field) && !notAvailableTypes.includes(fieldDefs.type) && !fieldDefs.importDisabled) {
                    this.params.options.push(field);
                    this.translatedOptions[field] = this.translate(field, 'fields', entity);

                    if (hasAttribute) {
                        if (fieldDefs.attributeId) {
                            this.params.groupOptions[0].options.push(field);
                        } else {
                            this.params.groupOptions[1].options.push(field);
                        }
                    }
                }
            })

            this.params.options.sort((a, b) => {
                return this.translatedOptions[a].localeCompare(this.translatedOptions[b])
            });
        },

        actionSelectAttribute() {
            const scope = 'Attribute';
            const viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) || 'views/modals/select-records';

            let entity = this.model.get('entity');

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: false,
                createButton: false,
                massRelateEnabled: false,
                boolFilterList: ['onlyForEntity'],
                boolFilterData: {
                    onlyForEntity: entity
                },
                allowSelectAllResult: false,
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', model => {
                    this.wait(true);
                    this.notify('Loading...');
                    this.ajaxGetRequest('Attribute/action/attributesDefs', {
                        entityName: entity,
                        attributesIds: [model.id]
                    }, { async: false }).success(res => {
                        $.each(res, (field, fieldDefs) => {
                            if (!fieldDefs.importDisabled) {
                                this.params.options.push(field);
                                this.translatedOptions[field] = fieldDefs.label;

                                this.getMetadata().data.entityDefs[entity].fields[field] = fieldDefs;
                                this.getLanguage().data[entity].fields[field] = fieldDefs.label;

                                this.params.groupOptions[0].options.push(field);
                                this.originalOptionList = this.params.options;
                                if (this.model.get(this.name) === '_addAttribute') {
                                    this.model.set(this.name, field);
                                }
                            }
                        });

                        this.model.set('entityAttributeId', model.id);
                        this.model.set('entityAttributeName', model.get('name'));

                        this.wait(false);
                        this.notify(false);

                        this.clearView('dialog');

                        this.reRender();
                    })
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

            return this.translate(name, 'fields', this.model.get('entity'));

        },

        getExtraInfo() {
            let extraInfo = null;

            let type = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'type']);
            if (['file', 'link', 'linkMultiple', 'extensibleEnum', 'extensibleMultiEnum', 'measure'].includes(type)) {
                let entityName = this.getMetadata().get(['entityDefs', this.model.get('entity'), 'fields', this.model.get('name'), 'entity']) || this.getMetadata().get(['entityDefs', this.model.get('entity'), 'links', this.model.get('name'), 'entity']);
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

            if (this.model.get('createIfNotExist')) {
                extraInfo += `<br><span class="text-muted small">${this.translate('createIfNotExist', 'fields', 'ImportConfiguratorItem')}</span>`;
            }

            return extraInfo;
        },

    })
);