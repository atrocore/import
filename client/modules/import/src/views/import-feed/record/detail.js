/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            const canImport = this.hasImportNow();

            this.additionalButtons.push(
                {
                    "action": "runImport",
                    "label": this.translate('import', 'labels', 'ImportFeed'),
                    "disabled": !canImport
                },
                {
                    "action": "uploadAndRunImport",
                    "label": this.translate('uploadAndImport', 'labels', 'ImportFeed'),
                    "disabled": !canImport
                }
            );
        },

        hasImportNow() {
            return this.model.get('isActive') && this.getAcl().check('ImportJob', 'create');
        },

        actionRunImport() {
            if (!this.hasImportNow()) {
                return;
            }

            this.confirm(this.translate('importNow', 'messages', 'ImportFeed'), () => {
                const data = {
                    fileId: null,
                };
                this.notify(this.translate('creatingImportJobs', 'labels', 'ImportFeed'));
                this.ajaxPostRequest('ImportFeed/' + this.model.get('id') + '/runImport', data).then(response => {
                    if (response) {
                        this.notify('Created', 'success');
                        this.model.trigger('importRun');
                    }
                });
            });
        },

        actionUploadAndRunImport() {
            if (!this.hasImportNow()) {
                return;
            }

            this.createView('dialog', 'import:views/import-feed/modals/run-import-options', {
                model: this.model
            }, view => {
                view.on('runImport', payload => {
                    const id = payload.importFeedId || this.model.get('id');
                    const data = {
                        fileId: payload.fileId || null,
                    };

                    this.notify(this.translate('creatingImportJobs', 'labels', 'ImportFeed'));
                    this.ajaxPostRequest('ImportFeed/' + id + '/runImport', data).then(response => {
                        if (response) {
                            this.notify('Created', 'success');
                            view.dialog.close();
                            this.model.trigger('importRun');
                        }
                    });
                });

                view.render();
            });
        },

    })
);
