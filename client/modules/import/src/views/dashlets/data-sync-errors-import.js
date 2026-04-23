/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/dashlets/data-sync-errors-import', 'views/dashlets/abstract/base',
    Dep => Dep.extend({

        _template: '<div class="list-container">{{{list}}}</div>',

        collectionUrl: 'ImportFeed/syncErrorStatistics',

        actionRefresh: function () {
            this.collection.fetch();
        },

        afterRender: function () {
            this.getCollectionFactory().create('DataSyncErrorsImportDashlet', function (collection) {
                this.collection = collection;

                collection.url = this.collectionUrl;
                collection.maxSize = this.getOption('displayRecords');

                this.listenToOnce(collection, 'sync', function () {
                    this.createView('list', 'views/record/list', {
                        el: this.getSelector() + ' > .list-container',
                        collection: collection,
                        rowActionsDisabled: true,
                        checkboxes: false,
                        listLayout: [
                            {
                                name: 'name',
                                width: '60'
                            },
                            {
                                name: 'feeds',
                                width: '20',
                                view: "import:views/dashlets/fields/link-feeds"
                            },
                            {
                                name: 'jobs',
                                width: '20',
                                view: "import:views/dashlets/fields/link-jobs"
                            }
                        ],
                    }, view => {
                        view.listenTo(view, 'after:render', () => {
                            let totalJobs = 0;
                            let totalFeeds = 0;
                            collection.each(model => {
                                totalFeeds += model.get('feeds');
                                totalJobs += model.get('jobs');
                            });
                            view.$el.find('table.table tbody').append(
                                `<tr data-id="total" class="list-row">
                                    <td class="cell" data-name="name" width="60%"><b>${this.translate('Total', 'labels', 'Global')}</b></td>
                                    <td class="cell" data-name="feeds" width="20%"><b>${parseInt(totalFeeds)}</b></td>
                                    <td class="cell" data-name="jobs" width="20%"><b>${parseInt(totalJobs)}</b></td>
                                </tr>'`
                            );
                            $('div[data-name="DataSyncErrorsImport"] .table.full-table thead th:first-child div').css('display', 'none');
                        });

                        view.render();
                    });
                }.bind(this));
                collection.fetch();

            }, this);
        },

    })
);

