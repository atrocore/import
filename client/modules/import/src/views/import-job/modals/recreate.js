/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-job/modals/recreate', 'import:views/import-feed/modals/run-import-options',
    Dep => Dep.extend({

        actionRunImport() {
            if (this.validate()) {
                this.notify('Not valid', 'error');
                return;
            }

            this.notify(this.translate('creatingImportJobs', 'labels', 'ImportFeed'));
            this.ajaxPostRequest('ImportJob/action/reCreate', {
                id: this.model.get('id'),
                attachmentId: this.model.get('importFileId')
            }).then(response => {
                if (response) {
                    this.notify('Created', 'success');
                    this.dialog.close();
                    this.collection.fetch()
                }
            });
        },
    })
);
