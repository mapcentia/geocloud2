/**
 * Copyright (c) 2008-2012 The Open Source Geospatial Foundation
 * Published under the BSD license.
 * See http://geoext.org/svn/geoext/core/trunk/license.txt for the full text
 * of the license.
 */

/** api: (define)
 *  module = Heron.widgets.search
 *  class = GeocoderCombo
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/dev/docs/?class=Ext.form.ComboBox>`_
 *
 * Adapted from 'GeocoderCombo.js' - < http://dev.geoext.org > for HERON use
 * < http://dev.geoext.org/geoext/trunk/geoext/examples/ >
 *
 *
 * To use this component you must use the GeoExt trunk from 08-MAY-2012 (or later)
 * <http://dev.geoext.org/geoext/trunk/geoext/>
 *
 * See also: http://code.google.com/p/geoext-viewer/issues/detail?id=122
 *
 */
Ext.namespace("Heron.widgets.search");

/** api: constructor
 *  .. class:: GeocoderCombo(config)
 *
 *  Creates a combo box that handles results from a geocoding service. By
 *  default it uses OSM Nominatim, but it can be configured with a custom store
 *  to use other services like WFS. If the user enters a valid address in the search
 *  box, the combo's store will be populated with records that match the
 *  address.  By default, records have the following fields:
 *  
 *  * name - ``String`` The formatted address.
 *  * lonlat - ``Array`` Location matching address, for use with
 *      OpenLayers.LonLat.fromArray.
 *  * bounds - ``Array`` Recommended viewing bounds, for use with
 *      OpenLayers.Bounds.fromArray.
 */   
