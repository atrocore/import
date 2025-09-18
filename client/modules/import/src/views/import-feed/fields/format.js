/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/format', 'views/fields/enum',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail' && this.model.get(this.name) === 'Excel') {
                this.$el.parent().find('.excel-alert').remove();
                this.$el.parent().append(`<span class="text-alert excel-alert" style="font-size: 12px">${this.translate('excelFormatWarning', 'labels', 'ImportFeed')}</span>`);
            }
        },

    })
);