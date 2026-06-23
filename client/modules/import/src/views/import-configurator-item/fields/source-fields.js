/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/source-fields', 'views/fields/array', function (Dep) {
    return Dep.extend({

        detailTemplate: 'import:import-configurator-item/fields/source-fields/detail',

        getValueForDisplay() {
            return this.selected.sort((a, b) => a.localeCompare(b));
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' && ['JSON', 'XML'].includes(this.getFormat())) {
                let html = '';
                (this.model.get(this.name) || []).forEach(column => {
                    let parts = column.split('.');
                    let last = parts.pop();
                    if (parts.length === 0) {
                        html += '<span style="width:100%;float:left">' + last + '</span>';
                    } else {
                        html += '<span style="width:100%;float:left"><span style="color: #bbb">' + parts.join('.') + '</span>.' + last + '</span>';
                    }
                });

                this.$el.html(html);
            }
        },

        getFormat() {
            return this.model.collection?.importFeedModel?.get('format') || null;
        },

    })
});
