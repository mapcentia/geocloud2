/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
Ext.namespace("Heron.widgets.search");

/** api: (define)
 *  module = Heron.widgets.search
 *  class = FormSearchPanel
 *  base_link = `GeoExt.form.FormPanel <http://www.geoext.org/lib/GeoExt/widgets/form/FormPanel.html>`_
 */

/** api: example
 *  Sample code showing how to configure a Heron FormSearchPanel.
 *  This example uses the internal default progress messages and action (zoom).
 *  Note that the fields in the items must follow the convention outlined in
 *  `GeoExt.form.SearchAction <http://geoext.org/lib/GeoExt/widgets/form/SearchAction.html>`_.
 *
 *  .. code-block:: javascript

    {
     xtype: 'hr_formsearchpanel',
     name: 'Attribute (Form) Search: USA States',
     header: false,
     protocol: new OpenLayers.Protocol.WFS({
         version: "1.1.0",
         url: "http://suite.opengeo.org/geoserver/ows?",
         srsName: "EPSG:4326",
         featureType: "states",
         featureNS: "http://usa.opengeo.org"
     }),
     downloadFormats: [
         {
             name: 'CSV',
             outputFormat: 'csv',
             fileExt: '.csv'
         },
         {
             name: 'GML (version 2.1.2)',
             outputFormat: 'text/xml; subtype=gml/2.1.2',
             fileExt: '.gml'
         },
         {
             name: 'ESRI Shapefile (zipped)',
             outputFormat: 'SHAPE-ZIP',
             fileExt: '.zip'
         },
         {
             name: 'GeoJSON',
             outputFormat: 'json',
             fileExt: '.json'
         }
     ],
     items: [
         {
             xtype: "textfield",
             name: "STATE_NAME__like",
             value: 'ah',
             fieldLabel: "  name"
         },
         {
             xtype: "label",
             id: "helplabel",
             html: 'Type name of a USA state, wildcards are appended and match is case-insensitive.<br/>Almost any single letter will yield results.<br/>',
             style: {
                 fontSize: '10px',
                 color: '#AAAAAA'
             }
         }
     ],
     hropts: {
         onSearchCompleteZoom: 10,
         autoWildCardAttach: true,
         caseInsensitiveMatch: true,
         logicalOperator: OpenLayers.Filter.Logical.AND
     }
    }
 */

/** api: constructor
 *  .. class:: FormSearchPanel(config)
 *
 *  A panel designed to hold a (geo-)search form.
 *
 *      For the ``items[] array``: when run this Form (via GeoExt
 *      `GeoExt.form.SearchAction <http://geoext.org/lib/GeoExt/widgets/form/SearchAction.html>`_)
 *      builds an ``OpenLayers.Filter`` from the form
 *      and passes this filter to its protocol's read method. The form fields
 *      must be named after a specific convention, so that an appropriate
 *      ``OpenLayers.Filter.Comparison`` filter is created for each
 *      field.
 *
 *      For example a field with the name ``foo__like`` would result in an
 *      ``OpenLayers.Filter.Comparison`` of type
 *      ``OpenLayers.Filter.Comparison.LIKE`` being created.
 *
 *      Here is the convention:
 *
 *      * ``<name>__eq: OpenLayers.Filter.Comparison.EQUAL_TO``
 *      * ``<name>__ne: OpenLayers.Filter.Comparison.NOT_EQUAL_TO``
 *      * ``<name>__lt: OpenLayers.Filter.Comparison.LESS_THAN``
 *      * ``<name>__le: OpenLayers.Filter.Comparison.LESS_THAN_OR_EQUAL_TO``
 *      * ``<name>__gt: OpenLayers.Filter.Comparison.GREATER_THAN``
 *      * ``<name>__ge: OpenLayers.Filter.Comparison.GREATER_THAN_OR_EQUAL_TO``
 *      * ``<name>__like: OpenLayers.Filter.Comparison.LIKE``
 */
