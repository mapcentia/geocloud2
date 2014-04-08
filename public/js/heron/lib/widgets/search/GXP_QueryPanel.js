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

/** api: (define)
 *  module = Heron.widgets.search
 *  class = GXP_QueryPanel
 *  base_link = `gxp.QueryPanel <http://gxp.opengeo.org/master/doc/lib/widgets/QueryPanel.html>`_
 */

Ext.namespace("Heron.widgets.search");

/** api: example
 *
 *  Sample code showing how to configure a Heron GXP_QueryPanel. Here within a SearchCenterPanel, through a search button
 *  (binoculars) within the MapPanel Toolbar. This Panel is mostly used in combination with the  `Heron.widgets.search.FeaturePanel <FeaturePanel.html>`_
 *  in which results from a search are displayed in a grid and on the Map. Both Panels are usually bundled
 *  in a `Heron.widgets.search.SearchCenterPanel <SearchCenterPanel.html>`_ that manages the search and result Panels.
 *  See config example below.
 *
 *  .. code-block:: javascript
 *
         {type: "zoomprevious"},
         {type: "zoomnext"},
         {type: "-"},
         {
             type: "searchcenter",
             // Options for SearchPanel window
             options: {
                 show: true,

                 searchWindow: {
                     title: __('Query Builder'),
                     x: 100,
                     y: undefined,
                     layout: 'fit',
                     width: 380,
                     height: 420,
                     items: [
                         {
                             xtype: 'hr_searchcenterpanel',
                             id: 'hr-searchcenterpanel',
                             hropts: {
                                 searchPanel: {
                                     xtype: 'hr_gxpquerypanel',
                                     header: false,
                                     border: false,
                                     spatialQuery: true,
                                     attributeQuery: true,
                                     caseInsensitiveMatch: true,
                                     autoWildCardAttach: true
                                 },
                                 resultPanel: {
                                     xtype: 'hr_featuregridpanel',
                                     id: 'hr-featuregridpanel',
                                     header: false,
                                     border: false,
                                     autoConfig: true,
                                     exportFormats: ['XLS', 'WellKnownText'],
                                     hropts: {
                                         zoomOnRowDoubleClick: true,
                                         zoomOnFeatureSelect: false,
                                         zoomLevelPointSelect: 8,
                                         zoomToDataExtent: true
                                     }
                                 }
                             }
                         }
                     ]
                 }
             }
         }
         ];


 * Important is to also enable your WMS Layers for WFS through the metadata object.
 * See the examples DefaultOptionsWorld.js, for example the USA States Layer (only 'fromWMSLayer' value is currently supported):
 *
 *  .. code-block:: javascript

         new OpenLayers.Layer.WMS(
         "USA States (OpenGeo)",
         'http://suite.opengeo.org/geoserver/ows?',
         {layers: "states", transparent: true, format: 'image/png'},
         {singleTile: true, opacity: 0.9, isBaseLayer: false, visibility: false, noLegend: false, featureInfoFormat: 'application/vnd.ogc.gml', transitionEffect: 'resize', metadata: {
             wfs: {
                 protocol: 'fromWMSLayer',
                 featurePrefix: 'usa',
                 featureNS: 'http://usa.opengeo.org',
                 downloadFormats: Heron.options.wfs.downloadFormats
             }
         }
         }
         ),
 *
 *  The ``downloadFormats`` specifies the outputFormats that the WFS supports for triggered download (via HTTP Content-disposition: attachment)
 *  that the WFS supports. Currently only GeoServer is known to support "triggered download".
 *
 *  .. code-block:: javascript

         Heron.options.wfs.downloadFormats = [
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
         ];

 *
 *
 *
 */

Heron.widgets.GXP_QueryPanel_Empty = Ext.extend(Ext.Panel, {} );

/** api: constructor
 *  .. class:: GXP_QueryPanel(config)
 *
 *  Wrap and configure an OpenGeo `GXP QueryPanel <http://gxp.opengeo.org/master/doc/lib/widgets/QueryPanel.html>`_.
 *  OpenGeo GXP are high-level GeoExt components that can be
 *  used in Heron as well. A GXP QueryPanel allows a user to construct query conditions interactively.
 *  These query conditions will be translated to WFS Filters embedded in an WFS GetFeature request.
 *  Results may be displayed in a  `Heron.widgets.search.FeaturePanel <FeaturePanel.html>`_,
 *  see example configuration.
 */
