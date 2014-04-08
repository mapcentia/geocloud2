/**
 * @copyright  2011 geOps
 * @author     Just van den Broecke
 * @license    https://github.com/geops/ole/blob/master/license.txt
 * @link       https://github.com/geops/ole
 */

/**
 * Class: OpenLayers.Editor.Control.DeleteAllFeatures
 * The DeleteAllFeatures provides a button to delete all features
 *     from a given layer.
 *
 * Inherits from:
 *  - <OpenLayers.Control.Button>
 */
OpenLayers.Editor.Control.DeleteAllFeatures = OpenLayers.Class(OpenLayers.Control.Button, {

    /**
     * Property: layer
     * {<OpenLayers.Layer.Vector>}
     */
    layer: null,

    title: OpenLayers.i18n('oleDeleteAllFeatures'),

    /**
     * Constructor: OpenLayers.Editor.Control.DeleteAllFeatures
     * Create a new control for deleting all features from given layer.
     *
     * Parameters:
     * layer - {<OpenLayers.Layer.Vector>} The layer from which selected
     *     features will be deleted.
     * options - {Object} An optional object whose properties will be used
     *     to extend the control.
     */
    initialize: function (layer, options) {

        this.layer = layer;

        this.title = OpenLayers.i18n('oleDeleteAllFeatures');

        OpenLayers.Control.Button.prototype.initialize.apply(this, [options]);

        this.trigger = this.deleteAllFeatures;

        this.displayClass = "oleControlEnabled " + this.displayClass;

    },

    /**
     * Method: deleteAllFeatures
     */
    deleteAllFeatures: function () {
        if (this.layer.features.length > 0) {
            this.layer.destroyFeatures();
        }
        if (this.map.editor.editLayer) {
            this.map.editor.editLayer.destroyFeatures();
        }
    },

    CLASS_NAME: 'OpenLayers.Editor.Control.DeleteAllFeatures'
});