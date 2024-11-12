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
            if (['Failed', 'Canceled'].includes(this.model.get('state'))) {
                this.dropdownItemList.push({
                    name: 'tryAgainImportJob',
                    action: 'tryAgainImportJob',
                    label: 'tryAgain',
                });
            }

            this.dropdownItemList.push({
                name: 'generateErrorFile',
                action: 'generateErrorFile',
                label: this.translate('generateErrorsFile', 'labels', 'ImportJob'),
            });

            Dep.prototype.setupActionItems.call(this);
        },

        actionGenerateErrorFile(){
            this.actionGenerateFile('errors');
        },

        actionGenerateFile(type) {
            this.notify(this.translate('generating', 'labels', 'ImportJob'));
            this.ajaxPostRequest('ImportJob/action/generateFile', {id: this.model.get('id'), type: type}).then(response => {
                let interval = setInterval(() => {
                    this.ajaxGetRequest(`QueueItem/${response.queueItemId}?silent=true`).success(res => {
                        this.notify(this.translate('generating', 'labels', 'ImportJob'));
                        if (["Success", "Failed", "Canceled"].includes(res.status)) {
                            clearInterval(interval);
                            this.model.fetch();
                            this.notify('Done', 'success');
                            $('.action[data-action=refresh][data-panel=files]').click();
                        }
                    }).error(() => {
                        clearInterval(interval);
                        this.model.fetch();
                        this.notify('Done', 'success');
                    });
                }, 2000);
            });
        },

    })
);