/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

Ext.namespace('tileLayer');
tileLayer.init = function (record) {
    tileLayer.defaultSql = record.data || "SELECT * FROM " + record.f_table_schema + "." + record.f_table_name;
    tileLayer.classId = record._key_;

    tileLayer.grid = new Ext.grid.PropertyGrid({
        id: 'propGridTiles',
        autoHeight: true,
        modal: false,
        region: 'west',
        frame: false,
        border: false,
        style: {
            borderBottom: '1px solid #d0d0d0'
        },
        propertyNames: {
            meta_size: 'Meta tile size' + __('Number of columns and rows to use for metatiling. Defaults to 3.', true),
            meta_buffer: 'Meta buffer size (px)' + __('Area around the tile or metatile that will be cut off to prevent some edge artifacts.', true),
            ttl: 'Time to live (TTL)' + __('This is expressed as number of seconds after creation date of the tile. This is the value that will be set in the HTTP Expires and Cache-Control headers, and has no effect on the actual expiration of tiles in the caches.', true),
            auto_expire: 'Auto expire' + __('Tiles older (in seconds) than this value will be re-requested and updated in the cache. Note that this will only delete tiles from the cache when they are accessed: You cannot use this configuration to limit the size of the created cache. Note that, if set, this value overrides the value given by "Time to live".', true),
            format: 'Format' + __('Image format that will be used to return tile data to clients. Defaults to PNG.', true),
            lock: 'Lock' + __("Lock the tile cache, so it can not be busted.", true),
            layers: 'Layers' + __("Merged other layers on top of this one. Comma separated list of schema qulified layers names.", true),
            s3_tile_set: 'S3 tile set name' + __("Only apply to S3 type cache. Default to layer name.", true),
            cache: 'Cache' + __("Choose cache type", true),
        },

        customEditors: {
            'opacity': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'label_max_scale': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'label_min_scale': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'ttl': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'auto_expire': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'meta_size': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'meta_buffer': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'maxscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'minscaledenom': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'geotype': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: ['Default', 'POINT', 'LINE', 'POLYGON'],
                editable: false,
                triggerAction: 'all'
            }), {}),
            'cluster': new Ext.grid.GridEditor(new Ext.form.NumberField({
                decimalPrecision: 0,
                decimalSeparator: '¤'// Some strange char nobody is using
            }), {}),
            'format': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                displayField: 'name',
                valueField: 'value',
                mode: 'local',
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [
                        {
                            name: 'PNG',
                            value: 'PNG'
                        }, {
                            name: 'JPEG, low quality',
                            value: 'jpeg_low'
                        }, {
                            name: 'JPEG, medium quality',
                            value: 'jpeg_medium'
                        }, {
                            name: 'JPEG, high quality',
                            value: 'jpeg_high'
                        }
                    ]
                }),
                editable: false,
                triggerAction: 'all',
                value: 'PNG'
            }), {}),
            'cache': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                displayField: 'name',
                valueField: 'value',
                mode: 'local',
                store: new Ext.data.JsonStore({
                    fields: ['name', 'value'],
                    data: [
                        {
                            name: 'Disk',
                            value: 'disk'
                        }, {
                            name: 'SQLite',
                            value: 'sqlite'
                        }, {
                            name: 'S3',
                            value: 's3'
                        }/*, {
                            name: 'Berke',
                            value: 'bdb'
                        }*/
                    ]
                }),
                editable: false,
                triggerAction: 'all',
                value: 'PNG'
            }), {})
        },
        viewConfig: {
            forceFit: true,
            scrollOffset: 2 // the grid will never have scrollbars
        },
        tbar: [
            {
                text: '<i class="fa fa-check"></i> ' + __('Update'),
                //iconCls : 'silk-accept',
                handler: function () {
                    var grid = Ext.getCmp("propGridTiles");
                    var id = Ext.getCmp("configStore");
                    var source = grid.getSource();
                    var param = {
                        data: source
                    };
                    param = Ext.util.JSON.encode(param);

                    Ext.Ajax.request({
                        url: '/controllers/tile/index/' + tileLayer.classId,
                        method: 'put',
                        params: param,
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function (response) {
                            writeMapCacheFile(record._key_);
                            App.setAlert(App.STATUS_NOTICE, __("The layer settings are updated"));
                        },
                        failure: function (response) {
                            Ext.MessageBox.show({
                                title: 'Failure',
                                msg: __(Ext.decode(response.responseText).message),
                                buttons: Ext.MessageBox.OK,
                                width: 400,
                                height: 300,
                                icon: Ext.MessageBox.ERROR
                            });
                        }
                    });
                }
            }
        ]
    });
};




