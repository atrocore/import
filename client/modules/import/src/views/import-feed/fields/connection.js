/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/connection', 'views/fields/link',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (['edit', 'detail'].includes(this.mode)) {
                let typesWithConnection = this.getMetadata().get('import.connection.types') || [];
                if (typesWithConnection.includes(this.model.get('type'))) {
                    this.show();
                } else {
                    this.hide();
                }
            }
        },

    })
);