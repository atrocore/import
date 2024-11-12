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
                    label: 'reCreate',
                    data: {
                        id: this.model.id
                    }
                });
            }

            if (['Failed', 'Canceled', 'Success'].includes(this.model.get('state')) && this.model.get('errorsCount') > 0) {
                list.unshift({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileErrors', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'errors'
                    }
                });

                if (this.getMetadata().get('scopes.Synchronization.type')) {
                    list.unshift({
                        action: 'generateFileForJob',
                        label: this.translate('generateFileSkippedByScript', 'labels', 'ImportJob'),
                        data: {
                            id: this.model.id,
                            type: 'skippedByScript'
                        }
                    });
                }

                list.unshift({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileSkippedBySystem', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'skippedBySystem'
                    }
                });

                list.unshift({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileDeleted', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'deleted'
                    }
                });

                list.unshift({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileUpdated', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'updated'
                    }
                });

                list.unshift({
                    action: 'generateFileForJob',
                    label: this.translate('generateFileCreated', 'labels', 'ImportJob'),
                    data: {
                        id: this.model.id,
                        type: 'created'
                    }
                });
            }

            return list;
        }
    });

});