/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/foreign-column', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            this.params.options = this.model.get('sourceFields') || [];
            this.translatedOptions = {};

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name change:createIfNotExist', () => {
                this.model.set(this.name, null);
            });
        }
    })
);
