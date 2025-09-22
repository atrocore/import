/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/sheet', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            this.listenTo(this.model, 'fileUpdate', () => {
                if (this.getParentView().getView('file').mode === 'edit') {
                    this.loadFileSheets();
                }
            });

            this.params.options = [];
            this.translatedOptions = {};

            (this.model.get('sheetOptions') || []).forEach((value, key)=> {
                let k = key.toString();

                this.translatedOptions[k] = value;
                this.params.options.push(k);
            })

            this.originalOptionList = this.params.options;

            Dep.prototype.setup.call(this);
        },

        loadFileSheets() {
            let fileId = this.model.get('fileId');
            if (!fileId) {
                return;
            }

            let data = {
                attachmentId: fileId,
                format: this.model.get('format')
            };

            this.ajaxPostRequest(`ImportFeed/action/GetFileSheets`, data).success(response => {
                this.model.set('sheetOptions', response);
                this.params.options = [];
                this.translatedOptions = {};
                (response || []).forEach((value, key)=> {
                    let k = key.toString();

                    this.translatedOptions[k] = value;
                    this.params.options.push(k);
                })

                this.originalOptionList = this.params.options;

                this.reRender();
            });
        }
    })
);