/**
 * Copyright (c) 2008-2011 The Open Planning Project
 *
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @require util.js
 * @require widgets/RulePanel.js
 * @require widgets/StylePropertiesDialog.js
 * @requires OpenLayers/Renderer/SVG.js
 * @requires OpenLayers/Renderer/VML.js
 * @requires OpenLayers/Renderer/Canvas.js
 * @require OpenLayers/Style2.js
 * @require GeoExt/data/AttributeStore.js
 * @require GeoExt/widgets/VectorLegend.js
 * @require widgets/StylesDialog.js
 */

/** api: (define)
 *  module = gxp
 *  class = VectorStylesDialog
 *  base_link = `Ext.Container <http://extjs.com/deploy/dev/docs/?class=Ext.Container>`_
 */
Ext.namespace("gxp");

/** api: constructor
 *  .. class:: VectorStylesDialog(config)
 *
 *      Extend the GXP WMSStylesDialog to work with Vector Layers
 *      that originate from a WFS or local OpenLayers Features from upload or drawing.
 */
gxp.VectorStylesDialog = Ext.extend(gxp.StylesDialog, {
    attributeStore: null,

    /** private: method[initComponent]
     */
    initComponent: function () {
        gxp.VectorStylesDialog.superclass.initComponent.apply(this, arguments);

        // We cannot create/delete new styles for Vector Layers (StyleMap restriction)
        // this.items.removeAt(1);
        this.initialConfig.styleName = 'default';
//        this.items.get(0).setDisabled(true);
        this.items.get(1).setDisabled(true);


        this.on({
            "styleselected": function (cmp, style) {
                var index = this.stylesStore.findExact("name", style.name);
                if (index !== -1) {
                    this.selectedStyle = this.stylesStore.getAt(index);
                }
            },
            "modified": function (cmp, style) {
                cmp.saveStyles();
            },
            "beforesaved": function () {
                this._saving = true;
            },
            "saved": function () {
                delete this._saving;
            },
            "savefailed": function () {
                Ext.Msg.show({
                    title: this.errorTitle,
                    msg: this.errorMsg,
                    icon: Ext.MessageBox.ERROR,
                    buttons: {ok: true}
                });
                delete this._saving;
            },
            "render": function () {
                gxp.util.dispatch([this.getStyles], function () {
                    this.enable();
                }, this);
            },
            scope: this
        });

    },


    /** private: method[addRulesFieldSet]
     *  :return: ``Ext.form.FieldSet``
     *
     *  Creates the rules fieldSet and adds it to this container.
     */
    addRulesFieldSet: function() {
        var rulesFieldSet = gxp.VectorStylesDialog.superclass.addRulesFieldSet.apply(this, arguments);
        // Disable Add for now: it does not work well.
        this.items.get(3).get(0).disable();
        return rulesFieldSet;
    },

    /** private: method[disableConditional]
     *
     *  Disable item, like in Rule toolbar when customStyling is enabled.
     */
    disableConditional: function(item) {
        var layer = this.layerRecord.getLayer();
        if (item && layer.customStyling) {
            item.disable();
        }
    },

    onRuleSelected: function(cmp, rule) {
        gxp.VectorStylesDialog.superclass.onRuleSelected.call(this, cmp, rule);
        // enable the Remove, Edit and Duplicate buttons
        var tbItems = this.items.get(3).items;
        // Edit button
        // tbItems.get(2).enable();
        // Duplicate button
        this.disableConditional(tbItems.get(3));
        // cmp.items.get(0).focus();
    },

    /** private: method[editRule]
     */
    editRule: function () {
        var rule = this.selectedRule;
        // May need TextSymbolizer if here first time and feature has data attrs
        if (!this.textSym && this.attributeStore && this.attributeStore.data.getCount() > 0) {
            rule.symbolizers.push(this.createSymbolizer('Text', {}));
            this.textSym = true;
        }
        var origRule = rule.clone();

        var ruleDlg = new this.dialogCls({
            title: String.format(this.ruleWindowTitle,
                rule.title || rule.name || this.newRuleText),
            shortTitle: rule.title || rule.name || this.newRuleText,
            layout: "fit",
            width: 320,
            height: 490,
            pageX: 150,
            pageY: 100,
            modal: true,
            listeners: {
                hide: function () {
                    if (gxp.ColorManager.pickerWin) {
                        gxp.ColorManager.pickerWin.hide();
                    }
                },
                scope: this
            },
            items: [
                {
                    xtype: "gxp_rulepanel",
                    ref: "rulePanel",
                    symbolType: rule.symbolType ? rule.symbolType : this.symbolType,
                    rule: rule,
                    attributes: this.attributeStore,
                    autoScroll: true,
                    border: false,
                    defaults: {
                        autoHeight: true,
                        hideMode: "offsets"
                    },
                    listeners: {
                        "change": this.saveRule,
                        "tabchange": function () {
                            if (ruleDlg instanceof Ext.Window) {
                                ruleDlg.syncShadow();
                            }
                        },
                        scope: this
                    }
                }
            ],
            bbar: ["->", {
                text: this.cancelText,
                iconCls: "cancel",
                handler: function () {
                    this.saveRule(ruleDlg.rulePanel, origRule);
                    ruleDlg.destroy();
                },
                scope: this
            }, {
                text: this.saveText,
                iconCls: "save",
                handler: function () {
                    ruleDlg.destroy();
                }
            }]
        });

        // Remove all text symbolizer-related tabs when no attributes exist
        var removeItems, i;
        if (this.attributeStore.data.getCount() == 0) {
            var rulePanel = ruleDlg.findByType('gxp_rulepanel')[0];
            removeItems = rulePanel.items.getRange(1, 2);
            for (i = 0; i < removeItems.length; i++) {
                rulePanel.remove(removeItems[i]);
            }

        }

        // Remove advanced Label symbolizer widget
        // items not or hard to support via OL Symbolizers and Printing
        var textSymbolizers = ruleDlg.findByType('gxp_textsymbolizer');
        if (textSymbolizers && textSymbolizers.length == 1) {
            var textSymbolizer = textSymbolizers[0];
            removeItems = textSymbolizer.items.getRange(3, 7);

            // Remove all from range
            for (i = 0; i < removeItems.length; i++) {
                textSymbolizer.remove(removeItems[i]);
            }
        }
        this.showDlg(ruleDlg);
    },

    /** private: method[createSymbolizer]
     *  Create OpenLayers Symbolizer object.
     *  :arg symbol: ``String`` symbolizer type: 'Point', 'Line' or 'Polygon'.
     *  :arg styleHash: ``Object`` object with OpenLayers Style properties.
     */
    createSymbolizer: function (symbol, styleHash) {
        var Type = eval('OpenLayers.Symbolizer.' + symbol);
        return new Type(styleHash);
    },

    /** private: method[prepareStyle]
     *  :arg style: ``Style`` object to be cloned and prepared for GXP editing.
     */
    prepareStyle: function (layer, styl, name) {
        // Makes deep copy
        var style = styl.clone();
        style.isDefault = (name === 'default');
        style.name = name;
        style.title = name + ' style';
        style.description = name + ' style for this layer';
        style.layerName = layer.name;

        var symbolizers = [], symbolizer, symbol, rule;
        if (style.rules && style.rules.length > 0) {
            for (var i = 0; i < style.rules.length; i++) {
                rule = style.rules[i];
                symbolizers = [];

                // GXP Style Editing needs symbolizers array in Rule object
                // while Vector/Style drawing needs symbolizer hash, so convert for GXP here.
                for (symbol in rule.symbolizer) {
                    symbolizer = rule.symbolizer[symbol];
                    if (!symbolizer.CLASS_NAME) {
                        // In some cases the symbolizer may be a hash: create corresponding class object
                        symbolizer = this.createSymbolizer(symbol, symbolizer);
                    } else {
                        symbolizer = symbolizer.clone();
                    }
                    symbolizers.push(symbolizer);
                }
                rule.symbolizers = symbolizers;
                rule.symbolizer = undefined;
            }
            // style.defaultsPerSymbolizer = true;

        } else if (layer.customStyling) {
            // One rule per symbol for custom styling, also for Layers with more geom-types
            var symbols = ['Point', 'Line', 'Polygon'];
            style.rules = [];
            var symbolizerStyle = style.defaultStyle;
            for (var s = 0; s < symbols.length; s++) {
                symbol = symbols[s];
                rule = new OpenLayers.Rule({title: symbol, symbolType: symbol, symbolizers: [this.createSymbolizer(symbol, symbolizerStyle)]});
                style.rules.push(rule);
            }
            style.defaultsPerSymbolizer = false;
        }
        else {
            // GXP Style Editing needs symbolizers array in Rule object
            // while Vector/Style drawing needs symbolizer hash...
            symbol = 'Polygon';
            if (layer && layer.features && layer.features.length > 0) {
                var geom = layer.features[0].geometry;
                if (geom) {
                    if (geom.CLASS_NAME.indexOf('Point') > 0) {
                        symbol = 'Point';
                    } else if (geom.CLASS_NAME.indexOf('Line') > 0) {
                        symbol = 'Line';
                    }
                }
            }
            symbolizer = this.createSymbolizer(symbol, style.defaultStyle);
//            delete style.defaultStyle;
            symbolizers = [symbolizer];

            style.rules = [new OpenLayers.Rule({title: style.name, symbolizers: symbolizers})];
            // style.defaultsPerSymbolizer = true;
        }
        return style;
    },

    /** private: method[getStyles]
     *  :arg callback: ``Function`` function that will be called when the
     *      request result was returned.
     */
    getStyles: function () {
        if (this.first) {
            return;
        }
        var layer = this.layerRecord.getLayer();
        if (this.editable) {
            this.first = true;
            var initialStyle = this.initialConfig.styleName;
            this.selectedStyle = this.stylesStore.getAt(this.stylesStore.findExact("name", initialStyle));

            try {

                // add userStyle objects to the stylesStore
                var userStyles = [];

                // Some layers are styled via the "Style" config prop: convert to a StyleMap
                if (layer.style && layer.styleMap) {
                    OpenLayers.Util.extend(layer.styleMap.styles['default'].defaultStyle, layer.style);
                    delete layer.style;
                }
                for (var styleName in layer.styleMap.styles) {
                    // Do only default style for now.
                    if (styleName == 'default') {
                        userStyles.push(this.prepareStyle(layer, layer.styleMap.styles[styleName], styleName));
                    }
                }

                // our stylesStore comes from the layerRecord's styles - clear it
                // and repopulate from GetStyles
                this.stylesStore.removeAll();
                this.selectedStyle = null;

                var userStyle, record, index;
                for (var i = 0, len = userStyles.length; i < len; ++i) {
                    userStyle = userStyles[i];
                    // remove existing record - this way we replace styles from
                    // userStyles with inline styles.
                    index = this.stylesStore.findExact("name", userStyle.name);
                    index !== -1 && this.stylesStore.removeAt(index);
                    record = new this.stylesStore.recordType({
                        "name": userStyle.name,
                        "title": userStyle.title,
                        "abstract": userStyle.description,
                        "userStyle": userStyle
                    });
                    record.phantom = false;
                    this.stylesStore.add(record);
                    // set the default style if no STYLES param is set on the layer
                    if (!this.selectedStyle && (initialStyle === userStyle.name ||
                        (!initialStyle && userStyle.isDefault === true))) {
                        this.selectedStyle = record;
                    }
                }

                this.addRulesFieldSet();
                this.createLegend(this.selectedStyle.get("userStyle").rules);
                // this.createLegend();

                this.stylesStoreReady();
                this.markModified();
            }
            catch (e) {
                this.setupNonEditable();
            }
        } else {
            this.setupNonEditable();
        }
    },


    /** private: method[describeLayer]
     *  :arg callback: ``Function`` function that will be called when the
     *      request result was returned.
     */
    describeLayer: function (callback) {

        if (this.layerDescription) {
            // always return before calling callback
            callback.call(this);
        }

        var layer = this.layerRecord.getLayer();
        if (layer.protocol && layer.protocol.CLASS_NAME.indexOf('.WFS') > 0) {
            this.wfsLayer = {};
            this.wfsLayer.owsURL = layer.protocol.url.replace('?', '');
            this.wfsLayer.owsType = 'WFS';
            this.wfsLayer.typeName = layer.protocol.featureType;
        }

        // Attribute types: either from WFS or local features
        var self = this;
        if (this.wfsLayer) {
            // WFS Layer: use DescribeFeatureType to get attribute-names/types
            this.attributeStore = new GeoExt.data.AttributeStore({
                url: this.wfsLayer.owsURL,
                baseParams: {
                    "SERVICE": "WFS",
                    "VERSION": "1.1.0",
                    "REQUEST": "DescribeFeatureType",
                    "TYPENAME": this.wfsLayer.typeName
                },
                // method: "GET",
                // disableCaching: false,
                autoLoad: true,
                listeners: {
                    'load': function (store) {
                        self.layerDescription = self.attributeStore;
                        // The TextSymbolizer calls load() as well, leading to loop
                        // when we would call editRule() again...
                        // prevent by makin load() function empty...
                        // We should fix this in TextSymbolizer but there is a risk to break other stuff...
                        store.load = function () {
                        };
                        callback.call(self);
                    },
                    scope: this
                }
            });
        } else {
            // Attribute store will be derived from local features
            this.attributeStore = new Ext.data.Store({
                // explicitly create reader
                // id for each record will be the first element}
                reader: new Ext.data.ArrayReader(
                    {idIndex: 0},
                    Ext.data.Record.create([
                        {name: 'name'}
                    ])
                )
            });

            // Create attribute meta data from feature-attributes
            var myData = [];
            if (layer && layer.features && layer.features.length > 0) {
                var attrs = layer.features[0].attributes;
                for (var attr in attrs) {
                    myData.push([attr]);
                }
            }
            this.attributeStore.loadData(myData);
            // Silence the proxy (must be better way...)
            this.attributeStore.proxy = {request: function () {
            }};
            this.layerDescription = this.attributeStore;

            callback.call(this);
        }

    },

    /** private: method[addStylesCombo]
     *
     *  Adds a combo box with the available style names found for the layer
     *  in the capabilities document to this component's stylesFieldset.
     */
    addStylesCombo: function () {
        if (this.combo) {
            return;
        }
        var store = this.stylesStore;
        this.combo = new Ext.form.ComboBox(Ext.apply({
            fieldLabel: this.chooseStyleText,
            store: store,
            editable: false,
            displayField: "title",
            valueField: "name",
            value: this.selectedStyle ? this.selectedStyle.get("title") : "default",
            disabled: !store.getCount(),
            mode: "local",
            typeAhead: true,
            triggerAction: "all",
            forceSelection: true,
            anchor: "100%",
            listeners: {
                "select": function (combo, record) {
                    this.changeStyle(record);
                    if (!record.phantom && !this._removing) {
                        this.fireEvent("styleselected", this, record.get("name"));
                    }
                },
                scope: this
            }
        }, this.initialConfig.stylesComboOptions));
        // add combo to the styles fieldset
        this.items.get(0).add(this.combo);
        this.doLayout();
    },

    /** private: method[createLegendImage]
     *  :return: ``GeoExt.LegendImage`` or undefined if none available.
     *
     *  Creates a legend image for the first style of the current layer. This
     *  is used when GetStyles is not available from the layer's WMS.
     */
    createLegendImage: function () {
        return new GeoExt.VectorLegend({
            showTitle: false,
            layerRecord: this.layerRecord,
            autoScroll: true
        });
    },

    /** private: method[updateRuleRemoveButton]
     *  Enable/disable the "Remove" button to make sure that we don't delete
     *  the last rule.
     */
    updateRuleRemoveButton: function() {
        gxp.VectorStylesDialog.superclass.updateRuleRemoveButton.apply(this, arguments);
        this.disableConditional(this.items.get(3).items.get(1));
    },

    /** private: method[updateStyleRemoveButton]
     *  We cannot remove styles for Vector styles so always disable remove.
     */
    updateStyleRemoveButton: function () {
        this.items.get(1).items.get(1).setDisabled(true);
    }

});

