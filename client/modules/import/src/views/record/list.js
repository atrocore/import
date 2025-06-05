/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/record/list', 'class-replace!import:views/record/list',
    Dep => Dep.extend({

        massActionDynamicActionUploadAndImport(data) {
            if (!data.id || data.action !== 'dynamicEntityAction') {
                return;
            }

            const action = this.getMetadata().get(['clientDefs', this.scope, 'dynamicEntityActions']).filter(action => action.id === data.id).pop();

            if (!action || !action.importFeedId) {
                return;
            }

            this.getModelFactory().create('ImportFeed', model => {
                model.id = action.importFeedId;

                this.notify('Loading...');
                model.fetch().success(() => {
                    this.notify(false);
                    this.createView('dialog', 'import:views/import-feed/modals/run-import-options', {
                        model: model
                    }, view => view.render());
                });
            });
        }

    })
);
