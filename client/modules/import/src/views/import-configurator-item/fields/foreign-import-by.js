/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/foreign-import-by', ['views/fields/multi-enum', 'import:views/import-configurator-item/fields/import-by'],
    (Dep, ImportBy) => Dep.extend({

        allowedTypes: ['bool', 'enum', 'varchar', 'email', 'float', 'int', 'text', 'wysiwyg', 'markdown'],

        setup() {
            Dep.prototype.setup.call(this);

            this.validations = Espo.Utils.clone(this.validations);
            if (!this.validations.includes('columns')) {
                this.validations.push('columns');
            }

            this.prepareOptions();
            this.listenTo(this.model, 'change:name', () => {
                this.prepareOptions();
            });

            this.listenTo(this.model, 'change:createIfNotExist', () => {
                if (!this.model.get('createIfNotExist')) {
                    this.model.set(this.name, null);
                }
                this.prepareOptions();
            });

            this.listenTo(this.model, 'change:importBy', () => {
                const importBy = this.model.get('importBy');

                if (importBy && this.model.get(this.name)) {
                    this.model.set(this.name, this.model.get('foreignImportBy').filter(field => !importBy.includes(field)));
                }

                this.prepareOptions();
                this.reRender();
            });
        },

        prepareOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            $.each(ImportBy.prototype.getImportByOptions.call(this), (name, label) => {
                this.params.options.push(name);
                this.translatedOptions[name] = label;
            });
        },

        getForeignEntity() {
            return ImportBy.prototype.getForeignEntity.call(this);
        },

        validateColumns() {
            let validate = false;

            const columns = (this.model.get('foreignColumn') || []).length,
                fields = (this.model.get(this.name) || []).length;

            if ((columns > 1 && fields !== columns) || (columns === 1 && fields < 1)) {
                this.showValidationMessage(this.translate('wrongForeignFieldsNumber', 'exceptions', 'ImportConfiguratorItem'), this.$el);
                validate = true;
            }

            return validate;
        }
    })
);
