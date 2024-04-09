/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-job/fields/generated-file', 'views/fields/file',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list' && this.name === 'convertedFile') {
                if (this.model.get('hasConvertedFile')) {
                    this.$el.parent().show();
                } else {
                    this.$el.parent().hide();
                }
            }

            if (this.mode === 'detail' && this.model.get(this.idName) === null) {
                this.$el.html(`<a href="javascript:" data-action="generateFile" data-name="${this.name}">${this.translate('generate', 'labels', 'ImportJob')}</a>`);
            }
        },

    })
);
