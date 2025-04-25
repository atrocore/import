/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/record/panels/configurator-items', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.collection, 'update', () => {
                this.collection.forEach(model => {
                    if (model.get('entityAttributeId') && model.get('fieldDefs')) {
                        this.getMetadata().data.entityDefs[model.get('entity')].fields[model.get('name')] = model.get('fieldDefs');
                        this.getLanguage().data[model.get('entity')].fields[model.get('name')] = model.get('fieldDefs').label;
                    }
                })
            });
        },

    })
);