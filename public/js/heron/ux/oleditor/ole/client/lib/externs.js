/**
 * @externs
 */

/**
 * @type {Object}
 */
var OpenLayers = {
    /**
     * @constructor
     */
    Bounds: function(){},
    /**
     * @constructor
     */
    Control: function(){},
    /**
     * @constructor
     */
    Map: function(){},
    Control: {
        EditorPanel: function(){}
    },
    /**
     * @constructor
     */
    Feature: {
        /**
         * @constructor
         * @param {OpenLayers.Geometry} geometry
         * @param {Object=} attributes
         * @param {Object=} style
         */
        Vector: function(geometry, attributes, style){}
    }
};

/**
 * @constructor
 */
OpenLayers.Geometry;

/**
 * @constructor
 * @extends OpenLayers.Geometry
 */
OpenLayers.Geometry.Point = function(a, b){};

/**
 * @constructor
 */
OpenLayers.Layer = function(){};

/**
 * @constructor
 * @param {String|string} name
 * @param {Object=} options
 */
OpenLayers.Layer.Vector = function(name, options){}

/**
 * @constructor
 */
OpenLayers.Strategy = function(){};