Heron.widgets.search.FormSearchPanel = Ext.extend(GeoExt.form.FormPanel, {

    /** api: config[onSearchCompleteZoom]
     *  Zoomlevel to zoom into when feature(s) found and panned to feature.
     *  default value is 11.
     */
    onSearchCompleteZoom: 11,

    /** api: config[autoWildCardAttach]
     *  Should search strings (LIKE comparison only) always be pre/postpended with a wildcard '*' character.
     *  default value is false.
     */
    autoWildCardAttach: false,

    /** api: config[caseInsensitiveMatch]
     *  Should search strings (LIKE and EQUALS comparison only) be matched case insensitive?
     *  NB case insensitive matching is only supported for WFS 1.1.0 and higher (not for WFS 1.0.0!).
     *  default value is false.
     */
    caseInsensitiveMatch: false,

    /** api: config[logicalOperator]
     *  The logical operator to use when combining multiple fields into a filter expresssion.
     *  Values can be OpenLayers.Filter.Logical.OR ('||') or OpenLayers.Filter.Logical.AND ('&&')
     *  default value is OpenLayers.Filter.Logical.AND.
     */
    logicalOperator: OpenLayers.Filter.Logical.AND,

    /** api: config[layerOpts]
     *  Options for layer activation when search successful.
     */
    layerOpts: undefined,

    /** api: property[statusPanelOpts]
     *  Layout for the status Panel.
     */
    statusPanelOpts: {
        html: '&nbsp;',
        height: 132,
        preventBodyReset: true,
        bodyCfg: {
            style: {
                padding: '6px',
                border: '0px'
            }
        },
        style: {
            marginTop: '24px',
            paddingTop: '24px',
            fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
            fontSize: '11px',
            color: '#0000C0'
        }
    },

    progressMessages: [
        __('Working on it...'),
        __('Still searching, please be patient...')
    ],

    header: true,
    bodyStyle: 'padding: 6px',
    style: {
        fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
        fontSize: '12px'
    },

    downloadFormats: [],

    defaults: {
        enableKeyEvents: true,
        listeners: {
            specialKey: function (field, el) {
                if (el.getKey() == Ext.EventObject.ENTER) {
                    var form = this.ownerCt;
                    if (!form && !form.search) {
                        return;
                    }

                    form.action = null;
                    form.search();
                }
            }
        }
    },

// See also: http://ian01.geog.psu.edu/geoserver_docs/apps/gaz/search.html
    initComponent: function () {

        // Setup our own events
        this.addEvents({
            "searchcomplete": true,
            "searchcanceled": true,
            "searchfailed": true,
            "searchsuccess": true
        });

        // hropts should become deprecated...
        Ext.apply(this, this.hropts);


        Heron.widgets.search.FormSearchPanel.superclass.initComponent.call(this);

        // In rare cases may we have a WMS with multiple URLs n Array (for loadbalancing)
        // Take a random URL. Note: this should really be implemented in OpenLayers Protocol read()
        if (this.protocol && this.protocol.url instanceof Array) {
            this.protocol.url = Heron.Utils.randArrayElm(this.protocol.url);
            this.protocol.options.url = this.protocol.url;
        }

        // Extra widgets besides configured form fields
        var items = [this.createStatusPanel(), this.createActionButtons()];
        this.add(items);
        this.addListener("beforeaction", this.onSearchIssued, this);
        this.addListener("searchcanceled", this.onSearchCanceled, this);
        this.addListener("actioncomplete", this.onSearchComplete, this);
        this.addListener("actionfailed", this.onSearchFailed, this);
    },

    createActionButtons: function () {

        this.searchButton = new Ext.Button({
            text: __('Search'),
            tooltip: __('Search'),
            disabled: false,
            handler: function () {
                this.search();
            },
            scope: this
        });

        this.cancelButton = new Ext.Button({
            text: 'Cancel',
            tooltip: __('Cancel ongoing search'),
            disabled: true,
            handler: function () {
                this.fireEvent('searchcanceled', this);
            },
            scope: this
        });

        return this.actionButtons = new Ext.ButtonGroup({
            fieldLabel: null,
            labelSeparator: '',
            anchor: "-50",
            title: null,
            border: false,
            bodyBorder: false,
            items: [
                this.cancelButton,
                this.searchButton
            ]});
    },


    createStatusPanel: function () {
        return this.statusPanel = new Heron.widgets.HTMLPanel(this.statusPanelOpts);
    },

    updateStatusPanel: function (text) {
        if (!text) {
            text = '&nbsp;';
        }
        if (this.statusPanel.body) {
            this.statusPanel.body.update(text);
        } else {
            this.statusPanel.html = text;
        }
    },

    getFeatureType: function () {
        return this.protocol ? this.protocol.featureType : 'heron';
    },

    /** api: config[onSearchInProgress]
     *  Function to call when search is starting.
     *  Default is to show "Searching..." on progress label.
     */
    onSearchIssued: function (form, action) {
        this.protocol = action.form.protocol;
        this.searchState = "searchissued";
        this.features = null;
        this.updateStatusPanel(__('Searching...'));
        this.cancelButton.enable();
        this.searchButton.disable();

        // If search takes to long, give some feedback
        var self = this;
        var startTime = new Date().getTime() / 1000;
        this.timer = setInterval(function () {
            if (self.searchState != 'searchissued') {
                return;
            }

            // User feedback with seconds passed and random message
            self.updateStatusPanel(Math.floor(new Date().getTime() / 1000 - startTime) +
                    ' ' + __('Seconds') + ' - ' +
                    Heron.Utils.randArrayElm(self.progressMessages));
        }, 4000);
    },

    /** api: config[onSearchFailed]
     *  Function to call when search has failed.
     */
    onSearchFailed: function (form, action) {
        this.searchAbort();
    },


    /** api: method[onSearchCanceled]
     *  Function called when search is canceled.
     */
    onSearchCanceled: function (searchPanel) {
        this.searchState = 'searchcanceled';
        this.searchAbort();
        this.updateStatusPanel(__('Search Canceled'));
    },

    /** api: config[onSearchComplete]
     *  Function to call when search is complete.
     *  Default is to show "Search completed" with feature count on progress label.
     */
    onSearchComplete: function (form, action) {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.cancelButton.disable();
        this.searchButton.enable();

        if (this.searchState == 'searchcanceled') {
            this.searchState = null;
            return;
        }

        this.searchState = "searchcomplete";

        var result = {
            olResponse: action.response
        };
        this.fireEvent('searchcomplete', this, result);

        if (action && action.response && action.response.success()) {
            var features = this.features = action.response.features;
            var featureCount = features ? features.length : 0;
            this.updateStatusPanel(__('Search Completed: ') + featureCount + ' ' + (featureCount != 1 ? __('Results') : __('Result')));

            var filter = GeoExt.form.toFilter(action.form, action.options);
            var filterFormat = new OpenLayers.Format.Filter.v1_1_0({srsName: this.protocol.srsName});
            var filterStr = OpenLayers.Format.XML.prototype.write.apply(
                    filterFormat, [filterFormat.write(filter)]
            );

            result.downloadInfo = {
                type: 'wfs',
                url: this.protocol.options.url,
                downloadFormats: this.downloadFormats,
                params: {
                    typename: this.protocol.featureType,
                    maxFeatures: this.protocol.maxFeatures,
                    "Content-Disposition": "attachment",
                    filename: this.protocol.featureType,
                    srsName: this.protocol.srsName,
                    service: "WFS",
                    version: "1.1.0",
                    request: "GetFeature",
                    filter: filterStr
                }
            };

            if (this.onSearchCompleteAction) {

                // GvS optional activation of layers
                // layerOpts: [
                //	 { layerOn: 'lki_staatseigendommen', layerOpacity: 0.4 },
                //	 { layerOn: 'bag_adres_staat_g', layerOpacity: 1.0 }
                // ]
                // If specified make those layers visible with optional layer opacity
                var lropts = this.layerOpts;
                if (lropts) {
                    var map = Heron.App.getMap();
                    for (var l = 0; l < lropts.length; l++) {
                        if (lropts[l]['layerOn']) {
                            // Get all layers from the map with the specified name
                            var mapLayers = map.getLayersByName(lropts[l]['layerOn']);
                            for (var n = 0; n < mapLayers.length; n++) {

                                // Make layer visible
                                if (mapLayers[n].isBaseLayer) {
                                    map.setBaseLayer(mapLayers[n]);
                                } else {
                                    mapLayers[n].setVisibility(true);
                                }

                                // And set optional opacity
                                if (lropts[l]['layerOpacity']) {
                                    mapLayers[n].setOpacity(lropts[l]['layerOpacity']);
                                }
                            }
                        }
                    }
                }

                this.onSearchCompleteAction(result);
            }
            this.fireEvent('searchsuccess', this, result);
        } else {
            this.fireEvent('searchfailed', this, action.response);
            this.updateStatusPanel(__('Search Failed') + ' details: ' + action.response.priv.responseText);
        }
    },

    /** api: config[onSearchCompleteAction]
     *  Function to call to perform action when search is complete.
     *  Either zoom to single Point feature or zoom to extent (bbox) of multiple features
     */
    onSearchCompleteAction: function (result) {
        var features = result.olResponse.features;

        // Safeguard
        if (!features || features.length == 0) {
            return;
        }

        var map = Heron.App.getMap();
        if (features.length == 1 && features[0].geometry.CLASS_NAME == "OpenLayers.Geometry.Point" && this.onSearchCompleteZoom) {
            // Case: one Point feature found and onSearchCompleteZoom defined: zoom to Point
            var point = features[0].geometry.getCentroid();
            map.setCenter(new OpenLayers.LonLat(point.x, point.y), this.onSearchCompleteZoom);
        } else {
            // All other cases: zoom to the extent (bounding box) of the features found. See issue 69.
            var geometryCollection = new OpenLayers.Geometry.Collection();
            for (var i = 0; i < features.length; i++) {
                geometryCollection.addComponent(features[i].geometry);
            }
            geometryCollection.calculateBounds();
            map.zoomToExtent(geometryCollection.getBounds());
        }
    },

    /** api: method[search]
     *  :param options: ``Object`` The options passed to the
     *      :class:`GeoExt.form.SearchAction` constructor.
     *
     *  Interceptor to the internal form's search method.
     */
    search: function () {
        this.action = null;
        Heron.widgets.search.FormSearchPanel.superclass.search.call(this, {
            wildcard: this.autoWildCardAttach ? GeoExt.form.CONTAINS : -1,
            matchCase: !this.caseInsensitiveMatch,
            logicalOp: this.logicalOperator
        });
        // this.fireEvent('searchissued', this);
    },

    /** api: method[searchAbort]
     *
     *  Abort/cancel search via protocol.
     */
    searchAbort: function () {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }

        if (this.protocol) {
            this.protocol.abort(this.response);
        }
        this.protocol = null;
        this.searchButton.enable();
        this.cancelButton.disable();
        this.updateStatusPanel(__('Search aborted'));
    }

});

/** api: xtype = hr_formsearchpanel */
Ext.reg('hr_formsearchpanel', Heron.widgets.search.FormSearchPanel);

// For compatibility with pre v0.73. Heron.widgets.SearchPanel was renamed to Heron.widgets.search.FormSearchPanel
/** api: xtype = hr_searchpanel */
Ext.reg('hr_searchpanel', Heron.widgets.search.FormSearchPanel);


