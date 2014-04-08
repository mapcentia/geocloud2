/**
 * Copyright (c) 2008-2011 The Open Planning Project
 *
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires plugins/LayerSource.js
 * @requires plugins/FeedGetFeatureInfo.js
 * @requires OpenLayers/Format/GeoRSS.js
 * @requires OpenLayers/Format/GeoJSON.js
 * @requires OpenLayers/Format/GML.js
 * @requires OpenLayers/Format/QueryStringFilter.js
 * @requires OpenLayers/Protocol/HTTP.js
 * @requires OpenLayers/Strategy/BBOX.js
 * @requires OpenLayers/Strategy/Fixed.js
 * @requires OpenLayers/Filter/Spatial.js
 * @requires GeoExt/widgets/Popup.js
 */

/** api: (define)
 *  module = gxp.plugins
 *  class = FeedSource
 */

/** api: (extends)
 *  plugins/LayerSource.js
 */

Ext.namespace("gxp.plugins");

/** api: constructor
 *  .. class:: FeedSource(config)
 *
 *       Plugin for using geoRSS feeds as layers with :class:`gxp.Viewer` instances.
 */
gxp.plugins.FeedSource = Ext.extend(gxp.plugins.LayerSource, {

    /** api: ptype = gxp_feedsource **/
    ptype:'gxp_feedsource',

    /** api: config[url]
     * ``String`` URL  for GeoRSS feed
     **/

    /** api: config[title]
     * ``String`` Title for source
     **/
    title:'Feed Source',

    /** api: config[format]
     * ``String`` Default format of vector layer
     **/
    format:'OpenLayers.Format.GeoRSS',

    /** api: config[popupFormat]
     *  ``String``  XTemplate string for feature info popup
     */
    popupTemplate:'<a target="_blank" href="{link}">{description}</a>',
    
    
    /** api: config[fixed]
     * ``Boolean`` Use OpenLayers.Strategy.Fixed if true, BBOX if false
     **/    
    fixed: true,

    /** api: method[createLayerRecord]
     *  :arg config:  ``Object``  The application config for this layer.
     *  :returns: ``GeoExt.data.LayerRecord``
     *
     *  Create a layer record given the config.
     */
    createLayerRecord:function (config) {
        var record;

        //create a vector layer based on config parameters
        var layer = new OpenLayers.Layer.Vector(config.name, {
            projection:"projection" in config ? config.projection : "EPSG:4326",
            visibility:"visibility" in config ? config.visibility : true,
            strategies:[this.fixed?new OpenLayers.Strategy.Fixed():new OpenLayers.Strategy.BBOX({resFactor:1,ratio:1})],
            protocol:new OpenLayers.Protocol.HTTP({
                url:this.url,
                params:config.params,
                format:this.getFormat(config)
            }),
            styleMap:this.getStyleMap(config)
        });


        //configure the popup balloons for feed items
        this.configureInfoPopup(layer);

        // create a layer record for this layer
        var Record = GeoExt.data.LayerRecord.create([
            //{name: "title", type: "string"},
            {name:"name", type:"string"},
            {name:"source", type:"string"},
            {name:"group", type:"string"},
            {name:"fixed", type:"boolean"},
            {name:"selected", type:"boolean"},
            {name:"visibility", type:"boolean"},
            {name:"format", type:"string"},
            {name:"defaultStyle"},
            {name:"selectStyle"},
            {name:"params"}
        ]);

        var formatConfig = "format" in config ? config.format : this.format;

        var data = {
            layer:layer,
            //title: config.name,
            name:config.name,
            source:config.source,
            group:config.group,
            fixed:("fixed" in config) ? config.fixed : false,
            selected:("selected" in config) ? config.selected : false,
            params:("params" in config) ? config.params : {},
            visibility:("visibility" in config) ? config.visibility : false,
            format: formatConfig instanceof String ? formatConfig : null,
            defaultStyle:("defaultStyle" in config) ? config.defaultStyle : {},
            selectStyle:("selectStyle" in config) ? config.selectStyle : {}
        };


        record = new Record(data, layer.id);
        return record;

    },


    /** api: method[getConfigForRecord]
     *  :arg record: :class:`GeoExt.data.LayerRecord`
     *  :returns: ``Object``
     *
     *  Create a config object that can be used to recreate the given record.
     */
    getConfigForRecord:function (record) {
        // get general config
        var config = gxp.plugins.FeedSource.superclass.getConfigForRecord.apply(this, arguments);
        // add config specific to this source
        return Ext.apply(config, {
            //title: record.get("name"),
            name:record.get("name"),
            group:record.get("group"),
            fixed:record.get("fixed"),
            selected:record.get("selected"),
            params:record.get("params"),
            visibility:record.getLayer().getVisibility(),
            format:record.get("format"),
            defaultStyle:record.getLayer().styleMap["styles"]["default"]["defaultStyle"],
            selectStyle:record.getLayer().styleMap["styles"]["select"]["defaultStyle"]
        });
    },

    /* api: method[getFormat]
     *  :arg config:  ``Object``  The application config for this layer.
     *  :returns: ``OpenLayers.Format``
     * Create an instance of the layer's format class and return it
     */
    getFormat:function (config) {
        // get class based on rssFormat in config
        var Class = window;
        var formatConfig = ("format" in config) ? config.format : this.format;

        if (typeof formatConfig == "string" || formatConfig instanceof String) {
            var parts = formatConfig.split(".");
            for (var i = 0, ii = parts.length; i < ii; ++i) {
                Class = Class[parts[i]];
                if (!Class) {
                    break;
                }
            }

            // TODO: consider static method on OL classes to construct instance with args
            if (Class && Class.prototype && Class.prototype.initialize) {

                // create a constructor for the given layer format
                var Constructor = function () {
                    // this only works for args that can be serialized as JSON
                    Class.prototype.initialize.apply(this);
                };
                Constructor.prototype = Class.prototype;

                // create a new layer given format
                var format = new Constructor();
                return format;
            }
        } else {
            return formatConfig;
        }
    },

    /* api: method[configureInfoPopup]
     *  :arg config:  ``Object``  The vector layer
     * Configure a popup to display information on selected feed item.
     */
    configureInfoPopup:function (layer) {
        var tpl = new Ext.XTemplate(this.popupTemplate);
        layer.events.on({
            "featureselected":function (featureObject) {
                var feature = featureObject.feature;
                var pos = feature.geometry;
                if (this.target.selectControl) {
                    if (this.target.selectControl.popup) {
                        this.target.selectControl.popup.close();
                    }
                    this.target.selectControl.popup = new GeoExt.Popup({
                        title:feature.attributes.title,
                        closeAction:'destroy',
                        location:feature,
                        html:tpl.apply(feature.attributes)
                    });
                    this.target.selectControl.popup.show();
                }
            },
            "featureunselected":function () {
                if (this.target.selectControl && this.target.selectControl.popup) {
                    this.target.selectControl.popup.close();
                }
            },
            scope:this
        });
    },

    /* api: method[getStyleMap]
     *  :arg config:  ``Object``  The application config for this layer.
     *  :returns: ``OpenLayers.StyleMap``
     * Return a style map containing default and select styles
     */
    getStyleMap:function (config) {
        return new OpenLayers.StyleMap({
            "default":new OpenLayers.Style("defaultStyle" in config ? config.defaultStyle : {graphicName:"circle", pointRadius:5, fillOpacity:0.7, fillColor:'Red'}, {title:config.name}),
            "select":new OpenLayers.Style("selectStyle" in config ? config.selectStyle : {graphicName:"circle", pointRadius:10, fillOpacity:1.0, fillColor:"Yellow"})
        });
    }

});
Ext.preg(gxp.plugins.FeedSource.prototype.ptype, gxp.plugins.FeedSource);
