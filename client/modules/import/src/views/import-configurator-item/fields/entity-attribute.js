/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/entity-attribute', 'views/fields/link',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                if (this.getMetadata().get(`scopes.${this.model.get('entity')}.hasAttribute`)) {
                    this.show();
                } else {
                    this.hide();
                }
            }
        }

    })
);
