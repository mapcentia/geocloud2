/**
 * Copyright (c) 2009 The Open Planning Project
 */

/**
 * @include widgets/form/FilterField.js
 */

/** api: (define)
 *  module = gxp
 *  class = FilterBuilder
 *  base_link = `Ext.Panel <http://extjs.com/deploy/dev/docs/?class=Ext.Panel>`_
 */
Ext.namespace("gxp");

/** api: constructor
 *  .. class:: FilterBuilder(config)
 *
 *      Create a panel for assembling a filter.
 */
gxp.FilterBuilder = Ext.extend(Ext.Container, {

    /** api: config[builderTypeNames]
     *  ``Array``
     *  A list of labels for that correspond to builder type constants.
     *  These will be the option names available in the builder type combo.
     *  Default is ``["any", "all", "none", "not all"]``.
     */
    builderTypeNames: ["any", "all", "none", "not all"],
    attributeQuery: false,
    spatialQuery: true,
    timeSliceQuery: false,

    /** api: config[allowedBuilderTypes]
     *  ``Array``
     *  List of builder type constants.  Default is
     *  ``[ANY_OF, ALL_OF, NONE_OF]``.
     */
    allowedBuilderTypes: null,

    /** api: config[preComboText]
     *  ``String``
     *  String to display before filter type combo.  Default is ``"Match"``.
     */
    preComboText: "Match",

    /** api: config[postComboText]
     *  ``String``
     *  String to display before filter type combo.  Default is
     *  ``"of the following:"``.
     */
    postComboText: "of the following:",

    /** api: config[cls]
     *  ``String``
     *  The CSS class to be added to this panel's element (defaults to
     *  ``"gx-filterbuilder"``).
     */
    cls: "gx-filterbuilder",

    /** private: property[builderType]
     */
    builderType: null,

    /** private: property[childFilterContainer]
     */
    childFilterContainer: null,

    /** private: property[customizeFilterOnInit]
     */
    customizeFilterOnInit: true,


    /** api: config[allowGroups]
     *  ``Boolean``
     *  Allow groups of conditions to be added.  Default is ``true``.
     *  If ``false``, only individual conditions (non-logical filters) can
     *  be added.
     */
    allowGroups: true,

    initComponent: function () {
        var defConfig = {
            defaultBuilderType: gxp.FilterBuilder.ANY_OF
        };
        Ext.applyIf(this, defConfig);

        if (this.customizeFilterOnInit) {
            this.filter = this.customizeFilter(this.filter);
        }

        this.builderType = this.getBuilderType();

        this.items = [
            {
                xtype: "fieldset",
                title: __("Filter by location"),
                checkboxToggle: true,
                //collapsed: !this.spatialQuery,
                anchor: "95%",
                html: "Only features within the map view will be loaded.",
                //items: [this.mapExtentField],
                listeners: {
                    collapse: function () {
                        this.spatialQuery = false;
                    },
                    expand: function () {
                        this.spatialQuery = true;
                    },
                    scope: this
                }
            },
            {
                xtype: "fieldset",
                title: __("Filter by attributes"),
                checkboxToggle: true,
                collapsed: !this.attributeQuery,
                anchor: "95%",
                items: [
                    {
                        xtype: "container",
                        layout: "form",
                        defaults: {anchor: "100%"},
                        hideLabels: true,
                        items: [
                            {
                                xtype: "compositefield",
                                style: "padding-left: 2px",
                                items: [
                                    {
                                        xtype: "label",
                                        style: "padding-top: 0.3em",
                                        text: this.preComboText
                                    },
                                    this.createBuilderTypeCombo(),
                                    {
                                        xtype: "label",
                                        style: "padding-top: 0.3em",
                                        text: this.postComboText
                                    }
                                ]
                            },
                            this.createChildFiltersPanel(),
                            {
                                xtype: "toolbar",
                                items: this.createToolBar()
                            }
                        ]

                    }
                ],
                listeners: {
                    collapse: function () {
                        this.attributeQuery = false;
                    },
                    expand: function () {
                        this.attributeQuery = true;
                    },
                    scope: this
                }
            },
            {
                xtype: "fieldset",
                title: __("Time slicing (only versioned layers)"),
                id: "timeSliceField",
                checkboxToggle: true,
                collapsed: !this.timeSliceQuery,
                anchor: "95%",
                html : __("Either select 'latest, 'all' or write a date/time in the form yyyy-mm-dd hh:mm:ss<br><br>If you are tracking changes on your table, you can either load the latest (current) versions or you can 'slice' at a given point in time. You can also load all versions.<br><br>Earlier features will be shown with a red dashed line."),
                items: [
                    {
                        xtype: "container",
                        layout: "form",
                        hideLabels: true,
                        items: [
                            {
                                xtype: "combo",
                                hideLabels: true,
                                width: 300,
                                store: new Ext.data.ArrayStore({
                                    fields: ['type', 'display'],
                                    data: [
                                        ['latest', 'latest'],
                                        ['all', 'all']
                                    ]
                                }),
                                displayField: 'display',
                                valueField: 'type',
                                editable: true,
                                triggerAction: "all",
                                name: "timeslice",
                                value: "latest",
                                allowBlank: true,
                                mode: 'local'
                            }
                        ]
                    }
                ],
                listeners: {
                    collapse: function () {
                        this.timeSliceQuery = false;
                    },
                    expand: function () {
                        this.timeSliceQuery = true;
                    },
                    scope: this
                }
            }
        ];

        this.addEvents(
            /**
             * Event: change
             * Fires when the filter changes.
             *
             * Listener arguments:
             * builder - {gxp.FilterBuilder} This filter builder.  Call
             *     ``getFilter`` to get the updated filter.
             */
            "change"
        );
        gxp.FilterBuilder.superclass.initComponent.call(this);
    },

    /** private: method[createToolBar]
     */
    createToolBar: function () {
        var bar = [
            {
                text: "<i class='fa fa-plus-circle'></i> Add condition",
                //iconCls: "silk-add",
                handler: function () {
                    this.addCondition();
                },
                scope: this
            }
        ];
        if (this.allowGroups) {
            bar.push({
                text: "add group",
                iconCls: "silk-add",
                handler: function () {
                    this.addCondition(true);
                },
                scope: this
            });
        }
        return bar;
    },

    /** api: method[getFilter]
     *  :return: ``OpenLayers.Filter``
     *
     *  Returns a filter that fits the model in the Filter Encoding
     *  specification.  Use this method instead of directly accessing
     *  the ``filter`` property.  Return will be ``false`` if any child
     *  filter does not have a type, property, or value.
     */
    /*
     getFilter: function() {
     var attributeFilter = this.attributeQuery && this.filterBuilder.getFilter();
     var filter;
     console.log(this.filter);
     if(this.filter) {
     filter = this.filter.clone();
     if(filter instanceof OpenLayers.Filter.Logical) {
     filter = this.cleanFilter(filter);
     }
     }
     return filter;
     },*/
    getFilter: function () {
        var attributeFilter = this.attributeQuery && this.filter;
        var spatialFilter = this.spatialQuery && new OpenLayers.Filter.Spatial({
            type: OpenLayers.Filter.Spatial.BBOX,
            value: map.getExtent()
        });
        var filter;
        if (attributeFilter && spatialFilter) {

            filter = this.filter.clone();
            if (filter instanceof OpenLayers.Filter.Logical) {
                attributeFilter = this.cleanFilter(attributeFilter);
                filter = new OpenLayers.Filter.Logical({
                    type: OpenLayers.Filter.Logical.AND,
                    filters: [spatialFilter, attributeFilter]
                });
            }
        } else {
            attributeFilter = this.cleanFilter(attributeFilter);
            filter = attributeFilter || spatialFilter;


        }
        return filter;
    },

    /** private: method[cleanFilter]
     *  :arg filter: ``OpenLayers.Filter.Logical``
     *  :return: ``OpenLayers.Filter`` An equivalent filter to the input, where
     *      all binary logical filters have more than one child filter.  Returns
     *      false if a filter doesn't have non-null type, property, or value.
     *
     *  Ensures that binary logical filters have more than one child.
     */
    cleanFilter: function (filter) {
        if (filter instanceof OpenLayers.Filter.Logical) {
            if (filter.type !== OpenLayers.Filter.Logical.NOT &&
                filter.filters.length === 1) {
                filter = this.cleanFilter(filter.filters[0]);
            } else {
                var child;
                for (var i = 0, len = filter.filters.length; i < len; ++i) {
                    child = filter.filters[i];
                    if (child instanceof OpenLayers.Filter.Logical) {
                        child = this.cleanFilter(child);
                        if (child) {
                            filter.filters[i] = child;
                        } else {
                            filter = child;
                            break;
                        }
                    } else if (!child || child.type === null ||
                        child.property === null || child.value === null) {
                        filter = false;
                        break;
                    }
                }
            }
        } else {
            if (!filter || filter.type === null || filter.property === null ||
                filter.value === null) {
                filter = false;
            }
        }
        return filter;
    },

    /** private: method[customizeFilter]
     *  :arg filter: ``OpenLayers.Filter``  This filter will not
     *      be modified.  Register for events to receive an updated filter, or
     *      call ``getFilter``.
     *  :return: ``OpenLayers.Filter``  A filter that fits the model used by
     *      this builder.
     *
     *  Create a filter that fits the model for this filter builder.  This filter
     *  will not necessarily meet the Filter Encoding specification.  In
     *  particular, filters representing binary logical operators may not
     *  have two child filters.  Use the <getFilter> method to return a
     *  filter that meets the encoding spec.
     */
    customizeFilter: function (filter) {
        if (!filter) {
            filter = this.wrapFilter(this.createDefaultFilter());
        } else {
            filter = this.cleanFilter(filter);
            switch (filter.type) {
                case OpenLayers.Filter.Logical.AND:
                case OpenLayers.Filter.Logical.OR:
                    if (!filter.filters || filter.filters.length === 0) {
                        // give the filter children if it has none
                        filter.filters = [this.createDefaultFilter()];
                    } else {
                        var child;
                        for (var i = 0, len = filter.filters.length; i < len; ++i) {
                            child = filter.filters[i];
                            if (child instanceof OpenLayers.Filter.Logical) {
                                filter.filters[i] = this.customizeFilter(child);
                            }
                        }
                    }
                    // wrap in a logical OR
                    filter = new OpenLayers.Filter.Logical({
                        type: OpenLayers.Filter.Logical.OR,
                        filters: [filter]
                    });
                    break;
                case OpenLayers.Filter.Logical.NOT:
                    if (!filter.filters || filter.filters.length === 0) {
                        filter.filters = [
                            new OpenLayers.Filter.Logical({
                                type: OpenLayers.Filter.Logical.OR,
                                filters: [this.createDefaultFilter()]
                            })
                        ];
                    } else {
                        // NOT filters should have one child only
                        var child = filter.filters[0];
                        if (child instanceof OpenLayers.Filter.Logical) {
                            if (child.type !== OpenLayers.Filter.Logical.NOT) {
                                // check children of AND and OR
                                var grandchild;
                                for (var i = 0, len = child.filters.length; i < len; ++i) {
                                    grandchild = child.filters[i];
                                    if (grandchild instanceof OpenLayers.Filter.Logical) {
                                        child.filters[i] = this.customizeFilter(grandchild);
                                    }
                                }
                            } else {
                                // silly double negative
                                if (child.filters && child.filters.length > 0) {
                                    filter = this.customizeFilter(child.filters[0]);
                                } else {
                                    filter = this.wrapFilter(this.createDefaultFilter());
                                }
                            }
                        } else {
                            // non-logical child of NOT should be wrapped
                            var type;
                            if (this.defaultBuilderType === gxp.FilterBuilder.NOT_ALL_OF) {
                                type = OpenLayers.Filter.Logical.AND;
                            } else {
                                type = OpenLayers.Filter.Logical.OR;
                            }
                            filter.filters = [
                                new OpenLayers.Filter.Logical({
                                    type: type,
                                    filters: [child]
                                })
                            ];
                        }
                    }
                    break;
                default:
                    // non-logical filters get wrapped
                    filter = this.wrapFilter(filter);
            }
        }
        return filter;
    },

    createDefaultFilter: function () {
        return new OpenLayers.Filter.Comparison();
    },

    /** private: method[wrapFilter]
     *  :arg filter: ``OpenLayers.Filter`` A non-logical filter.
     *  :return: ``OpenLayers.Filter`` A wrapped version of the input filter.
     *
     *  Given a non-logical filter, this creates parent filters depending on
     *  the ``defaultBuilderType``.
     */
    wrapFilter: function (filter) {
        var type;
        if (this.defaultBuilderType === gxp.FilterBuilder.ALL_OF) {
            type = OpenLayers.Filter.Logical.AND;
        } else {
            type = OpenLayers.Filter.Logical.OR;
        }
        return new OpenLayers.Filter.Logical({
            type: OpenLayers.Filter.Logical.OR,
            filters: [
                new OpenLayers.Filter.Logical({
                    type: type, filters: [filter]
                })
            ]
        });
    },

    /** private: method[addCondition]
     *  Add a new condition or group of conditions to the builder.  This
     *  modifies the filter and adds a panel representing the new condition
     *  or group of conditions.
     */
    addCondition: function (group) {
        var filter, type;
        if (group) {
            type = "gx_filterbuilder";
            filter = this.wrapFilter(this.createDefaultFilter());
        } else {
            type = "gx_filterfield";
            filter = this.createDefaultFilter();
        }
        var newChild = this.newRow({
            xtype: type,
            filter: filter,
            columnWidth: 1,
            attributes: this.attributes,
            customizeFilterOnInit: group && false,
            listeners: {
                change: function () {
                    this.fireEvent("change", this);
                },
                scope: this
            }
        });
        this.childFilterContainer.add(newChild);
        this.filter.filters[0].filters.push(filter);
        this.childFilterContainer.doLayout();
    },

    /** private: method[removeCondition]
     *  Remove a condition or group of conditions from the builder.  This
     *  modifies the filter and removes the panel representing the condition
     *  or group of conditions.
     */
    removeCondition: function (item, filter) {
        var parent = this.filter.filters[0].filters;
        if (parent.length > 1) {
            parent.remove(filter);
            this.childFilterContainer.remove(item, true);
        }
        this.fireEvent("change", this);
    },

    createBuilderTypeCombo: function () {
        var types = this.allowedBuilderTypes || [
            gxp.FilterBuilder.ANY_OF, gxp.FilterBuilder.ALL_OF];
        var numTypes = types.length;
        var data = new Array(numTypes);
        var type;
        for (var i = 0; i < numTypes; ++i) {
            type = types[i];
            data[i] = [type, this.builderTypeNames[type]];
        }
        return {
            xtype: "combo",
            store: new Ext.data.SimpleStore({
                data: data,
                fields: ["value", "name"]
            }),
            value: this.builderType,
            displayField: "name",
            valueField: "value",
            triggerAction: "all",
            mode: "local",
            listeners: {
                select: function (combo, record) {
                    this.changeBuilderType(record.get("value"));
                    this.fireEvent("change", this);
                },
                scope: this
            },
            width: 60 // TODO: move to css
        };
    },

    /** private: method[changeBuilderType]
     *  :arg type: ``Integer`` One of the filter type constants.
     *
     *  Alter the filter types when the filter type combo changes.
     */
    changeBuilderType: function (type) {
        if (type !== this.builderType) {
            this.builderType = type;
            var child = this.filter.filters[0];
            switch (type) {
                case gxp.FilterBuilder.ANY_OF:
                    this.filter.type = OpenLayers.Filter.Logical.OR;
                    child.type = OpenLayers.Filter.Logical.OR;
                    break;
                case gxp.FilterBuilder.ALL_OF:
                    this.filter.type = OpenLayers.Filter.Logical.OR;
                    child.type = OpenLayers.Filter.Logical.AND;
                    break;
                case gxp.FilterBuilder.NONE_OF:
                    this.filter.type = OpenLayers.Filter.Logical.NOT;
                    child.type = OpenLayers.Filter.Logical.OR;
                    break;
                case gxp.FilterBuilder.NOT_ALL_OF:
                    this.filter.type = OpenLayers.Filter.Logical.NOT;
                    child.type = OpenLayers.Filter.Logical.AND;
                    break;
            }
        }
    },

    /** private: method[createChildFiltersPanel]
     *  :return: ``Ext.Container``
     *
     *  Create the panel that holds all conditions and condition groups.  Since
     *  this is called after this filter has been customized, we always
     *  have a logical filter with one child filter - that child is also
     *  a logical filter.
     */
    createChildFiltersPanel: function () {
        this.childFilterContainer = new Ext.Container();
        var grandchildren = this.filter.filters[0].filters;
        var grandchild;
        for (var i = 0, len = grandchildren.length; i < len; ++i) {
            grandchild = grandchildren[i];
            var fieldCfg = {
                xtype: "gx_filterfield",
                columnWidth: 1,
                filter: grandchild,
                attributes: this.attributes,
                listeners: {
                    change: function () {
                        this.fireEvent("change", this);
                    },
                    scope: this
                }
            };
            var containerCfg = Ext.applyIf(
                grandchild instanceof OpenLayers.Filter.Logical ?
                {
                    xtype: "gx_filterbuilder"
                } : {
                    xtype: "container",
                    layout: "form",
                    hideLabels: true,
                    items: fieldCfg
                }, fieldCfg);

            this.childFilterContainer.add(this.newRow(containerCfg));
        }
        return this.childFilterContainer;
    },

    /** private: method[newRow]
     *  :return: ``Ext.Container`` A container that serves as a row in a child
     *  filters panel.
     *
     *  Generate a "row" for the child filters panel.  This couples another
     *  filter panel or filter builder with a component that allows for
     *  condition removal.
     */
    newRow: function (filterContainer) {
        var ct = new Ext.Container({
            layout: "column",
            items: [
                {
                    xtype: "container",
                    width: 28,
                    style: "padding-left: 2px",
                    items: {
                        xtype: "button",
                        text: "<i class='fa fa-minus-circle'></i>",
                        tooltip: "remove condition",
                        //iconCls: "silk-delete",
                        handler: function (btn) {
                            this.removeCondition(ct, filterContainer.filter);
                        },
                        scope: this
                    }
                },
                filterContainer
            ]
        });
        return ct;
    },

    /** private: method[getBuilderType]
     *  :return: ``Integer``  One of the builder type constants.
     *  Determine the builder type based on this filter.
     */
    getBuilderType: function () {
        var type = this.defaultBuilderType;
        if (this.filter) {
            var child = this.filter.filters[0];
            if (this.filter.type === OpenLayers.Filter.Logical.NOT) {
                switch (child.type) {
                    case OpenLayers.Filter.Logical.OR:
                        type = gxp.FilterBuilder.NONE_OF;
                        break;
                    case OpenLayers.Filter.Logical.AND:
                        type = gxp.FilterBuilder.NOT_ALL_OF;
                        break;
                }
            } else {
                switch (child.type) {
                    case OpenLayers.Filter.Logical.OR:
                        type = gxp.FilterBuilder.ANY_OF;
                        break;
                    case OpenLayers.Filter.Logical.AND:
                        type = gxp.FilterBuilder.ALL_OF;
                        break;
                }
            }
        }
        return type;
    }

});

/**
 * Builder Types
 */
gxp.FilterBuilder.ANY_OF = 0;
gxp.FilterBuilder.ALL_OF = 1;
gxp.FilterBuilder.NONE_OF = 2;
gxp.FilterBuilder.NOT_ALL_OF = 3;

/** api: xtype = gx_filterbuilder */
Ext.reg('gx_filterbuilder', gxp.FilterBuilder); 

