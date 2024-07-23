/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/url-headers-item', 'views/fields/base', function (Dep) {
    return Dep.extend({

        editTemplate: 'import:import-configurator-item/fields/url-headers/item/edit',

        events: {
            'click [data-action=remove-header]': function (e) {
                this.trigger('removeHeader');
            }
        },

        data() {
            return {
                index: this.model.get('index') ?? 0,
                key: this.model.get('key'),
                value: this.model.get('value')
            }
        },

        afterRender() {
            this.createView('header-key', 'views/fields/varchar', {
                model: this.model,
                name: 'key',
                label: null,
                mode: 'edit',
                el: this.options.el + ' .key-container',
                defs: {
                    params: {
                        required: true
                    }
                }
            }, view => {
                view.render();
            });

            this.createView('header-value', 'views/fields/varchar', {
                model: this.model,
                name: 'value',
                label: null,
                mode: 'edit',
                el: this.options.el + ' .value-container',
                defs: {
                    params: {
                        required: true
                    }
                }
            }, view => {
                view.render();
            });
        }
    })
});
