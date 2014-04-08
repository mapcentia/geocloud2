/**
 * Copyright (c) 2008-2011 The Open Planning Project
 *
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */


/**
 * @requires plugins/FeedSource.js
 *
 */

/** api: (define)
 *  module = gxp.plugins
 *  class = YouTubeFeedSource
 */

/** api: (extends)
 *  plugins/FeedSource.js
 */

Ext.namespace("gxp.plugins");

gxp.plugins.YouTubeFeedSource = Ext.extend(gxp.plugins.FeedSource, {

    /** api: ptype = gxp_youtubesource */
    ptype: "gxp_youtubesource",

    /** api: url [String]
     * The URL for the YouTube GeoRSS feed
     * **/
    url: "http://gdata.youtube.com/feeds/api/videos?v=2&prettyprint=true&",

    /**api: format [String]
     * The default format to use for YouTube features
     */
    format: "OpenLayers.Format.YouTube",

    /** api:title [String]
     * Title for source
     **/
    title: 'Youtube Videos',

    /** api:pointRadius [Number]
     * Size of thumbnails
     **/
    pointRadius: 24,

    /** api:popupTemplate [String]
     * Template for specifying HTML contents of popup
     **/
    popupTemplate:  '<tpl for="."><a target="_blank" href="{link}"><img height="180"  width="240" title="{title}" src="{thumbnail}"/></a></tpl>',

    /** api: config[fixed]
     * ``Boolean`` Use OpenLayers.Strategy.Fixed if true, BBOX if false
     **/    
    fixed: false,    
    
    /**
     * Create a YouTube layer record
     * @param config
     * @return layerRecord
     */
    createLayerRecord:function (config) {

        if (Ext.isEmpty(config.params["max-results"])) {
            config.params["max-results"] = 50;
        } else {
            //Youtube doesn't accept more than 50 results
            config.params["max-results"] = Math.min(config.params["max-results"], 50);
        }

        config.url = this.url;

        this.format =  new OpenLayers.Format.GeoRSS({
            // adds the thumbnail attribute to the feature
            createFeatureFromItem: function(item) {
                var feature = OpenLayers.Format.GeoRSS.prototype.createFeatureFromItem.apply(this, arguments);
                feature.attributes.thumbnail = this.getElementsByTagNameNS(item, "http://search.yahoo.com/mrss/", "thumbnail")[4].getAttribute("url");
                feature.attributes.content = OpenLayers.Util.getXmlNodeValue(this.getElementsByTagNameNS(item, "*", "summary")[0]);
                return feature;
            }
        });

        var record = gxp.plugins.YouTubeFeedSource.superclass.createLayerRecord.apply(this, arguments);

        var layer = record.getLayer();

        layer.protocol.filterToParams =  function(filter, params) {
            if (filter.type === OpenLayers.Filter.Spatial.BBOX) {
                var bounds =  filter.value;
                var location = bounds.getCenterLonLat();
                //calculate the location-radius to use
                var R = 6378.1370;
                var PI = 3.1415926;
                var leftBounds = R * (bounds.left) / 180.0 / PI;
                var rightBounds = R * (bounds.right) / 180.0 / PI;
                var radius = Math.min((rightBounds - leftBounds) / 2 * 2, 1000);
                Ext.apply(params, {
                    "location":"" + location.lat + "," + location.lon,
                    "location-radius":radius + "km"
                });
            }
            return params;
        }

        return record;
    },

    /**
     * Create a popup based on the YouTube feature attributes
     * @param layer
     */
    configureInfoPopup:function (layer) {
        var tpl = new Ext.XTemplate(this.popupTemplate);
        layer.events.on({
            "featureselected":function (featureObject) {
                var feature = featureObject.feature;
                var pos = feature.geometry;

                if (this.target.selectControl.popup != null) {
                    this.target.selectControl.popup.close();
                }

                this.target.selectControl.popup = new GeoExt.Popup({
                    title: feature.attributes.title,
                    location : feature,
                    width: 240,
                    height: 220,
                    closeAction: 'destroy',
                    html: tpl.apply(feature.attributes)
                });
                this.target.selectControl.popup.show();
            },

            "featureunselected":function (featureObject) {
                if (this.target.selectControl && this.target.selectControl.popup) {
                    this.target.selectControl.popup.close();
                }
            },
            scope:this
        });
    },

    /**
     * Create an OpenLayers.StyleMap based on configuration parameters
     * @param config
     * @return {OpenLayers.StyleMap}
     */
    getStyleMap:function (config) {
        return new OpenLayers.StyleMap({
            "default":new OpenLayers.Style(
                {externalGraphic:"${thumbnail}", pointRadius:24},
                {title: this.title}),
            "select":new OpenLayers.Style({pointRadius:this.pointRadius+5})
        });
    }

});

Ext.preg(gxp.plugins.YouTubeFeedSource.prototype.ptype, gxp.plugins.YouTubeFeedSource);