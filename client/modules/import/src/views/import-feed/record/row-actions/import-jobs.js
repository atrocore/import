/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('import:views/import-feed/record/row-actions/import-jobs', 'views/record/row-actions/relationship', Dep => {

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
            let list = [],
                scope = this.scope || this.options.scope;
            if (['Pending', 'Running'].includes(this.model.get('state')) && this.getAcl().check(scope, 'edit')) {
                list.push({
                    action: 'cancelImportJob',
                    label: 'Cancel',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (['Failed', 'Canceled'].includes(this.model.get('state')) && this.getAcl().check(scope, 'edit')) {
                list.push({
                    action: 'tryAgainImportJob',
                    label: 'tryAgain',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (['Failed', 'Canceled', 'Success'].includes(this.model.get('state')) && this.model.get('processingType') === 'configurator') {
                list.push({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileCreated', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'created'
                    }
                });
                list.push({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileUpdated', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'updated'
                    }
                });
                list.push({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileDeleted', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'deleted'
                    }
                });
                list.push({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileSkippedBySystem', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'skippedBySystem'
                    }
                });

                if (this.getMetadata().get('scopes.Synchronization.type')) {
                    list.push({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileSkippedByScript', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'skippedByScript'
                        }
                    });
                }

                list.push({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileErrors', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'errors'
                    }
                });
            }

            if (this.model.get('state') === 'Success' && this.getAcl().check(scope, 'edit')) {
                list.push({
                    action: 'reCreateImportJob',
                    label: 'reCreate',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (this.getAcl().check(scope, 'delete')) {
                list.push({
                    action: 'removeRelated',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        }

    });

});