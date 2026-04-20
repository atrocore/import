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

            (this.model.get('sheetOptions') || []).forEach((value, key) => {
                this.translatedOptions[key] = value;
                this.params.options.push(key);
            });

            this.originalOptionList = this.params.options;

            Dep.prototype.setup.call(this);
        },

        validateRequired() {
            if (this.isRequired()) {
                if (this.model.get(this.name) === null || this.model.get(this.name) === undefined) {
                    const msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        fetch() {
            const data = Dep.prototype.fetch.call(this);
            if (data[this.name] !== null && data[this.name] !== undefined) {
                data[this.name] = parseInt(data[this.name], 10);
            }
            return data;
        },

        loadFileSheets() {
            let fileId = this.model.get('fileId');
            if (!fileId || this.model.get('format') !== 'Excel') {
                return;
            }

            this.ajaxGetRequest(`File/${fileId}/sheets`).success(response => {
                this.model.set('sheetOptions', response, {silent: true});
                this.model.set(this.name, null, {silent: true});
                this.originalOptionList = null;
                this.params.options = [];
                this.translatedOptions = {};
                (response || []).forEach((value, key) => {
                    this.translatedOptions[key] = value;
                    this.params.options.push(key);
                });

                this.originalOptionList = this.params.options;

                this.reRender();
            });
        }
    })
);