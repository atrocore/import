/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/value-extractor', 'views/fields/varchar',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.reRender()
            })
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            const measureId = this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.measureId`);

            if (measureId &&
                (!this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.unitField`) || this.getMetadata().get(`entityDefs.${this.model.get('entity')}.fields.${this.model.get('name')}.attributeId`))) {
                this.show();
            } else {
                this.hide();
            }
        },

    })
);