/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/column', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            this.params.options = this.model.get('sourceFields') || [];
            this.translatedOptions = {};

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                this.model.set('column', null);
            });

            this.listenTo(this.model, 'change:default change:defaultId change:defaultIds', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list') {
                let originalValue = this.model.get(this.name) || [];

                let sourceFields = this.model.get('sourceFields') || [];

                let style = '';

                let items = [];
                originalValue.forEach(column => {
                    if (!sourceFields.includes(column)) {
                        style = 'style="color:red"';
                    }
                    let parts = column.split('.');
                    let last = parts.pop();
                    items.push(last);
                });

                this.$el.html('<span ' + style + ' title="' + originalValue.join(', ') + '">' + items.join(', ') + '</span>');
            }
        }
    })
);