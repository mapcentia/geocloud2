Ext.namespace('attributeForm');
Ext.namespace("filter");
attributeForm.init = function (layer, geomtype) {
    Ext.QuickTips.init();
    // create attributes store
    attributeForm.attributeStore = new GeoExt.data.AttributeStore({
        url: '/wfs/' + screenName + '/' + schema + '?REQUEST=DescribeFeatureType&TYPENAME=' + layer,
        listeners: {
            load: {
                scope: this,
                fn: function (_store) {
                    attributeForm.attributeStoreCopy = new Ext.data.ArrayStore();
                    _store.each(function (record) {
                        var match = /gml:((Multi)?(Point|Line|Polygon|Curve|Surface)).*/.exec(record.get("type"));
                        if (!match) {
                            var newDataRow = {"name": record.get("name"), "value": null};
                            var newRecord = new attributeForm.attributeStore.recordType(newDataRow);
                            attributeForm.attributeStoreCopy.add(newRecord);
                        }
                    }, this);
                    filter.filterBuilder = new gxp.FilterBuilder({
                        attributes: attributeForm.attributeStoreCopy,
                        allowGroups: false
                    });
                    filter.queryPanel = new Ext.Panel({
                        id: "uploadpanel",
                        frame: false,
                        region: "center",
                        border: false,
                        bodyStyle: {
                            background: '#ffffff',
                            padding: '7px'
                        },
                        tbar: ["->",
                            {
                                text: "<i class='fa fa-download'></i> " + __("Load"),
                                //iconCls: "icon-find",
                                disabled: false,
                                handler: function () {
                                    filter.queryPanel.query();
                                    filter.win.close();
                                }
                            }],
                        query: function () {
                            var filters = filter.filterBuilder.getFilter(), valid = true, timeslice;
                            if (typeof filters.filters === "object") {
                                $.each(filters.filters, function (k, v) {
                                    if (v === false) {
                                        valid = false;
                                    }
                                });
                            }
                            if (typeof Ext.getCmp('timeSliceField').items.items[0].items.items[0].value !== "undefined" && filter.filterBuilder.timeSliceQuery === true) {
                                timeslice = Ext.getCmp('timeSliceField').items.items[0].items.items[0].value;
                            }
                            if (valid) {
                                if ((layerBeingEditing) && (!timeslice)) {
                                    var protocol = store.proxy.protocol;
                                    protocol.defaultFilter = filter.filterBuilder.getFilter();
                                    saveStrategy.layer.refresh();
                                } else {
                                    startWfsEdition(layer, geomtype, filters, false, timeslice);
                                }
                            }
                        },
                        items: [filter.filterBuilder]
                    });
                    filter.win = new Ext.Window({
                        title: __("Load features"),
                        modal: false,
                        layout: 'fit',
                        initCenter: true,
                        border: false,
                        width: 400,
                        height: 400,
                        closeAction: 'hide',
                        plain: true,
                        items: [new Ext.Panel({
                            frame: false,
                            border: false,
                            layout: 'border',
                            items: [filter.queryPanel]
                        })]
                    });
                }
            }
        }
    });
    attributeForm.form = new Ext.form.FormPanel({
        autoScroll: true,
        region: 'center',
        disabled: true,
        viewConfig: {
            forceFit: true
        },
        border: false,
        labelWidth: 150,
        bodyStyle: {
            background: '#ffffff',
            padding: '7px'
        },
        defaults: {
            //width: 150,
            maxLengthText: "too long",
            minLengthText: "too short",
            anchor: '100%'
        },
        plugins: [
            new GeoExt.plugins.AttributeForm({
                attributeStore: attributeForm.attributeStore
            })
        ],
        buttons: [
            {
                //iconCls: 'silk-add',
                text: "<i class='icon-ok btn-gc'></i> " + __("Update table"),
                handler: function () {
                    if (attributeForm.form.form.isValid()) {
                        var record = grid.getSelectionModel().getSelected();
                        attributeForm.form.getForm().updateRecord(record);
                        var feature = record.get("feature");
                        if (feature.state !== OpenLayers.State.INSERT) {
                            feature.state = OpenLayers.State.UPDATE;
                        }
                        //attributeForm.win.close();
                    } else {
                        var s = '';
                        Ext.iterate(detailForm.form.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                    }
                }
            }
        ]
    });
    attributeForm.attributeStore.load();
};
function getFieldType(attrType) {
    "use strict";
    return ({
        "xsd:boolean": "boolean",
        "xsd:int": "int",
        "xsd:integer": "int",
        "xsd:short": "int",
        "xsd:long": "int",
        "xsd:date": "date",
        "xsd:string": "string",
        "xsd:float": "float",
        "xsd:double": "float",
        "xsd:decimal": "float",
        "gml:PointPropertyType": "int"
    })[attrType];
}

