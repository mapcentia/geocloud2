/**
 * Copyright (c) 2008-2011 The Open Planning Project
 *
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires plugins/FeedSource.js
 */

/** api: (define)
 *  module = gxp.plugins
 *  class = PicasaFeedSource
 */

/** api: (extends)
 *  plugins/FeedSource.js
 */

Ext.namespace("gxp.plugins");

gxp.plugins.PicasaFeedSource = Ext.extend(gxp.plugins.FeedSource, {

    /** api: ptype = gxp_picasasource */
    ptype: "gxp_picasasource",


    /** api: url [String]
     * The URL of the Picasa feed
     */
    url: "http://picasaweb.google.com/data/feed/base/all?thumbsize=160c&",

    /** api:format [String]
     *  The default feature format for the feed source
     */
    format: "OpenLayers.Format.Picasa",

    /** api:title [String]
     * Title for source
     **/
    title: 'Picasa Photos',

    /** api:pointRadius [Number]
     * Size of thumbnails
     **/
    pointRadius: 14,

    /** api:popupTemplate [String]
     * Template for specifying HTML contents of popup
     **/
    popupTemplate:  '<tpl for="."><a target="_blank" href="{link}"><img  title="{title}" src="{thumbnail}"/></a></tpl>',

    /** api: config[fixed]
     * ``Boolean`` Use OpenLayers.Strategy.Fixed if true, BBOX if false
     **/    
    fixed: false,
    
    /**
     * Create a Picasa layer record
     * @param config
     * @return GeoExt.data.LayerRecord
     */
    createLayerRecord: function(config) {
        if (Ext.isEmpty(config.params["max-results"])) {
            config.params["max-results"] = 50;
        }
        config.url = this.url;


        this.format =  new OpenLayers.Format.GeoRSS({
            // adds the thumbnail attribute to the feature
            createFeatureFromItem: function(item) {
                var feature = OpenLayers.Format.GeoRSS.prototype.createFeatureFromItem.apply(this, arguments);
                feature.attributes.thumbnail = this.getElementsByTagNameNS(item, "http://search.yahoo.com/mrss/", "thumbnail")[0].getAttribute("url");
                feature.attributes.content = OpenLayers.Util.getXmlNodeValue(this.getElementsByTagNameNS(item, "*","summary")[0]);
                return feature;
            }
        });

        var record = gxp.plugins.PicasaFeedSource.superclass.createLayerRecord.apply(this, arguments);

        var layer = record.getLayer();

        //Picasa will not return results if bounds are invalid
        layer.protocol.filterToParams =  function(filter, params) {
            if (filter.type === OpenLayers.Filter.Spatial.BBOX) {
                var bbox =  filter.value.toArray();
                params.bbox = [Math.max(-180,bbox[0]), Math.max(-90, bbox[1]), Math.min(180, bbox[2]), Math.min(90, bbox[3]) ];
            }
            return params;
        }

        return record;
    },

    /**
     * Configure popup to display larger Picasa thumbnail
     * @param layer
     */
    configureInfoPopup: function(layer) {
        var tpl = new Ext.XTemplate(this.popupTemplate);
        layer.events.on({
            "featureselected": function(featureObject) {
                var feature = featureObject.feature;
                var pos = feature.geometry;

                if (this.target.selectControl.popup != null) {
                    this.target.selectControl.popup.close();
                }

                var content = document.createElement("div");
                content.innerHTML = feature.attributes.content;

                var popupFeature = {
                    "link": content.getElementsByTagName('a')[0].getAttribute('href'),
                    "title": feature.attributes.title,
                    "thumbnail": feature.attributes.thumbnail
                };

                this.target.selectControl.popup = new GeoExt.Popup({
                    title: feature.attributes.title,
                    closeAction: 'destroy',
                    location : feature,
                    width: 175,
                    height: 200,
                    html: tpl.apply(popupFeature)
                });
                this.target.selectControl.popup.show();
            },

            "featureunselected" : function(featureObject) {
                if (this.target.selectControl && this.target.selectControl.popup) {
                    this.target.selectControl.popup.close();
                }
            },
            scope: this
        });
    },

    getStyleMap: function(config) {
        return new OpenLayers.StyleMap({
            "default": new OpenLayers.Style(
                {externalGraphic: "${thumbnail}", pointRadius: this.pointRadius},
                {title: this.title}),
            "select": new OpenLayers.Style({pointRadius: this.pointRadius+5})
        });
    }

});

Ext.preg(gxp.plugins.PicasaFeedSource.prototype.ptype, gxp.plugins.PicasaFeedSource);