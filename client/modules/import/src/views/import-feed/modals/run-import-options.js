/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/modals/run-import-options', 'views/modal',
    Dep => Dep.extend({

        template: 'import:import-feed/modals/run-import-options',

        data() {
            return {
                scope: this.scope
            }
        },

        setup() {
            this.buttonList = [
                {
                    name: 'runImport',
                    label: 'import',
                    style: 'primary',
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.scope = this.options.scope || this.model.name || this.scope;
            this.header = this.getLanguage().translate('import', 'labels', this.scope);
            this.setupFields();
        },

        setupFields() {
            this.createView('importFile', 'import:views/import-feed/fields/file', {
                el: `${this.options.el} .field[data-name="importFile"]`,
                model: this.model,
                name: 'importFile',
                params: {
                    required: true
                },
                mode: 'edit',
                inlineEditDisabled: true
            }, view => {
                this.listenTo(this, 'close', () => {
                    this.model.set('importFileId', null);
                    this.model.set('importFileName', null);
                });
            });
        },

        actionRunImport() {
            if (this.validate()) {
                this.notify('Not valid', 'error');
                return;
            }

            let data = {
                importFeedId: this.model.id || null,
                attachmentId: this.model.get('importFileId') || null,
            };
            this.notify(this.translate('creatingImportJobs', 'labels', 'ImportFeed'));
            this.ajaxPostRequest('ImportFeed/action/runImport', data).then(response => {
                if (response) {
                    this.notify('Created', 'success');
                    this.dialog.close();
                    this.model.trigger('importRun');
                }
            });
        },

        validate() {
            let notValid = false;
            let fields = this.nestedViews;
            for (let i in fields) {
                if (fields[i].mode === 'edit') {
                    if (!fields[i].disabled && !fields[i].readOnly) {
                        notValid = fields[i].validate() || notValid;
                    }
                }
            }
            return notValid
        },
    })
);
