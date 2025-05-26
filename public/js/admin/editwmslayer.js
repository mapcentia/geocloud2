/*
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

Ext.namespace('wmsLayer');
wmsLayer.init = function (record) {
    var checkboxRender = function (d) {
        var checked = d ? 'property-grid-check-on' : '';
        return '<div class="' + checked + '">';
    };
    wmsLayer.fieldsForStore = [];
    wmsLayer.numFieldsForStore = [];
    wmsLayer.fieldsForStoreBrackets = [];
    wmsLayer.defaultSql = record.data || "SELECT * FROM " + record.f_table_schema + "." + record.f_table_name;
    wmsLayer.legendUrl = record.legend_url;

    $.ajax({
        url: '/controllers/table/columns/' + record.f_table_schema + '.' + record.f_table_name,
        async: false,
        dataType: 'json',
        type: 'GET',
        success: function (data) {
            var response = data,
                forStore = response.forStore,
                i;
            wmsLayer.fieldsForStore.push("");
            wmsLayer.numFieldsForStore.push("");
            wmsLayer.fieldsForStoreBrackets.push("");
            for (i in forStore) {
                if (forStore.hasOwnProperty(i)) {
                    wmsLayer.fieldsForStore.push(forStore[i].name);
                    wmsLayer.fieldsForStoreBrackets.push("[" + forStore[i].name + "]");
                    if (forStore[i].type === "number" || forStore[i].type === "int" || forStore[i].type === "double" || forStore[i].type === "decimal" ) {
                        wmsLayer.numFieldsForStore.push(forStore[i].name);
                    }
                }
            }
        }
    });
    wmsLayer.classId = record._key_;

    wmsLayer.grid = new Ext.grid.PropertyGrid({
        id: 'propGridLayer',
        autoHeight: true,
        modal: false,
        region: 'west',
        frame: false,
        border: false,
        style: {
            borderBottom: '1px solid #d0d0d0'
        },
        propertyNames: {
            label_column: __('Label item'),
            theme_column: 'Class item',
            opacity: 'Opacity',
            label_max_scale: __('Label max scale denominator') + __('Minimum scale at which this LAYER is labeled. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.', true), //LABELMAXSCALEDENOM
            label_min_scale: __('Label min scale denominator') + __('Maximum scale at which this LAYER is labeled. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.', true), //LABELMINSCALEDENOM
            cluster: 'Clustering distance',
            maxscaledenom: __('Max scale denominator') + __('Minimum scale at which this LAYER is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.', true),
            minscaledenom: __('Min scale denominator') + __('Maximum scale at which this LAYER is drawn. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.', true),
            symbolscaledenom: 'Symbol scale denominator' + __("The scale at which symbols and/or text appear full size. This allows for dynamic scaling of objects based on the scale of the map. If not set then this layer will always appear at the same size. Scale is given as the denominator of the actual scale fraction, for example for a map at a scale of 1:24,000 use 24000.", true),
            geotype: 'Geom type',
            offsite: 'Offsite' + __("This parameter tells MapServer what pixel values to render as background (or ignore). You can get the pixel values using image processing or image manipulation programs (i.e. Imagine, Photoshop, Gimp).", true),
            label_no_clip: 'No clipping of labels' + __("Can be used to skip clipping of shapes when determining associated label anchor points. This avoids changes in label position as extents change between map draws. It also avoids duplicate labels where features appear in multiple adjacent tiles when creating tiled maps.", true),
            polyline_no_clip: 'No clipping of polylines' + __("Can be used to skip clipping of shapes when rendering styled lines (dashed or styled with symbols). This avoids changes in the line styling as extents change between map draws. It also avoids edge effects where features appear in multiple adjacent tiles when creating tiled maps.", true),
            bands: 'Bands' + __("This directive allows a specific band or bands to be selected from a raster file. If one band is selected, it is treated as greyscale. If 3 are selected, they are treated as red, green and blue. If 4 are selected they are treated as red, green, blue and alpha (opacity). Example: 4,2,1", true)
        },

        customRenderers: {
            label_no_clip: checkboxRender,
            polyline_no_clip: checkboxRender,
        },

        customEditors: {
            'label_column': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
            'theme_column': new Ext.grid.GridEditor(new Ext.form.ComboBox({
                store: wmsLayer.fieldsForStore,
                editable: true,
                triggerAction: 'all'
            }), {}),
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
            'label_no_clip': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {}),
            'polyline_no_clip': new Ext.grid.GridEditor(new Ext.form.Checkbox({}), {})
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
                    var grid = Ext.getCmp("propGridLayer");
                    var id = Ext.getCmp("configStore");
                    var source = grid.getSource();
                    var param = {
                        data: source
                    };
                    param = Ext.util.JSON.encode(param);

                    Ext.Ajax.request({
                        url: '/controllers/tile/index/' + wmsLayer.classId,
                        method: 'put',
                        params: param,
                        headers: {
                            'Content-Type': 'application/json; charset=utf-8'
                        },
                        success: function (response) {
                            writeFiles(record._key_);
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
    wmsLayer.sqlForm = new Ext.FormPanel({
        frame: false,
        border: false,
        id: "sqlForm",
        labelWidth: 1,
        bodyStyle: 'padding: 10px 5px 0px 5px;',
        items: [
            {
                html: '<table>' +
                    '<tr class="x-grid3-row"><td><b>SQL</b></td></tr>' +
                    '</table>',
                border: false,
                bodyStyle: 'padding-left: 3px'
            },
            {
                name: '_key_',
                xtype: 'hidden',
                value: record._key_

            },
            {
                xtype: 'textarea',
                width: '95%',
                height: 100,
                labelAlign: 'top',
                name: 'data',
                value: wmsLayer.defaultSql
            }
        ],
        buttons: [
            {
                text: '<i class="fa fa-check"></i> ' + __('Update SQL'),
                handler: function () {
                    var f = Ext.getCmp('sqlForm');
                    if (f.form.isValid()) {
                        var values = f.form.getValues();
                        values.data = encodeURIComponent(values.data);
                        var param = {
                            data: values
                        };
                        param = Ext.util.JSON.encode(param);
                        Ext.Ajax.request({
                            url: '/controllers/layer/records/_key_',
                            method: 'put',
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8'
                            },
                            params: param,
                            success: function () {
                                store.reload();
                                writeFiles(record._key_);
                                App.setAlert(App.STATUS_NOTICE, "Sql updated");
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
                    } else {
                        var s = '';
                        Ext.iterate(f.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                    }
                }
            }
        ]
    });
    wmsLayer.legendForm = new Ext.FormPanel({
        labelWidth: 70,
        frame: false,
        border: false,
        id: "legendForm",
        viewConfig: {
            forceFit: true
        },
        bodyStyle: 'padding: 10px 5px 0px 5px;',
        items: [{
            xtype: 'fieldset',
            title: __('Settings'),
            defaults: {
                anchor: '100%'
            },
            items: [
                {
                    name: '_key_',
                    xtype: 'hidden',
                    value: record._key_

                },
                {
                    xtype: 'textfield',
                    labelAlign: 'top',
                    fieldLabel: __('Image URL'),
                    name: 'legend_url',
                    value: wmsLayer.legendUrl
                }
            ]
        }],
        buttons: [
            {
                text: '<i class="fa fa-check"></i> ' + __('Update'),
                handler: function () {
                    var f = Ext.getCmp('legendForm');
                    if (f.form.isValid()) {
                        var values = f.form.getValues();
                        values.legend_url = encodeURIComponent(values.legend_url);
                        var param = {
                            data: values
                        };
                        param = Ext.util.JSON.encode(param);
                        Ext.Ajax.request({
                            url: '/controllers/layer/records/_key_',
                            method: 'put',
                            headers: {
                                'Content-Type': 'application/json; charset=utf-8'
                            },
                            params: param,
                            success: function () {
                                store.reload();
                                writeFiles(record._key_);
                                App.setAlert(App.STATUS_NOTICE, __("Legend URL updated"));
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
                    } else {
                        var s = '';
                        Ext.iterate(f.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                    }
                }
            }
        ]
    })
};




