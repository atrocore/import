/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-job/record/list', 'views/record/list',
    Dep => Dep.extend({

        rowActionsView: 'import:views/import-job/record/row-actions/default',

        getSelectAttributeList: function (callback) {
            Dep.prototype.getSelectAttributeList.call(this, attributeList => {
                if (Array.isArray(attributeList) && !attributeList.includes('entityName')) {
                    attributeList.push('entityName', 'importFeedId');
                }
                callback(attributeList);
            });
        },

        actionTryAgainImportJob(data) {
            let model = this.collection.get(data.id);

            this.notify('Saving...');
            model.set('state', 'Pending');
            model.save().then(() => {
                this.notify('Saved', 'success');
            });
        },

        actionReCreateImportJob(data) {
            const importJobModel = this.collection.get(data.id)
            this.getModelFactory().create('ImportFeed', model => {
                model.set({
                    id: data.id,
                    importFileId: importJobModel.get('attachmentId'),
                    importFileName: importJobModel.get('attachmentName')
                });
                this.getParentView().getParentView().createView('dialog', 'import:views/import-job/modals/recreate', {
                    scope: this.options.scope,
                    el: '[data-view="dialog"]',
                    model: model,
                }, view => view.render());
            })
        },

        actionGenerateErrorFile(data) {
            let model = this.collection.get(data.id);
            if (model.get('errorsAttachmentName')) {
                this.downloadFile(model.get('errorsAttachmentPathsData').download, model.get('errorsAttachmentName'));
                return;
            }

            this.notify(this.translate('generating', 'labels', 'ImportJob'));
            this.ajaxPostRequest('ImportJob/action/generateFile', {id: model.get('id'), field: 'errorsAttachment'}).then(response => {
                let interval = setInterval(() => {
                    this.ajaxGetRequest(`QueueItem/${response.queueItemId}?silent=true`).success(res => {
                        this.notify(this.translate('generating', 'labels', 'ImportJob'));
                        if (["Success", "Failed", "Canceled"].includes(res.status)) {
                            clearInterval(interval);
                            this.notify('Done', 'success');
                            model.fetch().then(() => {
                                if (model.get('errorsAttachmentName')) {
                                    this.downloadFile(model.get('errorsAttachmentPathsData').download, model.get('errorsAttachmentName'));
                                }
                            });
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
