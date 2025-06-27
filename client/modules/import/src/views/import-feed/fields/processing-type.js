/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/processing-type', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            this.prepareListOptions();

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entity', () => {
                this.prepareListOptions();
                this.reRender();
            });
        },

        prepareListOptions() {
            this.params.options = ['configurator'];
            this.translatedOptions = {'configurator': this.getLanguage().translateOption('configurator', 'processingType', 'ImportFeed')};

            $.each(this.getMetadata().get('app.processingTypes') || {}, (type, data) => {
                if (this.model.get('entity') === data.entityName) {
                    this.params.options.push(type);
                    this.translatedOptions[type] = data.label;
                }
            });
        },

    })
);