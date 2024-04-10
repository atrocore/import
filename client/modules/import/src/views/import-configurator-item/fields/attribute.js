/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/attribute', 'views/fields/link',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.mode === 'edit') {
                this.listenTo(this.model, 'change:attributeId', () => {
                    this.model.set('attributeData', null);
                    if (this.model.get('attributeId')) {
                        this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attr => {
                            this.model.set('attributeData', attr);
                        });
                    }
                });
            }
        },

    })
);
