/**
 * Copyright (c) 2008-2011 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires plugins/LayerSource.js
 * @requires OpenLayers/Layer/ArcGIS93Rest.js
 */

/** api: (define)
 *  module = gxp.plugins
 *  class = ArcRestSource
 */

/** api: (extends)
 *  plugins/LayerSource.js
 */
Ext.namespace("gxp.plugins");

/** api: constructor
 *  .. class:: ArcRestSource(config)
 *
 *    Plugin for using ArcGIS REST MapServer layers with :class:`gxp.Viewer` instances.
 *
 */
/** api: example
 *  The configuration in the ``sources`` property of the :class:`gxp.Viewer` is
 *  straightforward:
 *
 *  .. code-block:: javascript
 *
 *    defaultSourceType: "gxp_arcrestsource",
 *    sources: {
 *        "arcgisrest": {
 *            url: "http://cga6.cga.harvard.edu/arcgis/rest/services/darmc/roman/MapServer"
 *        }
 *    }
 *
 *  A typical configuration for a layer from this source (in the ``layers``
 *  array of the viewer's ``map`` config option would look like this:
 *
 *  .. code-block:: javascript
 *
 *    {
 *        source: "arcgisrest",
 *        name: "Routes",
 *        layerid: "show:74"
 *    }
 *
 */

