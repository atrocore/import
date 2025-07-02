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
                this.model.set(this.name, 'configurator');
                this.reRender();
            });

            this.listenTo(this.model, `change:${this.name}`, () => {
                if (!this.model.get('description') && this.model.isNew()) {
                    this.model.set('description', this.getMetadata().get(`app.processingTypes.${this.model.get(this.name)}.description`));
                }
            })
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

        initInlineEdit() {
            Dep.prototype.initInlineEdit.call(this);

            if (this.model.get(this.name) !== 'configurator') {
                this.initShowCodeModal();
            }
        },

        initShowCodeModal() {
            const $cell = this.getCellElement();
            const inlineActions = this.getInlineActionsContainer();

            $cell.find('.show-code').parent().remove();

            const $link = $(`<a href="javascript:" class="code-link hidden" title="${this.translate('phpCode')}"><i class="ph ph-code show-code"></i></a>`);

            if (inlineActions.size()) {
                inlineActions.prepend($link);
            } else {
                $cell.prepend($link);
            }

            $link.on('click', () => {
                this.notify('Loading...');
                this.createView('dialog', 'import:views/import-feed/modals/show-code', {model: this.model}, dialog => {
                    dialog.render();
                    this.notify(false);
                });
            });

            $cell.on('mouseenter', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $link.removeClass('hidden');
                }
            }).on('mouseleave', e => {
                e.stopPropagation();
                if (this.mode === 'detail') {
                    $link.addClass('hidden');
                }
            });
        },

    })
);