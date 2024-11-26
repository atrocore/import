/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/file', 'views/fields/file',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.name === 'file') {
                this.listenTo(this.model, 'change:fileId', () => {
                    this.model.trigger('fileUpdate');
                });

                this.prepareAccept();
                this.listenTo(this.model, 'change:format', () => {
                    this.model.trigger('fileUpdate');
                    this.prepareAccept();
                    this.reRender();
                });
                this.shouldAvoidAutomaticallyExtensionUpdate = true;
            }
        },

        prepareAccept() {
            if (this.model.get('format') === 'CSV') {
                this.accept = '.csv';
            }

            if (this.model.get('format') === 'Excel') {
                this.accept = '.xls,.xlsx';
            }

            if (this.model.get('format') === 'JSON') {
                this.accept = '.json';
            }

            if (this.model.get('format') === 'XML') {
                this.accept = '.xml';
            }
        },

    })
);