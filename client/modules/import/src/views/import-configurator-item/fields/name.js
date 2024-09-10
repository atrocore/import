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
            let entity = this.model.get('entity');
            let fields = this.getEntityFields(entity);

            this.params.options = [];
            this.translatedOptions = {};

            $.each(fields, field => {
                this.params.options.push(field);
                this.translatedOptions[field] = this.translate(field, 'fields', entity);
            });
            this.params.options.sort((a, b) => {
                return this.translatedOptions[a].localeCompare(this.translatedOptions[b])
            });

            this.listenTo(this.model, `change:${this.name}`, function () {
                this.model.set('createIfNotExist', false);
            }, this);

            Dep.prototype.setup.call(this);
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
                if (['extensibleEnum', 'extensibleMultiEnum'].includes(this.model.get('attributeData').type)) {
                    let translated = [];
                    this.model.get('importBy').forEach(field => {
                        translated.push(this.translate(field, 'fields', 'ExtensibleEnumOption'));
                    });
                    extraInfo = `<span class="text-muted small">${this.translate('importBy', 'fields', 'ImportConfiguratorItem')}: ${translated.join(', ')}</span><br>`;
                }

                extraInfo += `<span class="text-muted small">${this.translate('code', 'fields', 'Attribute')}: ${this.model.get('attributeData').code}</span>`;
                extraInfo += `<br><span class="text-muted small">${this.translate('attributeValue', 'fields', 'ImportConfiguratorItem')}: ${this.getLanguage().translateOption(this.model.get('attributeValue'), 'attributeValue', 'ImportConfiguratorItem')}</span>`;
                if (this.model.get('channelName')){
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