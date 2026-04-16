/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('import:views/import-feed/fields/source-fields', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            ['file', 'sheet', 'format', 'fileFieldDelimiter', 'fileTextQualifier', 'isFileHeaderRow', 'rootNode', 'excludedNodes', 'keptStringNodes'].forEach(fieldName => {
                let action = fieldName === 'file' ? 'fileUpdate' : 'change:' + fieldName;
                this.listenTo(this.model, action, () => {
                    if (this.getParentView().getView(fieldName).mode === 'edit') {
                        this.loadFileColumns(action);
                    }
                });
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail') {
                if (this.$el.height() > 300) {
                    this.$el.css('max-height', '300px');
                    this.$el.css('overflow-x', 'hidden');
                    this.$el.css('overflow-y', 'scroll');
                }
            }

            if (this.mode === 'detail' && ['JSON', 'XML'].includes(this.model.get('format'))) {
                let items = [];
                (this.model.get(this.name) || []).forEach(column => {
                    let parts = column.split('.');
                    let last = parts.pop();
                    if (parts.length === 0) {
                        items.push(last);
                    } else {
                        items.push('<span style="color: #bbb">' + parts.join('.') + '</span>.' + last);
                    }
                });

                this.$el.html(`<div class="value-container">${items.join(', ')}</div>`);
            }
        },

        readSourceFieldsFromJob(jobId) {
            this.ajaxGetRequest(`Job/${jobId}`).success(queueItem => {
                if (queueItem.status === 'Canceled') {
                    $('.attachment-upload .remove-attachment').click();
                    this.model.set('sourceFields', []);
                    this.$el.html('');
                } else if (queueItem.status === 'Success') {
                    this.model.set('sourceFields', queueItem.payload.sourceFields);
                } else {
                    setTimeout(() => {
                        this.readSourceFieldsFromJob(jobId);
                    }, 4000);
                }
            }).error(response => {
                $('.attachment-upload .remove-attachment').click();
                this.model.set('sourceFields', []);
                this.$el.html('');
            });
        },

        loadFileColumns(action) {
            const fileId = this.model.get('fileId');
            if (!fileId) {
                return;
            }

            this.model.set('sourceFields', [], {silent: true});
            this.selected = [];
            this.reRender();

            const data = {
                fileId: fileId,
                format: this.model.get('format'),
                delimiter: this.model.get('fileFieldDelimiter'),
                enclosure: this.model.get('fileTextQualifier'),
                rootNode: this.model.get('rootNode'),
                excludedNodes: this.model.get('excludedNodes'),
                keptStringNodes: this.model.get('keptStringNodes'),
                isHeaderRow: !!this.model.get('isFileHeaderRow'),
                sheet: this.model.get('sheet')
            };

            const proceed = (fileSize) => {
                const isLarge = fileSize > 2 * 1024 * 1024;

                if (isLarge) {
                    this.ajaxPostRequest('ImportFeed/parseFileColumnsAsync', data).success(response => {
                        Backbone.trigger('showQueuePanel');
                        this.$el.html('<img alt="preloader" class="preloader" style="height:19px;margin-top:6px;margin-left:-8px" src="client/img/atro-loader.svg" />');
                        this.readSourceFieldsFromJob(response.jobId);
                    });
                } else {
                    let options = {};
                    if (action !== 'fileUpdate') {
                        options.async = false;
                    }
                    this.ajaxPostRequest('ImportFeed/parseFileColumns', data, options).success(response => {
                        this.model.set('sourceFields', response, {silent: true});
                        this.selected = response;
                        this.reRender();
                    });
                }
            };

            if (this._cachedFileId === fileId && this._cachedFileSize !== undefined) {
                proceed(this._cachedFileSize);
            } else {
                this.ajaxGetRequest(`File/${fileId}`).success(file => {
                    this._cachedFileId = fileId;
                    this._cachedFileSize = file.fileSize || 0;
                    proceed(this._cachedFileSize);
                });
            }
        }
    })
);