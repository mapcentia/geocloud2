/**
 * Copyright (c) 2009 The Open Planning Project
 */

/**
 * @include widgets/form/ComparisonComboBox.js
 */

Ext.namespace("gxp.form");
gxp.form.FilterField = Ext.extend(Ext.form.CompositeField, {

    /**
     * Property: filter
     * {OpenLayers.Filter} Optional non-logical filter provided in the initial
     *     configuration.  To retreive the filter, use <getFilter> instead
     *     of accessing this property directly.
     */
    filter: null,

    /**
     * Property: attributes
     * {GeoExt.data.AttributeStore} A configured attributes store for use in
     *     the filter property combo.
     */
    attributes: null,

    /**
     * Property: attributesComboConfig
     * {Object}
     */
    attributesComboConfig: null,

    initComponent: function () {

        if (!this.filter) {
            this.filter = this.createDefaultFilter();
        }
        if (!this.attributes) {
            this.attributes = new GeoExt.data.AttributeStore();
        }

        //console.log(attributeForm.attributeFormCopy);
        var defAttributesComboConfig = {
            xtype: "combo",
            store: this.attributes,
            editable: false,
            triggerAction: "all",
            allowBlank: false,
            displayField: "name",
            valueField: "name",
            mode: 'local',
            value: this.filter.property,
            listeners: {
                select: function (combo, record) {
                    this.filter.property = record.get("name");
                    this.fireEvent("change", this.filter);
                },
                scope: this
            },
            width: 120
        };
        this.attributesComboConfig = this.attributesComboConfig || {};
        Ext.applyIf(this.attributesComboConfig, defAttributesComboConfig);

        this.items = this.createFilterItems();

        this.addEvents(
            /**
             * Event: change
             * Fires when the filter changes.
             *
             * Listener arguments:
             * filter - {OpenLayers.Filter} This filter.
             */
            "change"
        );

        gxp.form.FilterField.superclass.initComponent.call(this);
    },

    /**
     * Method: createDefaultFilter
     * May be overridden to change the default filter.
     *
     * Returns:
     * {OpenLayers.Filter} By default, returns a comarison filter.
     */
    createDefaultFilter: function () {
        return new OpenLayers.Filter.Comparison();
    },

    /**
     * Method: createFilterItems
     * Creates a panel config containing filter parts.
     */
    createFilterItems: function () {

        return [
            this.attributesComboConfig, {
                xtype: "gx_comparisoncombo",
                value: this.filter.type,
                listeners: {
                    select: function (combo, record) {
                        this.filter.type = record.get("value");
                        this.fireEvent("change", this.filter);
                    },
                    scope: this
                }
            }, {
                xtype: "textfield",
                value: this.filter.value,
                width: 50,
                grow: true,
                growMin: 50,
                anchor: "100%",
                allowBlank: false,
                listeners: {
                    change: function (el, value) {
                        this.filter.value = value;
                        this.fireEvent("change", this.filter);
                    },
                    scope: this
                }
            }
        ];
    }

});

Ext.reg('gx_filterfield', gxp.form.FilterField);
