/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-job/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        duplicateAction: false,

        events: _.extend({
            'click [data-action="generateFile"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.actionGenerateFile($(e.currentTarget).data('name'));
            }
        }, Dep.prototype.events),

        setupActionItems: function () {
            this.buttonList = this.buttonList.filter(button => button.name !== 'edit');
            this.additionalButtons = this.additionalButtons.filter(button => button.name !== 'loadCounters');
            if (['createdCount', 'updatedCount', 'deletedCount', 'skippedCount', 'errorsCount'].some(field => this.model.get(field) === null)) {
                this.additionalButtons.push({
                    name: 'loadCounters',
                    action: 'loadCounters',
                    html: '<i class="ph ph-arrows-clockwise"></i>',
                    tooltip: this.translate('loadCounters', 'labels', 'ImportJob'),
                });
            }

            if (['Failed', 'Canceled'].includes(this.model.get('state'))) {
                this.dropdownItemList.push({
                    name: 'tryAgainImportJob',
                    action: 'tryAgainImportJob',
                    label: this.translate('tryAgain', 'labels', 'ImportJob'),
                });
            }

            if (['Failed', 'Canceled', 'Success'].includes(this.model.get('state')) && this.model.get('processingType') === 'configurator') {
                this.dropdownItemList.push({
                    name: 'generateFileCreated',
                    action: 'generateFileCreated',
                    label: this.translate('generateFileCreated', 'labels', 'ImportJob'),
                });

                this.dropdownItemList.push({
                    name: 'generateFileUpdated',
                    action: 'generateFileUpdated',
                    label: this.translate('generateFileUpdated', 'labels', 'ImportJob'),
                });

                this.dropdownItemList.push({
                    name: 'generateFileDeleted',
                    action: 'generateFileDeleted',
                    label: this.translate('generateFileDeleted', 'labels', 'ImportJob'),
                });

                this.dropdownItemList.push({
                    name: 'generateFileSkippedBySystem',
                    action: 'generateFileSkippedBySystem',
                    label: this.translate('generateFileSkippedBySystem', 'labels', 'ImportJob'),
                });

                if (this.getMetadata().get('scopes.Synchronization.type')) {
                    this.dropdownItemList.push({
                        name: 'generateFileSkippedByScript',
                        action: 'generateFileSkippedByScript',
                        label: this.translate('generateFileSkippedByScript', 'labels', 'ImportJob'),
                    });
                }

                this.dropdownItemList.push({
                    name: 'generateFileErrors',
                    action: 'generateFileErrors',
                    label: this.translate('generateFileErrors', 'labels', 'ImportJob'),
                });
            }

            Dep.prototype.setupActionItems.call(this);
        },

        actionLoadCounters: function (data, e) {
            const target = e.currentTarget;

            target.disabled = true;
            this.notify('Loading...');
            this.ajaxGetRequest(`ImportJob/${this.model.id}/recordCounters`).success(response => {
                this.model.set('lastCounterData', response, {silent: true});
                this.model.trigger('importCounterChanged');

                if (!['Pending', 'Running'].includes(this.model.get('state'))) {
                    this.additionalButtons = (this.additionalButtons || []).filter(button => button.name !== 'loadCounters');
                }

                this.reRender();
            }).done(() => {
                this.notify(false);
                target.disabled = false;
            });
        },

        actionGenerateFileCreated() {
            this.actionGenerateFile('created');
        },

        actionGenerateFileUpdated() {
            this.actionGenerateFile('updated');
        },

        actionGenerateFileDeleted() {
            this.actionGenerateFile('deleted');
        },

        actionGenerateFileSkippedBySystem() {
            this.actionGenerateFile('skippedBySystem');
        },

        actionGenerateFileSkippedByScript() {
            this.actionGenerateFile('skippedByScript');
        },

        actionGenerateFileErrors() {
            this.actionGenerateFile('errors');
        },

        actionGenerateFile(type) {
            this.notify(this.translate('generating', 'labels', 'ImportJob'));
            this.ajaxPostRequest('ImportJob/action/generateFile', {
                id: this.model.get('id'),
                type: type
            }).then(response => {
                let interval = setInterval(() => {
                    this.ajaxGetRequest(`Job/${response.queueItemId}?silent=true`).success(res => {
                        this.notify(this.translate('generating', 'labels', 'ImportJob'));
                        if (res.status === 'Success') {
                            clearInterval(interval);
                            this.notify('Done', 'success');
                            if (type === 'convertedFile') {
                                this.model.fetch();
                            } else {
                                $('.action[data-action=refresh][data-panel=files]').click();
                            }
                            this.downloadFile(res.payload.downloadUrl, res.payload.fileName);
                        } else if (["Failed", "Canceled"].includes(res.status)) {
                            clearInterval(interval);
                            this.notify('Error occured!', 'error');
                        }
                    }).error(() => {
                        clearInterval(interval);
                        this.notify('Error occured!', 'error');
                    });
                }, 2000);
            });
        },

        downloadFile(url, filename) {
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);

            a.click();

            document.body.removeChild(a);
        },

    })
);