Heron.widgets.search.GXP_QueryPanel = Ext.extend(gxp.QueryPanel, {
    statusReady: __('Ready'),
    statusNoQueryLayers: __('No query layers found'),
    wfsVersion: '1.1.0',
    title: __('Query Panel'),
    bodyStyle: 'padding: 12px',

    /** api: config[layerSortOrder]
     *  ``String``
     *  How should the layer names be sorted in the selector, 'ASC', 'DESC' or null (as Map order)?
     *  default value is 'ASC' (Alphabetically Ascending).
     */
    layerSortOrder: 'ASC',

    /** api: config[caseInsensitiveMatch]
     *  ``Boolean``
     *  Should Comparison Filters for Strings do case insensitive matching?  Default is ``"false"``.
     */
    caseInsensitiveMatch: false,

    /** api: config[autoWildCardAttach]
     *  ``Boolean``
     *  Should search strings (LIKE comparison only) for attribute queries always be pre/postpended with a wildcard '*' character?
     *  Default is ``"false"``.
     */
    autoWildCardAttach: false,

    /** api: config[downloadFormats]
     *  ``Array``
     *  Optional array of explicit download formats (mainly GeoServer-only) to set or overrule any
     *  downloadFormats in the Layer metadata.wfs properties.
     *  Default is null (taking possible values from the Layer metadata).
     */
    downloadFormats: null,

    wfsLayers: undefined,

    layerFilter: function (map) {
        // Select only those (WMS) layers that have a WFS attached.
        // Note: WMS-layers should have the 'metadata.wfs' property configured,
        // either with a full OL WFS protocol object or the string 'fromWMSLayer'.
        // The latter means that a WMS has a related WFS (GeoServer usually).
        return map.getLayersBy('metadata',
                {
                    test: function (metadata) {
                        // no BBOX: some GeoServer WFS-es seem to hang on BBOX queries, so skip
                        return metadata && metadata.wfs && !metadata.wfs.noBBOX;
                    }
                }
        )
    },

    progressMessages: [
        __('Working on it...'),
        __('Still searching, please be patient...'),
        __('Still searching, have you selected an area with too many objects?')
    ],

// See also: http://ian01.geog.psu.edu/geoserver_docs/apps/gaz/search.html
    initComponent: function () {
        var map = this.map = Heron.App.getMap();

        // WFS Layers may be preconfigured or from WMS derived (e.g. GeoServer)
        this.wfsLayers = this.getWFSLayers();

        // Initial config for QueryPanel
        var config = {
            map: map,
            layerStore: new Ext.data.JsonStore({
                data: {
                    layers: this.wfsLayers
                },
                sortInfo: this.layerSortOrder ? {
                    field: 'title',
                    direction: this.layerSortOrder // or 'DESC' (case sensitive for local sorting)
                } : null,
                root: "layers",
                fields: ["title", "name", "namespace", "url", "schema", "options"]
            }),
            listeners: {
                ready: function (panel, store) {
                    store.addListener("exception", this.onQueryException, this);
                },
                layerchange: function (panel, record) {
                    // TODO set layer
                    this.layerRecord = record;
                },
                beforequery: function (panel, store) {
                    // Check for area requested, return false if too large
                    var area = Math.round(map.getExtent().toGeometry().getGeodesicArea(map.projection));
                    var filter = this.getFilter();
                    // TODO check with possibly configured area constraints for that layer
//                    if (area > wfsOptions.maxQueryArea) {
//                        var areaUnits = options.units + '2';
//                        Ext.Msg.alert(__('Warning - Area is ') + area + areaUnits, __('You selected an area for this layer above its maximum of ') + wfsOptions.maxQueryArea + areaUnits);
//                        return false;
//                    }
                    return true;
                },
                query: function (panel, store) {
                    this.fireEvent('searchissued', this);
                },
                storeload: function (panel, store) {
                    var features = [];
                    store.each(function (record) {
                        features.push(record.get("feature"));
                    });

                    var protocol = store.proxy.protocol;
                    var wfsOptions = this.layerRecord.get('options');

                    var filterFormat = new OpenLayers.Format.Filter.v1_1_0({srsName: protocol.srsName});
                    var filterStr = protocol.filter ? OpenLayers.Format.XML.prototype.write.apply(
                            filterFormat, [filterFormat.write(protocol.filter)]
                    ) : null;

                    var downloadInfo = {
                        type: 'wfs',
                        url: protocol.options.url,
                        downloadFormats: this.downloadFormats ? this.downloadFormats : wfsOptions.downloadFormats,
                        params: {
                            typename: protocol.featureType,
                            maxFeatures: undefined,
                            "Content-Disposition": "attachment",
                            filename: protocol.featureType,
                            srsName: protocol.srsName,
                            service: "WFS",
                            version: "1.1.0",
                            request: "GetFeature",
                            filter: filterStr
                        }
                    };

                    var result = {
                        olResponse: store.proxy.response,
                        downloadInfo: downloadInfo
                    };

                    this.fireEvent('searchcomplete', panel, result);
                    store.removeListener("exception", this.onQueryException, this);
                }
            }
        };

		if ( config.layerStore.data.items[0] ) {

        	Ext.apply(this, config);

        	// Setup our own events
        	this.addEvents({
            	"searchissued": true,
            	"searchcomplete": true,
            	"searchfailed": true,
            	"searchsuccess": true,
            	"searchaborted": true
        	});

        	// Compat with QueryBuilder, autoWildCardAttach was renamed to likeSubstring
        	// https://github.com/opengeo/gxp/issues/191
        	this.likeSubstring = this.autoWildCardAttach;

        	Heron.widgets.search.GXP_QueryPanel.superclass.initComponent.call(this);

	        this.addButton(this.createActionButtons());
	        this.addListener("searchissued", this.onSearchIssued, this);
	        this.addListener("searchcomplete", this.onSearchComplete, this);
	        this.addListener("beforedestroy", this.onBeforeDestroy, this);

	        // ExtJS lifecycle events
	        this.addListener("afterrender", this.onPanelRendered, this);

	        if (this.ownerCt) {
	            this.ownerCt.addListener("parenthide", this.onParentHide, this);
	            this.ownerCt.addListener("parentshow", this.onParentShow, this);
	        }

        } else {
			Ext.apply(this, {});
			Heron.widgets.GXP_QueryPanel_Empty.superclass.initComponent.apply(this, arguments);
        }

        this.statusPanel = this.add({
            xtype: "hr_htmlpanel",
            html: config.layerStore.data.items[0] ? this.statusReady : this.statusNoQueryLayers,
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
        });

    },

    createActionButtons: function () {
        this.searchButton = new Ext.Button({
            text: __('Search'),
            tooltip: __('Search in target layer using the selected filters'),
            disabled: false,
            handler: function () {
                this.search();
            },
            scope: this
        });

        this.cancelButton = new Ext.Button({
            text: __('Cancel'),
            tooltip: __('Cancel current search'),
            disabled: true,
            listeners: {
                click: function () {
                    this.searchAbort();
                },
                scope: this
            }

        });
        return this.actionButtons = new Ext.ButtonGroup({
                    fieldLabel: null,
                    anchor: "100%",
                    title: null,
                    border: false,
                    width: 160,
                    padding: '2px',
                    bodyBorder: false,
                    style: {
                        border: '0px'
                    },
                    items: [
                        this.cancelButton,
                        this.searchButton
                    ]
                }
        );
    },

    getWFSLayers: function () {
        var self = this;

        // Preconfigured: return immediately
        if (this.wfsLayers) {
            return this.wfsLayers;
        }

        var wmsLayers = this.layerFilter(this.map);
        var wfsLayers = [];
        Ext.each(wmsLayers, function (wmsLayer) {
            // Determine WFS options
            var wfsOpts = wmsLayer.metadata.wfs;

            // protocol is either 'fromWMSLayer' or a full OL WFS Protocol object
            var protocol = wfsOpts.protocol;
            if (wfsOpts.protocol === 'fromWMSLayer') {
                protocol = OpenLayers.Protocol.WFS.fromWMSLayer(wmsLayer);

                // In rare cases may we have a WMS with multiple URLs n Array (for loadbalancing)
                // Take a random URL. Note: this should really be implemented in OpenLayers Protocol read()
                if (protocol.url instanceof Array) {
                    protocol.url = Heron.Utils.randArrayElm(protocol.url);
                    protocol.options.url = protocol.url;
                }
            } else {
                // Added https://code.google.com/p/geoext-viewer/issues/detail?id=268
                // Use at own risk..
                protocol = wfsOpts.protocol;
            }

            var url = protocol.url.indexOf('?') == protocol.url.length - 1 ? protocol.url.slice(0, -1) : protocol.url;
            var featureType = protocol.featureType;
            var featurePrefix = wfsOpts.featurePrefix;
            var fullFeatureType = featurePrefix ? featurePrefix + ':' + featureType : featureType;
            var wfsVersion = protocol.version ? protocol.version : self.version;
            var outputFormat = protocol.outputFormat ? '&outputFormat=' + protocol.outputFormat : '';

            var wfsLayer = {
                title: wmsLayer.name,
                name: featureType,
                namespace: wfsOpts.featureNS,
                url: url,
                schema: url + '?service=WFS&version=' + wfsVersion + '&request=DescribeFeatureType&typeName=' + fullFeatureType + outputFormat,
                options: wfsOpts
            };
            wfsLayers.push(wfsLayer);
        });
        return wfsLayers;
    },

    getFeatureType: function () {
        return this.layerRecord ? this.layerRecord.get('name') : 'heron';
    },

    updateStatusPanel: function (text) {
        this.statusPanel.body.update(text);
    },


    /** api: method[onPanelRendered]
     *  Called when Panel has been rendered.
     */
    onPanelRendered: function () {
    },

    /** api: method[onParentShow]
     *  Called when parent Panel is shown in Container.
     */
    onParentShow: function () {

    },

    /** api: method[onParentHide]
     *  Called when parent Panel is hidden in Container.
     */
    onParentHide: function () {

    },


    /** api: method[onBeforeDestroy]
     *  Called just before Panel is destroyed.
     */
    onBeforeDestroy: function () {

    },

    /** api: method[onSearchIssued]
     *  Called when remote search (WFS) query has started.
     */
    onQueryException: function (type, action, obj, response_error, o_records) {
        // First check for failures
//        if (!result || !result.success() || result.priv.responseText.indexOf('ExceptionReport') > 0) {
//            this.fireEvent('searchfailed', searchPanel, result);
//            this.updateStatusPanel(__('Search Failed') + ' details: ' + result.priv.responseText);
//            return;
//        }
        this.searchButton.enable();
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.updateStatusPanel(__('Search Failed'));

    },

    /** api: method[onSearchIssued]
     *  Called when remote search (WFS) query has started.
     */
    onSearchIssued: function () {
        this.searchState = "searchissued";
        this.response = null;
        this.features = null;
        this.updateStatusPanel(__('Searching...'));

        // If search takes to long, give some feedback
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.searchButton.disable();
        var self = this;
        var startTime = new Date().getTime() / 1000;
        this.timer = setInterval(function () {
            if (self.searchState != 'searchissued') {
                return;
            }

            // User feedback with seconds passed and random message
            self.updateStatusPanel(Math.floor(new Date().getTime() / 1000 - startTime) +
                    ' ' + __('Seconds') + ' - ' +
                    self.progressMessages[Math.floor(Math.random() * self.progressMessages.length)]);

        }, 4000);
    },

    /** api: method[onSearchComplete]
     *  Function to call when search is complete.
     *  Default is to show "Search completed" with feature count on progress label.
     */
    onSearchComplete: function (searchPanel, result) {
        this.searchButton.enable();
        this.cancelButton.disable();
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        if (this.searchState == 'searchaborted') {
            return;
        }

        this.searchState = "searchcomplete";

        // All ok display result and notify listeners
        var features = result.olResponse.features;
        var featureCount = features ? features.length : 0;
        this.updateStatusPanel(__('Search Completed: ') + featureCount + ' ' + (featureCount != 1 ? __('Results') : __('Result')));

        this.fireEvent('searchsuccess', searchPanel, result);
    },

    /** api: method[searchAbort]
     *
     *  Cancel search in progress.
     */
    searchAbort: function () {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }

        if (this.featureStore && this.featureStore.proxy && this.featureStore.proxy.protocol) {
            this.featureStore.proxy.protocol.abort(this.featureStore.proxy.response);
        }

        this.fireEvent('searchaborted', this);
        this.searchState = 'searchaborted';
        this.searchButton.enable();
        this.cancelButton.disable();
        this.updateStatusPanel(__('Search aborted'));
    },

    /** api: method[search]
     *
     *  Issue query via GXP QueryPanel.
     */
    search: function () {
        this.query();

        this.cancelButton.enable();
    }
});

/** api: xtype = hr_gxpquerypanel */
Ext.reg('hr_gxpquerypanel', Heron.widgets.search.GXP_QueryPanel);