Heron.widgets.search.GeocoderCombo = Ext.extend(Ext.form.ComboBox, {
    
    /** api: config[map]
     *  ``GeoExt.MapPanel|OpenLayers.Map`` The map that will be controlled by
     *  this GeoCoderComboBox. Only used if this component is not added as item
     *  or toolbar item to a ``GeoExt.MapPanel``.
     */
    map: null,

    /** api: config[emptyText]
     *  ``String`` Text to display for an empty field (i18n).
     */
    emptyText: __('Search'),

    /** api: config[loadingText]
     *  ``String`` Text to display for an empty field (i18n).
     */
    loadingText: __('Loading...'),
    
    /** api: config[srs]
     *  ``String|OpenLayers.Projection`` The srs used by the geocoder service.
     *  Default is "EPSG:4326".
     */
    srs: "EPSG:4326",
    
    /** api: config[zoom]
     *  ``String`` The minimum zoom level to use when zooming to a location.
     *  If zoom < 0 then zoom to extent. Default is 10.
     */
    zoom: 10,
    
	/** api: config[layerOpts]
	 *  Options for layer activation when search was successful.
	 */
    layerOpts: undefined,
    
    /** api: config[layer]
     *  ``OpenLayers.Layer.Vector`` If provided, a marker will be drawn on this
     *  layer with the location returned by the geocoder. 
     *  DISABLED: The location will be cleared when the map panned. 
     */
    
    /** api: config[queryDelay]
     *  ``Number`` Delay before the search occurs.  Default is 200ms.
     */
    queryDelay: 200,
    
    /** api: config[valueField]
     *  ``String`` Field from selected record to use when the combo's
     *  :meth:`getValue` method is called.  Default is "bounds". This field is
     *  supposed to contain an array of [left, bottom, right, top] coordinates
     *  for a bounding box or [x, y] for a location. 
     */
    valueField: "bounds",

    /** api: config[displayField]
     *  ``String`` The field to display in the combo boy. Default is
     *  "name" for instant use with the default store for this component.
     */
    displayField: "name",
    
    /** api: config[locationField]
     *  ``String`` The field to get the location from. This field is supposed
     *  to contain an array of [x, y] for a location. Default is "lonlat" for
     *  instant use with the default store for this component.
     */
    locationField: "lonlat",
    
    /** api: config[url]
     *  ``String`` URL template for querying the geocoding service. If a
     *  :obj:`store` is configured, this will be ignored. Note that the
     *  :obj:`queryParam` will be used to append the user's combo box
     *  input to the url. Default is
     *  "http://nominatim.openstreetmap.org/search?format=json", for instant
     *  use with the OSM Nominatim geolocator. However, if you intend to use
     *  that, note the
     *  `Nominatim Usage Policy <http://wiki.openstreetmap.org/wiki/Nominatim_usage_policy>`_.
     */
    url: "http://nominatim.openstreetmap.org/search?format=json",
    
    /** api: config[queryParam]
     *  ``String`` The query parameter for the user entered search text.
     *  Default is "q" for instant use with OSM Nominatim.
     */
    queryParam: "q",
    
    /** api: config[minChars]
     *  ``Number`` Minimum number of entered characters to trigger a search.
     *  Default is 3.
     */
    minChars: 3,

	/** api: property[hideTrigger]
	 *  Hide trigger of the combo.
	 */
	hideTrigger: true,

	/** api: config[tooltip]
	 *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
	 *  default value is "Search".
	 */
    tooltip: __('Search'),
    
    /** api: config[store]
     *  ``Ext.data.Store`` The store used for this combo box. Default is a
     *  store with a ScriptTagProxy and the url configured as :obj:`url`
     *  property.
     */
    
    /** private: property[center]
     *  ``OpenLayers.LonLat`` Last center that was zoomed to after selecting
     *  a location in the combo box.
     */
    
    /** private: property[locationFeature]
     *  ``OpenLayers.Feature.Vector`` Last location provided by the geolocator.
     *  Only set if :obj:`layer` is configured.
     */

	/** Example: The example below uses plain WFS but needs a proxy
		...
		{
            xtype: "hr_geocodercombo",
            id: 'gui_Combo_SucheOrganisationRB',
            style: 'font-size: 11px;',
            width: 185,
            emptyText: 'Please enter name...',
            hideTrigger: true,
            loadingText: 'Searching XYZ...',
            minChars: 1,
            queryDelay: 300,
            fieldLabel: 'Search - Name of XYZ',
            labelStyle: 'font-size: 11px;'
            , tooltip: "Searching XYZ",
            // srs: "EPSG:31467",
            srs: Heron.options.map.settings.projection,
            // zoom: 17,	// >= 0 => zoom to zoomlevel
            zoom: -1,		//  < 0 => zoom to zoom to extent
			layerOpts: [	{ layerOn: 'Raster' , layerOpacity: 1.0  },
							{ layerOn: 'XYZ', layerOpacity: 1.0 }
					   ],
            store: new Ext.data.Store({
                		reader: new GeoExt.data.FeatureReader({}, [
                		    {name: 'name', mapping: 'NAME'},
                		    {name: 'bounds', convert: function(v, feature) {
                		        return feature.geometry.getBounds().toArray();
		                    }}
		                ]),
          	proxy: new (Ext.extend(GeoExt.data.ProtocolProxy, {
                    	doRequest: function(action, records, params, reader, callback, scope, arg) {
	                        if (params.q) {
	                            params.filter = new OpenLayers.Filter.Comparison({
	                                type: OpenLayers.Filter.Comparison.LIKE,
	                                matchCase: false,
	                                property: 'NAME',
	                                value: '*' + params.q + '*'
	                            });
	                            delete params.q;
	                        }
	                        GeoExt.data.ProtocolProxy.prototype.doRequest.apply(this, arguments);
	                    }
	                	}))({
	                    protocol: new OpenLayers.Protocol.WFS({
	                        version: "1.1.0",
	                        // url: "http://isdduisr019.sv.db.de:8080/geoserver/wfs",
	                        url: Heron.scratch.urls.WFS_ISD,
	                        featurePrefix: "isd-db",
	                        featureType: "db-org-rb",
	                        // srsName: "EPSG:31467",
                            srsName: Heron.options.map.settings.projection,
	                        propertyNames: ['NAME', 'the_geom'],
	                        maxFeatures: 20
	                        , sortBy: 'NAME'
	                    }),
	                    setParamsAsOptions: true
	                	})
	            	})
		}
		...
	*/
    
    /** private: method[initComponent]
     *  Override
     */   
    initComponent: function() {
        if (this.map) {
            this.setMap(this.map);
        } 
        if (Ext.isString(this.srs)) {
            this.srs = new OpenLayers.Projection(this.srs);
        }
        if (!this.store) {
            this.store = new Ext.data.JsonStore({
                root: null,
                fields: [
                    {name: "name", mapping: "display_name"},
                    {name: "bounds", convert: function(v, rec) {
                        var bbox = rec.boundingbox;
                        return [bbox[2], bbox[0], bbox[3], bbox[1]];
                    }},
                    {name: "lonlat", convert: function(v, rec) {
                        return [rec.lon, rec.lat];
                    }}
                ],
                proxy: new Ext.data.ScriptTagProxy({
                    url: this.url,
                    callbackParam: "json_callback"
                })
            });
        }
        
        this.on({
            added: this.handleAdded,
            select: this.handleSelect,
            focus: function() {
                this.clearValue();
                this.removeLocationFeature();
            },
            scope: this
        });

        return Heron.widgets.search.GeocoderCombo.superclass.initComponent.apply(this, arguments);
    },
    
    /** private: method[handleAdded]
     *  When this component is added to a container, see if it has a parent
     *  MapPanel somewhere and set the map
     */
    handleAdded: function() {
//        var mapPanel = this.findParentBy(function(cmp) {
//            return cmp instanceof GeoExt.MapPanel;
//        });
//        if (mapPanel) {
//            this.setMap(mapPanel);
//        }

		// --- HERON ---
		if (! this.map) {
			this.setMap( Heron.App.getMap() );
		}

    },
    
    /** private: method[handleSelect]
     *  Zoom to the selected location, and also set a location marker if this
     *  component was configured with an :obj:`layer`.
     */
    handleSelect: function(combo, rec) {                
        var value = this.getValue();
        if (Ext.isArray(value)) {
            var mapProj = this.map.getProjectionObject();
            delete this.center;
            delete this.locationFeature;
            // if (value.length === 4) {
			// zoom < 0 => zoom to extent
			if (this.zoom < 0) {
                this.map.zoomToExtent(
                    OpenLayers.Bounds.fromArray(value)
                        .transform(this.srs, mapProj)
                );
            } else {
                 this.map.setCenter(
                    OpenLayers.LonLat.fromArray(value)
                        .transform(this.srs, mapProj),
                    Math.max(this.map.getZoom(), this.zoom)
                );
            }
            this.center = this.map.getCenter();

            var lonlat = rec.get(this.locationField);
            if (this.layer && lonlat) {

				//  var geom = new OpenLayers.Geometry.Point(lonlat[0], lonlat[1]).transform(this.srs, mapProj);
				var geom = new OpenLayers.Geometry.Point(this.center.lon, this.center.lat).transform(this.srs, mapProj);
                this.locationFeature = new OpenLayers.Feature.Vector(geom, rec.data);
                this.layer.addFeatures([this.locationFeature]);

               	// Set the visibility of the layer always to ON
				var vm = this.map.getLayersByName(this.layer);
             	if (vm.length===0) {
					this.layer.setVisibility(true);
				}

            }
            
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
            
        }
        // blur the combo box
        //TODO Investigate if there is a more elegant way to do this.
        (function() {
            this.triggerBlur();
            this.el.blur();
        }).defer(100, this);
    },
    
    /** private: method[removeLocationFeature]
     *  Remove the location marker from the :obj:`layer` and destroy the
     *  :obj:`locationFeature`.
     */
    removeLocationFeature: function() {
        if (this.locationFeature) {
            this.layer.destroyFeatures([this.locationFeature]);
        }
    },
    
    /** private: method[clearResult]
     *  Handler for the map's moveend event. Clears the selected location
     *  when the map center has changed.
     */
    clearResult: function() {
        if (this.center && !this.map.getCenter().equals(this.center)) {
            this.clearValue();
        }
    },
    
    /** private: method[setMap]
     *  :param map: ``GeoExt.MapPanel||OpenLayers.Map``
     *
     *  Set the :obj:`map` for this instance.
     */
    setMap: function(map) {
        if (map instanceof GeoExt.MapPanel) {
            map = map.map;
        }
        this.map = map;
        map.events.on({
            "moveend": this.clearResult,
// don't clear location
//            "click": this.removeLocationFeature,
            scope: this
        });
    },
    
    /** private: method[addToMapPanel]
     *  :param panel: :class:`GeoExt.MapPanel`
     *  
     *  Called by a MapPanel if this component is one of the items in the panel.
     */
    addToMapPanel: Ext.emptyFn,
    
    /** private: method[beforeDestroy]
     */
    beforeDestroy: function() {
        this.map.events.un({
            "moveend": this.clearResult,
// don't clear location
//            "click": this.removeLocationFeature,
            scope: this
        });
        this.removeLocationFeature();
        delete this.map;
        delete this.layer;
        delete this.center;
        Heron.widgets.search.GeocoderCombo.superclass.beforeDestroy.apply(this, arguments);
    },

    /** method[listeners]
     *  Show qtip
     */
    listeners: {
		render: function(c){
        	c.el.set({qtip: this.tooltip});
        	c.trigger.set({qtip: this.tooltip});
    	}
	}

});

/** api: xtype = hr_geocodercombo */
Ext.reg("hr_geocodercombo", Heron.widgets.search.GeocoderCombo);
