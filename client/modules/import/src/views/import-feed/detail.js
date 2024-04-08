/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/detail', 'views/detail',
    Dep => {

        return Dep.extend({

            setup() {
                Dep.prototype.setup.call(this);

                this.relatedAttributeFunctions['configuratorItems'] = () => {
                    return {
                        "entity": this.model.get('entity'),
                        "sourceFields": this.model.get('sourceFields')
                    }
                };

                this.listenTo(this.model, 'after:save', () => {
                    $('.action[data-action=refresh][data-panel=configuratorItems]').click();
                });

            },

        });
    });