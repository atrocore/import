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

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalButtons = [
                {
                    "action": "runImport",
                    "label": this.translate('import', 'labels', 'ImportFeed')
                }
            ];

            this.additionalButtons.push({
                "action": "uploadAndRunImport",
                "label": this.translate('uploadAndImport', 'labels', 'ImportFeed')
            })

            this.listenTo(this.model, 'after:save', () => {
                this.handleButtonsDisability();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.handleButtonsDisability();
        },

        isButtonsDisabled() {
            return !this.model.get('isActive') || !this.getAcl().check('ImportJob', 'create');
        },

        handleButtonsDisability() {
            const $buttons = $('.additional-button');
            if (this.isButtonsDisabled()) {
                $buttons.addClass('disabled');
            } else {
                $buttons.removeClass('disabled');
            }
        },

        actionRunImport() {
            if ($('.action[data-action=runImport]').hasClass('disabled')) {
                return;
            }

            this.confirm(this.translate('importNow', 'messages', 'ImportFeed'), () => {
                const data = {
                    importFeedId: this.model.get('id'),
                    attachmentId: null,
                };
                this.notify(this.translate('creatingImportJobs', 'labels', 'ImportFeed'));
                this.ajaxPostRequest('ImportFeed/action/runImport', data).then(response => {
                    if (response) {
                        this.notify('Created', 'success');
                        this.model.trigger('importRun');
                    }
                });
            });
        },

        actionUploadAndRunImport() {
            if ($('.action[data-action=uploadAndRunImport]').hasClass('disabled')) {
                return;
            }

            this.createView('dialog', 'import:views/import-feed/modals/run-import-options', {
                model: this.model
            }, view => {
                view.on('runImport', payload => {
                    const data = {
                        importFeedId: payload.importFeedId || null,
                        attachmentId: payload.attachmentId || null,
                    };

                    this.notify(this.translate('creatingImportJobs', 'labels', 'ImportFeed'));
                    this.ajaxPostRequest('ImportFeed/action/runImport', data).then(response => {
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