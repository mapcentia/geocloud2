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
 *  class = SpatialSearchPanel
 *  base_link = `Ext.Panel <http://docs.sencha.com/ext-js/3-4/#!/api/Ext.Panel>`_
 */

/** api: example
 *  This is an abstract base class that cannot be used directly. See examples in subclasses
 *  like the `Heron.widgets.search.SearchByDrawPanel <SearchByDrawPanel.html>`_ and
 *  the `Heron.widgets.search.SearchByFeaturePanel <SearchByFeaturePanel.html>`_..
 *
 */

/** api: constructor
 *  .. class:: SpatialSearchPanel(config)
 *
 * Abstract base class for specific spatial queries. This class itself cannot be
 * instantiated, use subclasses like the `Heron.widgets.search.SearchByDrawPanel <SearchByDrawPanel.html>`_.
 */
Heron.widgets.search.SpatialSearchPanel = Ext.extend(Ext.Panel, {
    layout: 'form',
    bodyStyle: 'padding: 24px 12px 12px 12px',
    border: false,

    /** api: config[name]
     *  ``String``
     *  Name, e.g. for multiple searches combo.
     */
    name: __('Spatial Search'),

    /** api: config[description]
     *  ``String``
     *  Default description in status area.
     */
    description: '',

    /** api: config[filterFeatures]
     *  ``Array``
     *  Features from last external Search.
     *  Default null
     */
    fromLastResult: false,

    /** api: config[lastSearchName]
     *  ``String``
     *  Name of last Search (UNUSED).
     *  Default null
     */
    lastSearchName: null,

    /** api: config[filterFeatures]
     *  ``Array``
     *  Features from last external Search.
     *  Default null
     */
    filterFeatures: null,

    showFilterFeatures: true,

    /** api: config[maxFilterGeometries]
     *  ``Integer``
     *  Max features to use for Search selection.
     *  Default 24
     */
    maxFilterGeometries: 24,

    /** api: config[selectLayerStyle]
     *  ``Object``
     *  OpenLayers Style config to use for features Selection Layer.
     *  Default reddish
     */
    selectLayerStyle: {
        pointRadius: 10,
        strokeColor: "#dd0000",
        strokeWidth: 1,
        fillOpacity: 0.4,
        fillColor: "#cc0000"
    },

    /** api: config[layerSortOrder]
     *  ``String``
     *  How should the layer names be sorted in the selector, 'ASC', 'DESC' or null (as Map order)?
     *  default value is 'ASC' (Alphabetically Ascending).
     */
    layerSortOrder: 'ASC',

    /** api: config[downloadFormats]
     *  ``Array``
     *  Optional array of explicit download formats (mainly GeoServer-only) to set or overrule any
     *  downloadFormats in the Layer metadata.wfs properties.
     *  Default is null (taking possible values from the Layer metadata).
     */
    downloadFormats: null,

    /** api: config[layerFilter]
     *  ``Function``
     *  Filter for OpenLayer getLayersBy(), to filter out WFS-enabled Layers from Layer array.
     *  Default: only Layers that have metadata.wfs (see OpenLayers Layer spec and examples) set.
     */
    layerFilter: function (map) {
        /* Select only those (WMS) layers that have a WFS attached
         * Note: WMS-layers should have the 'metadata.wfs' property configured,
         * either with a full OL WFS protocol object or the string 'fromWMSLayer'.
         * The latter means that a WMS has a related WFS (GeoServer usually).
         */
        return map.getLayersBy('metadata',
                {
                    test: function (metadata) {
                        return metadata && metadata.wfs;
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

        this.addEvents(this.getEvents());

        Heron.widgets.search.SpatialSearchPanel.superclass.initComponent.call(this);

        this.map = Heron.App.getMap();
        this.addSelectionLayer();

        this.addListener("selectionlayerupdate", this.onSelectionLayerUpdate, this);
        this.addListener("targetlayerselected", this.onTargetLayerSelected, this);
        this.addListener("drawingcomplete", this.onDrawingComplete, this);
        this.addListener("searchissued", this.onSearchIssued, this);
        this.addListener("searchcomplete", this.onSearchComplete, this);
        this.addListener("searchcanceled", this.onSearchCanceled, this);
        this.addListener("beforehide", this.onBeforeHide, this);
        this.addListener("beforeshow", this.onBeforeShow, this);
        this.addListener("beforedestroy", this.onBeforeDestroy, this);

        // ExtJS lifecycle events
        this.addListener("afterrender", this.onPanelRendered, this);

        if (this.ownerCt) {
            this.ownerCt.addListener("parenthide", this.onParentHide, this);
            this.ownerCt.addListener("parentshow", this.onParentShow, this);
        }
    },

    addSelectionLayer: function () {
        if (this.selectionLayer) {
            return;
        }
        this.selectionLayer = new OpenLayers.Layer.Vector(__('Selection'), {
            style: this.selectLayerStyle,
            displayInLayerSwitcher: false,
            hideInLegend: false,
            isBaseLayer: false
        });
        this.map.addLayers([this.selectionLayer]);
    },

    getEvents: function () {
        // Setup our own events
        return {
            "drawcontroladded": true,
            "selectionlayerupdate": true,
            "targetlayerselected": true,
            "drawingcomplete": true,
            "searchissued": true,
            "searchcomplete": true,
            "searchcanceled": true,
            "searchfailed": true,
            "searchsuccess": true,
            "searchreset": true
        };

    },

    createStatusPanel: function () {

        var infoText = __('Select the Layer to query') + '<p>' + this.description + '</p>';
        if (this.lastSearchName) {
            infoText += '<p>' + __('Using geometries from the result: ') + '<br/>' + this.lastSearchName;
            if (this.filterFeatures) {
                infoText += '<br/>' + __('with') + ' ' + this.filterFeatures.length + ' ' + __('features');
            }
            infoText += '</p>';
        }

        this.statusPanel = new Heron.widgets.HTMLPanel({
            html: infoText,
            preventBodyReset: true,
            bodyCfg: {
                style: {
                    padding: '6px',
                    border: '1px'
                }
            },
            style: {
                marginTop: '10px',
                marginBottom: '10px',
                fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
                fontSize: '11px',
                color: '#0000C0'
            }
        });

        return this.statusPanel;
    },

    createDrawToolPanel: function (config) {
        var defaultConfig = {
            html: '<div class="olControlEditingToolbar olControlNoSelect">&nbsp;</div>',
            preventBodyReset: true,
            style: {
                marginTop: '32px',
                marginBottom: '24px'
            },
            activateControl: true,
            listeners: {
                afterrender: function (htmlPanel) {
                    var div = htmlPanel.body.dom.firstChild;
                    if (!div) {
                        Ext.Msg.alert('Warning', 'Cannot render draw controls');
                        return;
                    }
                    this.addDrawControls(div);
                    if (this.activateControl) {
                        this.activateDrawControl();
                    }
                },
                scope: this
            }
        };
        config = Ext.apply(defaultConfig, config);

        return this.drawToolPanel = new Heron.widgets.HTMLPanel(config);
    },

    addDrawControls: function (div) {
        this.drawControl = new OpenLayers.Control.EditingToolbar(this.selectionLayer, {div: div});

        // Bit a hack but we want tooltips for the drawing controls.
        this.drawControl.controls[0].panel_div.title = __('Return to map navigation');
        this.drawControl.controls[1].panel_div.title = __('Draw point');
        this.drawControl.controls[2].panel_div.title = __('Draw line');
        this.drawControl.controls[3].panel_div.title = __('Draw polygon');

        var drawCircleControl = new OpenLayers.Control.DrawFeature(this.selectionLayer,
                OpenLayers.Handler.RegularPolygon, {
                    title: __('Draw circle (click and drag)'),
                    displayClass: 'olControlDrawCircle',
                    handlerOptions: {
                        citeCompliant: this.drawControl.citeCompliant,
                        sides: 30,
                        irregular: false
                    }
                }
        );
        this.drawControl.addControls([drawCircleControl]);

        // Add extra rectangle draw
        var drawRectangleControl = new OpenLayers.Control.DrawFeature(this.selectionLayer,
                OpenLayers.Handler.RegularPolygon, {
                    displayClass: 'olControlDrawRectangle',
                    title: __('Draw Rectangle (click and drag)'),
                    handlerOptions: {
                        citeCompliant: this.drawControl.citeCompliant,
                        sides: 4,
                        irregular: true
                    }
                }
        );
        this.drawControl.addControls([drawRectangleControl]);

        this.map.addControl(this.drawControl);
        this.activeControl = drawRectangleControl;
        this.fireEvent('drawcontroladded');
    },

    removeDrawControls: function () {
        if (this.drawControl) {
            var self = this;
            Ext.each(this.drawControl.controls, function (control) {
                self.map.removeControl(control);
            });
            this.map.removeControl(this.drawControl);
            this.drawControl = null;
        }
    },

    activateDrawControl: function () {
        if (!this.drawControl || this.drawControlActive) {
            return;
        }
        var self = this;
        Ext.each(this.drawControl.controls, function (control) {
            control.events.register('featureadded', self, self.onFeatureDrawn);
            control.deactivate();
            // If we have a saved active control: activate it
            if (self.activeControl && control == self.activeControl) {
                control.activate();
            }
        });
        this.drawControlActive = true;
    },

    deactivateDrawControl: function () {
        if (!this.drawControl) {
            return;
        }
        var self = this;
        Ext.each(this.drawControl.controls, function (control) {
            control.events.unregister('featureadded', self, self.onFeatureDrawn);

            // Deactivate all controls and save the active control (see onParentShow)
            if (control.active) {
                self.activeControl = control;
            }
            control.deactivate();
        });
        this.updateStatusPanel();
        this.drawControlActive = false;
    },

    onFeatureDrawn: function () {

    },

    createTargetLayerCombo: function (config) {
        var defaultConfig = {
//            anchor: '100%',
            fieldLabel: __('Search in'),
            sortOrder: this.layerSortOrder,
            layerFilter: this.layerFilter,
            selectFirst: true,
            listeners: {
                selectlayer: function (layer) {
                    this.targetLayer = layer;
                    this.fireEvent('targetlayerselected');
                },
                scope: this
            }
        };

        config = Ext.apply(defaultConfig, config);
        return this.targetLayerCombo = new Heron.widgets.LayerCombo(config);
    },

    getFeatureType: function () {
        return this.lastFeatureType ? this.lastFeatureType : (this.targetLayer ? this.targetLayer.name : 'heron');
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

    /** api: method[onBeforeHide]
     *  Called just before Panel is hidden.
     */
    onBeforeHide: function () {
        if (this.selectionLayer) {
            this.selectionLayer.setVisibility(false);
        }
    },

    /** api: method[onBeforeDestroy]
     *  Called just before Panel is shown.
     */
    onBeforeShow: function () {
        if (this.selectionLayer) {
            this.selectionLayer.setVisibility(true);
        }
    },

    /** api: method[onBeforeDestroy]
     *  Called just before Panel is destroyed.
     */
    onBeforeDestroy: function () {
        this.deactivateDrawControl();
        if (this.selectionLayer) {
            this.selectionLayer.removeAllFeatures();
            this.map.removeLayer(this.selectionLayer);
        }

    },

    /** api: method[onDrawingComplete]
     *  Called when feature drawn selected.
     */
    onDrawingComplete: function (searchPanel, selectionLayer) {
    },

    /** api: method[onLayerSelect]
     *  Called when Layer selected.
     */
    onTargetLayerSelected: function () {

    },

    /** api: method[onLayerSelect]
     *  Called when Layer selected.
     */
    onSelectionLayerUpdate: function () {

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

    /** api: method[onSearchCanceled]
     *  Function called when search is canceled.
     */
    onSearchCanceled: function (searchPanel) {
        this.searchState = 'searchcanceled';
        this.searchAbort();
        this.updateStatusPanel(__('Search Canceled'));
    },

    /** api: method[onSearchComplete]
     *  Function to call when search is complete.
     *  Default is to show "Search completed" with feature count on progress label.
     */
    onSearchComplete: function (searchPanel, result) {
        this.protocol = null;
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        if (this.sketch) {
            this.selectionLayer.removeAllFeatures();
            this.sketch = false;
        }
        this.fireEvent('selectionlayerupdate');

        if (this.searchState == 'searchcanceled') {
            this.fireEvent('searchfailed', searchPanel, null);
            return;
        }

        this.searchState = "searchcomplete";

        // First check for failures
        var olResponse = result.olResponse;
        if (!olResponse || !olResponse.success() || olResponse.priv.responseText.indexOf('ExceptionReport') > 0) {
            this.fireEvent('searchfailed', searchPanel, olResponse);
            this.updateStatusPanel(__('Search Failed') + ' details: ' + olResponse.priv.responseText);
            return;
        }

        // All ok display result and notify listeners, subclass may override
        this.onSearchSuccess(searchPanel, result);
    },

    /** api: method[onSearchSuccess]
     *  Function called when search is complete and succesful.
     *  Default is to show "Search completed" with feature count on progress label.
     */
    onSearchSuccess: function (searchPanel, result) {

        // All ok display result and notify listeners
        var features = this.features = this.filterFeatures = result.olResponse.features;
        var featureCount = features ? features.length : 0;
        this.updateStatusPanel(__('Search Completed: ') + featureCount + ' ' + (featureCount != 1 ? __('Results') : __('Result')));
        this.fireEvent('searchsuccess', searchPanel, result);
    },

    /** api: method[search]
     *
     *  Issue spatial search via WFS.
     */
    search: function (geometries, options) {
        var targetLayer = options.targetLayer;

        // Determine WFS protocol
        var wfsOptions = targetLayer.metadata.wfs;

        if (wfsOptions.protocol == 'fromWMSLayer') {
            // WMS has related WFS layer (usually GeoServer)
            this.protocol = OpenLayers.Protocol.WFS.fromWMSLayer(targetLayer, {outputFormat: 'GML2'});

            // In rare cases may we have a WMS with multiple URLs n Array (for loadbalancing)
            // Take a random URL. Note: this should really be implemented in OpenLayers Protocol read()
            if (this.protocol.url instanceof Array) {
                this.protocol.url = Heron.Utils.randArrayElm(this.protocol.url);
                this.protocol.options.url = this.protocol.url;
            }
        } else {
            // WFS via Regular OL WFS protocol object
            this.protocol = wfsOptions.protocol;
        }

        this.lastFeatureType = this.protocol.featureType;

        var geometry = geometries[0];

        // Create WFS Spatial Filter from Geometry
        var spatialFilterType = options.spatialFilterType || OpenLayers.Filter.Spatial.INTERSECTS;
        var filter = new OpenLayers.Filter.Spatial({
            type: spatialFilterType,
            value: geometry
        });

        if (geometries.length > 1) {
            var filters = [];
            geometry = new OpenLayers.Geometry.Collection();
            Ext.each(geometries, function (g) {
                geometry.addComponent(g);
                filters.push(new OpenLayers.Filter.Spatial({
                    type: OpenLayers.Filter.Spatial.INTERSECTS,
                    value: g
                }));
            });

            filter = new OpenLayers.Filter.Logical({
                type: OpenLayers.Filter.Logical.OR,
                filters: filters
            });
        }
        if (geometry.CLASS_NAME.indexOf('LineString') > 0 && wfsOptions.maxQueryLength) {
            var length = Math.round(geometry.getGeodesicLength(options.projection));
            if (length > wfsOptions.maxQueryLength) {
                this.selectionLayer.removeAllFeatures();
                var units = options.units;
                Ext.Msg.alert(__('Warning - Line Length is ') + length + units, __('You drew a line with length above the layer-maximum of ') + wfsOptions.maxQueryLength + units);
                return false;
            }
        }
        if (geometry.CLASS_NAME.indexOf('Polygon') > 0 && wfsOptions.maxQueryArea) {
            var area = Math.round(geometry.getGeodesicArea(options.projection));
            if (area > wfsOptions.maxQueryArea) {
                this.selectionLayer.removeAllFeatures();
                var areaUnits = options.units + '2';
                Ext.Msg.alert(__('Warning - Area is ') + area + areaUnits, __('You selected an area for this layer above its maximum of ') + wfsOptions.maxQueryArea + areaUnits);
                return false;
            }
        }

        var filterFormat = new OpenLayers.Format.Filter.v1_1_0({srsName: this.protocol.srsName});
        var filterStr = OpenLayers.Format.XML.prototype.write.apply(
                filterFormat, [filterFormat.write(filter)]
        );

        //        filterStr ='<ogc:Filter xmlns:ogc="http://www.opengis.net/ogc"><ogc:Intersects><ogc:PropertyName/><gml:Polygon xmlns:gml="http://www.opengis.net/gml" srsName="EPSG:4326"><gml:exterior><gml:LinearRing><gml:posList>-107.0966796875 31.03515625 -107.0966796875 46.416015625 -85.5634765625 46.416015625 -85.5634765625 31.03515625 -107.0966796875 31.03515625</gml:posList></gml:LinearRing></gml:exterior></gml:Polygon></ogc:Intersects></ogc:Filter>';
        //        filterStr ='<ogc:Filter xmlns:ogc="http://www.opengis.net/ogc"><ogc:Intersects><ogc:PropertyName/><gml:Polygon xmlns:gml="http://www.opengis.net/gml" srsName="EPSG:4326"><gml:exterior><gml:LinearRing><gml:posList>-96.9013671875 32.529296875 -96.9013671875 32.96875 -96.4619140625 32.96875 -96.4619140625 32.529296875 -96.9013671875 32.529296875</gml:posList></gml:LinearRing></gml:exterior></gml:Polygon></ogc:Intersects></ogc:Filter>';
        // Heron.data.DataExporter.directDownload(url);

        // document.body.appendChild(iframe);
//        if (!targetLayer.metadata.wfs.store) {
//            targetLayer.metadata.wfs.store = new GeoExt.data.WFSCapabilitiesStore({
//                url: OpenLayers.Util.urlAppend(downloadInfo.url, 'SERVICE=WFS&REQUEST=GetCapabilities&VERSION=1.1.0'),
//                autoLoad: true
//            });
//            targetLayer.metadata.wfs.store.load();
//        }

        // Issue the WFS request
        var maxFeatures = this.single == true ? this.maxFeatures : undefined;
        this.response = this.protocol.read({
            maxFeatures: maxFeatures,
            filter: filter,
            callback: function (olResponse) {
                if (!this.protocol) {
                    return;
                }
                var downloadInfo = {
                    type: 'wfs',
                    url: this.protocol.options.url,
                    downloadFormats: this.downloadFormats ? this.downloadFormats : wfsOptions.downloadFormats,
                    params: {
                        typename: this.protocol.featureType,
                        maxFeatures: maxFeatures,
                        "Content-Disposition": "attachment",
                        filename: targetLayer.name,
                        srsName: this.protocol.srsName,
                        service: "WFS",
                        version: "1.1.0",
                        request: "GetFeature",
                        filter: filterStr
                    }
                };
                var result = {
                    olResponse: olResponse,
                    downloadInfo: downloadInfo,
                    layer: targetLayer
                };
                this.fireEvent('searchcomplete', this, result);
            },
            scope: this
        });
        this.fireEvent('searchissued', this);

        return true;
    },

    /** api: method[searchAbort]
     *
     *  Abort/cancel spatial search via WFS.
     */
    searchAbort: function () {
        if (this.protocol) {
            this.protocol.abort(this.response);
        }
        this.protocol = null;

        if (this.timer) {
             clearInterval(this.timer);
             this.timer = null;
         }
    }
});

/** api: xtype = hr_spatialsearchpanel */
Ext.reg('hr_spatialsearchpanel', Heron.widgets.search.SpatialSearchPanel);


