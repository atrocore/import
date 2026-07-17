/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('import:views/import-job/record/row-actions/default', 'views/record/row-actions/default', Dep => {

    return Dep.extend({

        events: {
            'click [data-action=loadCounters]': function (e) {
                e.currentTarget?.classList.add('ph-spin');

                this.ajaxGetRequest('ImportJob/' + this.model.id + '/recordCounters').then(response => {
                    if (response.state) {
                        this.model.set('state', response.state);
                    }

                    this.model.set('lastCounterData', response, {silent: true});
                    this.model.trigger('importCounterChanged');

                    this.reRender();
                }).done(() => e.currentTarget?.classList.remove('ph-spin'));
            }
        },

        hasUndefinedCounters() {
            return ['createdCount', 'updatedCount', 'deletedCount', 'skippedCount', 'errorsCount'].some(field => this.model.get(field) === null)
                || ['Pending', 'Running'].includes(this.model.get('state'));
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.on('after:render', () => {
                if (this.hasUndefinedCounters()) {
                    const iconContainer = $("<div class='icons-container fixed'></div>");
                    iconContainer.html('<button type="button" class="btn btn-link btn-sm" data-action="loadCounters" title="' + this.translate('loadCounters', 'labels', 'ImportJob') + '"><i class="ph ph-arrows-clockwise"></i></button>');
                    this.$el?.find('.list-row-buttons').prepend(iconContainer);
                }

                this.listenTo(this.model, 'importCounterChanged', () => {
                    if (!['Pending', 'Running'].includes(this.model.get('state'))) {
                        this.$el?.find('.list-row-buttons > .icons-container').remove();
                    }
                })

                this.listenTo(this.model, 'importCancel', () => {
                    this.$el?.find('.list-row-buttons > .icons-container').remove();
                });
            });
        },

        getActionList() {
            let list = Dep.prototype.getActionList.call(this);
            let scope = this.scope || this.options.scope;

            if (['Pending', 'Running'].includes(this.model.get('state')) && this.getAcl().check(scope, 'edit')) {
                list.unshift({
                    action: 'cancelImportJob',
                    label: 'Cancel',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (['Failed', 'Canceled'].includes(this.model.get('state')) && this.getAcl().check(scope, 'edit')) {
                list.unshift({
                    action: 'tryAgainImportJob',
                    label: 'tryAgain',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (this.model.get('state') === 'Success' && this.getAcl().check(scope, 'edit')) {
                list.unshift({
                    action: 'reCreateImportJob',
                    label: this.translate('reCreate', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (['Failed', 'Canceled', 'Success'].includes(this.model.get('state'))) {
                if (this.model.get('errorsCount') > 0) {
                    list.unshift({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileErrors', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'errors'
                        }
                    });
                }

                if (this.model.get('skippedCount') > 0 && this.getMetadata().get('scopes.Synchronization.type')) {
                    list.unshift({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileSkippedByScript', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'skippedByScript'
                        }
                    });
                }

                if (this.model.get('skippedCount') > 0) {
                    list.unshift({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileSkippedBySystem', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'skippedBySystem'
                        }
                    });
                }

                if (this.model.get('deletedCount') > 0) {
                    list.unshift({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileDeleted', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'deleted'
                        }
                    });
                }

                if (this.model.get('updatedCount') > 0) {
                    list.unshift({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileUpdated', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'updated'
                        }
                    });
                }

                if (this.model.get('createdCount') > 0) {
                    list.unshift({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileCreated', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'created'
                        }
                    });
                }
            }

            return list;
        }
    });

});