gxp.plugins.ArcRestSource = Ext.extend(gxp.plugins.LayerSource, {

    /** api: ptype = gxp_arcrestsource */
    ptype:"gxp_arcrestsource",

    requiredProperties: ["name"],

    constructor:function (config) {
        this.config = config;
        gxp.plugins.ArcRestSource.superclass.constructor.apply(this, arguments);
    },


    /** private: method[createStore]
     *
     *  Creates a store of layers.  This requires that the API script has already
     *  loaded.  Fires the "ready" event when the store is loaded.
     */
    createStore:function () {
        var baseUrl = this.url.split("?")[0];
        var source = this;

        var processResult = function (response) {
            var json = Ext.decode(response.responseText);

            var layerProjection = source.getArcProjection(json.spatialReference.wkid);

            var layers = [];
            if (layerProjection != null) {
                for (var l = 0; l < json.layers.length; l++) {
                    var layer = json.layers[l];
                    var layerShow = "show:" + layer.id;
                    layers.push(new OpenLayers.Layer.ArcGIS93Rest(layer.name, baseUrl + "/export",
                        {
                            layers:layerShow,
                            TRANSPARENT:true
                        },
                        {
                            isBaseLayer:false,
                            ratio: 1,
                            displayInLayerSwitcher:true,
                            visibility:true,
                            projection:layerProjection,
                            queryable:json.capabilities && json.capabilities["Identify"]}
                    ));
                }
            } else {
                processFailure(response);
            }

            source.title = json.documentInfo.Title;

            source.store = new GeoExt.data.LayerStore({
                layers:layers,
                fields:[
                    {name:"source", type:"string"},
                    {name:"name", type:"string", mapping:"name"},
                    {name:"layerid", type:"string"},
                    {name:"group", type:"string", defaultValue:this.title},
                    {name:"fixed", type:"boolean", defaultValue:true},
                    {name:"tiled", type:"boolean", defaultValue:true},
                    {name:"queryable", type:"boolean", defaultValue:true},
                    {name:"selected", type:"boolean"}
                ]
            });


            source.fireEvent("ready", source);
        };

        var processFailure = function (response) {
            Ext.Msg.alert("No ArcGIS Layers", "Could not find any compatible layers  at " + source.config.url);
            source.fireEvent("failure", source);
        };


        this.lazy = this.isLazy();

        if (!this.lazy) {
            Ext.Ajax.request({
                url:baseUrl,
                params:{'f':'json', 'pretty':'false', 'keepPostParams':'true'},
                method:'POST',
                success:processResult,
                failure:processFailure
            });
        } else {
            this.fireEvent("ready");
        }
    },


    /** private: method[isLazy]
     *  :returns: ``Boolean``
     *
     *  The store for a lazy source will not be loaded upon creation.  A source
     *  determines whether or not it is lazy given the configured layers for
     *  the target.  If the layer configs have all the information needed to
     *  construct layer records, the source can be lazy.
     */
    isLazy: function() {
        var lazy = true;
        var sourceFound = false;
        var mapConfig = this.target.initialConfig.map;
        if (mapConfig && mapConfig.layers) {
            var layerConfig;
            for (var i=0, ii=mapConfig.layers.length; i<ii; ++i) {
                layerConfig = mapConfig.layers[i];
                if (layerConfig.source === this.id) {
                    sourceFound = true;
                    lazy = this.layerConfigComplete(layerConfig);
                    if (lazy === false) {
                        break;
                    }
                }
            }
        }
        return (lazy && sourceFound);
    },


    /** private: method[layerConfigComplete]
     *  :returns: ``Boolean``
     *
     *  A layer configuration is considered complete if it has a title and a
     *  bbox.
     */
    layerConfigComplete: function(config) {
        var lazy = true;
        var props = this.requiredProperties;
        for (var i=props.length-1; i>=0; --i) {
            lazy = !!config[props[i]];
            if (lazy === false) {
                break;
            }
        }

        return lazy;
    },

    /** api: method[getConfigForRecord]
     *  :arg record: :class:`GeoExt.data.LayerRecord`
     *  :returns: ``Object``
     *
     *  Create a config object that can be used to recreate the given record.
     */
    createLayerRecord:function (config) {
        var record, layer;
        var cmp = function (l) {
            return l.get("name") === config.name;
        };

        var recordExists = this.lazy ||  (this.store && this.store.findBy(cmp) > -1);

        // only return layer if app does not have it already
        if (this.target.mapPanel.layers.findBy(cmp) == -1 && recordExists) {
            // records can be in only one store

            if (!this.lazy &&  this.store.findBy(cmp) > -1 ) {
                record = this.store.getAt(this.store.findBy(cmp)).clone();
            } else {
                record = this.createLazyLayerRecord(config);
            }
            layer = record.getLayer();

            // set layer title from config
            if (config.title) {
                layer.setName(config.title);
                record.set("title", config.title);
            }
            // set visibility from config
            if ("visibility" in config) {
                layer.visibility = config.visibility;
            }

            if ("opacity" in config) {
                layer.opacity = config.opacity;
            }

            if ("format" in config) {
                layer.params.FORMAT = config.format;
                record.set("format", config.format);
            }

            var singleTile = false;
            if ("tiled" in config) {
                singleTile = !config.tiled;
            } 
            record.set("tiled", !singleTile);
            record.set("selected", config.selected || false);
            record.set("queryable", config.queryable || true);
            record.set("source", config.source);
            record.set("name", config.name);
            record.set("layerid", config.layerid);
            record.set("properties", "gxp_wmslayerpanel");
            if ("group" in config) {
                record.set("group", config.group);
            }
            record.commit();
        }
        return record;
    },


    /** api: method[getProjection]
     *  :arg layerRecord: ``GeoExt.data.LayerRecord`` a record from this
     *      source's store
     *  :returns: ``OpenLayers.Projection`` A suitable projection for the
     *      ``layerRecord``. If the layer is available in the map projection,
     *      the map projection will be returned. Otherwise an equal projection,
     *      or null if none is available.
     *
     *  Get the projection that the source will use for the layer created in
     *  ``createLayerRecord``. If the layer is not available in a projection
     *  that fits the map projection, null will be returned.
     */
    getArcProjection:function (srs) {
        var projection = this.getMapProjection();
        var compatibleProjection = projection;
        var layerSRS = "EPSG:" + srs + '';
        if (layerSRS !== projection.getCode()) {
            compatibleProjection = null;
            if ((p = new OpenLayers.Projection(layerSRS)).equals(projection)) {
                compatibleProjection = p;
            }
        }
        return compatibleProjection;
    },

    /** private: method[createLazyLayerRecord]
     *  :arg config: ``Object`` The application config for this layer.
     *  :returns: ``GeoExt.data.LayerRecord``
     *
     *  Create a minimal layer record
     */
    createLazyLayerRecord: function(config) {
        var srs = config.srs || this.target.map.projection;
        config.srs = {};
        config.srs[srs] = true;

        var bbox = config.bbox || this.target.map.maxExtent || OpenLayers.Projection.defaults[srs].maxExtent;
        config.bbox = {};
        config.bbox[srs] = {bbox: bbox};

        var  record = new GeoExt.data.LayerRecord(config);
        record.set("name", config.name);
        record.set("layerid", config.layerid || "show:0");
        record.set("format", config.format || "png");
        record.set("tiled", "tiled" in config ? config.tiled : true);

        record.setLayer(new OpenLayers.Layer.ArcGIS93Rest(config.name,  this.url.split("?")[0] + "/export",
            {
                layers: config.layerid,
                TRANSPARENT:true,
                FORMAT: "format" in config ? config.format : "png"
            },
            {
                isBaseLayer:false,
                displayInLayerSwitcher:true,
                projection:srs,
                singleTile: "tiled" in config ? !config.tiled : false,
                queryable: "queryable" in config ? config.queryable : false}
        )
        );
        return record;

    },


    /** api: method[getConfigForRecord]
     *  :arg record: :class:`GeoExt.data.LayerRecord`
     *  :returns: ``Object``
     *
     *  Create a config object that can be used to recreate the given record.
     */
    getConfigForRecord: function(record) {
        var layer = record.getLayer();
        return {
            source: record.get("source"),
            name: record.get("name"),
            title: record.get("title"),
            tiled: record.get("tiled"),
            visibility: layer.getVisibility(),
            layerid: layer.params.LAYERS,
            format: layer.params.FORMAT,
            opacity: layer.opacity || undefined,
            group: record.get("group"),
            fixed: record.get("fixed"),
            selected: record.get("selected")
        };
    }

});

Ext.preg(gxp.plugins.ArcRestSource.prototype.ptype, gxp.plugins.ArcRestSource);
