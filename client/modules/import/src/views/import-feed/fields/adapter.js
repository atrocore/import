/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/adapter', 'views/fields/enum',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();
            if (this.params.options.length > 1 && ['detail', 'edit'].includes(this.mode)) {
                this.$el.parent().show();
            }
        },

    })
);