/** api: function[createGeoServerStylerConfig]
 *  :arg layerRecord: ``GeoExt.data.LayerRecord`` Layer record to configure the
 *      dialog for.
 *  :arg url: ``String`` Optional. Custaom URL for the GeoServer REST endpoint
 *      for writing styles.
 *
 *  Creates a configuration object for a :class:`gxp.VectorStylesDialog` with a
 *  :class:`gxp.plugins.GeoServerStyleWriter` plugin and listeners for the
 *  "styleselected", "modified" and "saved" events that take care of saving
 *  styles and keeping the layer view updated.
 */
gxp.VectorStylesDialog.createVectorStylerConfig = function (layerRecord) {
    return {
        xtype: "gxp_vectorstylesdialog",
        layerRecord: layerRecord,
        listeners: {
            hide: function () {
                alert('hide');
            }
        },
        plugins: [
            {
                ptype: "gxp_vectorstylewriter"
            }
        ]
    };
};

/** api: xtype = gxp_vectorstylesdialog */
Ext.reg('gxp_vectorstylesdialog', gxp.VectorStylesDialog);

(function () {
    // register the color manager with every color field
    Ext.util.Observable.observeClass(Ext.ColorPalette);
    Ext.ColorPalette.on({
        render: function () {
            if (gxp.ColorManager.pickerWin) {
                gxp.ColorManager.pickerWin.setPagePosition(200, 100);
            }
        }
    });
    // Hide color-picker window on color select
    Ext.ColorPalette.on({
        select: function () {
            if (gxp.ColorManager.pickerWin) {
                gxp.ColorManager.pickerWin.hide();
            }
        }
    });
})();
