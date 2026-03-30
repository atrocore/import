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

            // setOptionList (called from disableOptions inside Dep.prototype.setup) uses Array.includes()
            // with strict equality. The server returns sheet as an integer (e.g. 0), but params.options
            // are strings ("0"). 0 !== "0", so includes() returns false and triggers 'change', clearing
            // the value. Normalize to string here and on every model change (e.g. cancel reverts to integer).
            const normalizeToString = () => {
                const v = this.model.get(this.name);
                if (v !== null && v !== undefined && typeof v !== 'string') {
                    this.model.set(this.name, String(v), {silent: true});
                }
            };

            normalizeToString();
            this.listenTo(this.model, 'change:' + this.name, normalizeToString);

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