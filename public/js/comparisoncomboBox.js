/**
 * Copyright (c) 2008 The Open Planning Project
 */

Ext.namespace("gxp.form");
gxp.form.ComarisonComboBox = Ext.extend(Ext.form.ComboBox, {
    
    allowedTypes: [
        [OpenLayers.Filter.Comparison.EQUAL_TO, "="],
        [OpenLayers.Filter.Comparison.NOT_EQUAL_TO, "<>"],
        [OpenLayers.Filter.Comparison.LESS_THAN, "<"],
        [OpenLayers.Filter.Comparison.GREATER_THAN, ">"],
        [OpenLayers.Filter.Comparison.LESS_THAN_OR_EQUAL_TO, "<="],
        [OpenLayers.Filter.Comparison.GREATER_THAN_OR_EQUAL_TO, ">="],
        [OpenLayers.Filter.Comparison.LIKE, "like"]
    ],  

    allowBlank: false,

    mode: "local",

    triggerAction: "all",

    width: 50,

    editable: false,
  
    initComponent: function() {
        var defConfig = {
            displayField: "name",
            valueField: "value",
            store: new Ext.data.SimpleStore({
                data: this.allowedTypes,
                fields: ["value", "name"]
            }),
            value: (this.value === undefined) ? this.allowedTypes[0][0] : this.value
        };
        Ext.applyIf(this, defConfig);
        
        gxp.form.ComarisonComboBox.superclass.initComponent.call(this);
    }
});

Ext.reg("gx_comparisoncombo", gxp.form.ComarisonComboBox);