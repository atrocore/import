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

        rowActionsView: 'import:views/import-job/record/row-actions/import-again-and-remove',

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
    })
);
