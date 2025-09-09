/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-configurator-item/fields/url-headers', 'views/fields/base', function (Dep) {
    return Dep.extend({

        editTemplate: 'import:import-configurator-item/fields/url-headers/edit',

        items: [],

        label: null,

        events: {
            'click [data-action=add-header]': function (e) {
                this.items.push(null);
                this.reRender();
            }
        },

        data() {
            return {
                headers: this.items
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.items = [];
            for (const [key, value] of Object.entries(this.model.get('urlHeaders') ?? {})) {
                this.items.push({key: key, value: value});
            }

            if (this.items.length === 0) {
                this.items.push(null);
            }

            this.options.labelText = null;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            for (let i = 0; i < this.items.length; i++) {
                this.getModelFactory().create(null, model => {
                    const item = this.items[i] ?? null;
                    model.set('index', i);
                    model.set('key', item?.key);
                    model.set('value', item?.value);

                    this.createView('header-item-' + i, 'import:views/import-configurator-item/fields/url-headers-item', {
                        model: model,
                        el: `${this.options.el} tr[data-key=${i}]`,
                        mode: 'edit'
                    }, view => {
                        view.render();

                        this.listenTo(model, 'change', e => {
                            const key = model.get('key') ?? '';
                            const value = model.get('value') ?? '';
                            this.items[i] = {key: key, value: value};
                        });

                        view.on('removeHeader', e => {
                            this.items.splice(i, 1);
                            view.remove();
                        });
                    });
                });
            }

            this.getLabelElement()?.remove();
        },

        fetch() {
            const result = {};
            for (const item of this.items) {
                if (!item?.key || !item?.value) {
                    continue;
                }

                result[item.key] = item.value;
            }

            this.model.set('urlHeaders', result);

            return {
                urlHeaders: result
            };
        },

        getLabelText() {
            return null;
        }
    })
});
