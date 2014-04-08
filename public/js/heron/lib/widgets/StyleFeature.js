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

/**
 * @copyright  2014 Just Objects B.V.
 * @author     Just van den Broecke
 * @license    https://geoext-viewer.googlecode.com/svn/trunk/license.txt
 * @link       http://heron-mc.org
 */

/**
 * Class: OpenLayers.Editor.Control.StyleFeature
 *
 * Allows styling of features via the (GXP) VectorStylesDialog in a popup Window. For an example of StyleFeature button:
 * http://lib.heron-mc.org/heron/latest/examples/vectorstyler
 * Note that this Class makes the most sense within the OpenLayers Editor (OLE). For styling Layers see LayerNodeMenuItem.
 *
 * Inherits from:
 *  - <OpenLayers.Control.Button>
 */
OpenLayers.Control.StyleFeature = OpenLayers.Class(OpenLayers.Control.Button, {

    layer: null,


    /**
     * Constructor: OpenLayers.Control.StyleFeature
     * Create a new OpenLayers control for styling features.
     *
     * Parameters:
     * layer - {<OpenLayers.Layer.Vector>}
     * options - {Object} An optional object whose properties will be used
     *     to extend the control.
     */
    initialize: function (layer, options) {

        this.layer = layer;

        this.options = options ? options : {};

        this.title = __('Change feature styles');

        OpenLayers.Control.Button.prototype.initialize.apply(this, [options]);

        this.trigger = this.toggleStyleEditor;

        this.displayClass = "oleControlEnabled " + this.displayClass;
    },

    toggleStyleEditor: function () {
        if (!gxp || !gxp.VectorStylesDialog) {
            Ext.Msg.alert(__('Warning'), __('Vector Layer style editing requires GXP with VectorStylesDialog'));
            return;
        }

        var layerRecord = Heron.App.getMapPanel().layers.getByLayer(this.layer);

        if (!this.styleEditor) {
            this.styleEditor = new Ext.Window({
                layout: 'auto',
                resizable: false,
                autoHeight: true,
                pageX: this.options.pageX ? this.options.pageX : 100,
                pageY: this.options.pageY ? this.options.pageY : 200,
                width: this.options.width ? this.options.width : 400,
                height: this.options.height ? this.options.height : undefined,
                // height: options.formHeight,
                closeAction: 'hide',
                title: __('Style Editor (Vector)'),
                items: [
                    gxp.VectorStylesDialog.createVectorStylerConfig(layerRecord)
                ]
            });
        }

        if (!this.styleEditor.isVisible()) {
            this.styleEditor.show();
        } else {
            this.styleEditor.hide();
        }
    },

    CLASS_NAME: "OpenLayers.Control.StyleFeature"
});

/** If the OLE (OpenLayers Editor) is loaded extend the Class, such that it can be integrated automatically. */
if (OpenLayers.Editor && OpenLayers.Editor.Control) {
    OpenLayers.Editor.Control.StyleFeature = OpenLayers.Class(OpenLayers.Control.StyleFeature, {
        /**
          * Constructor: OpenLayers.Editor.Control.StyleFeature
          * Create a new control for styling features.
          *
          * Parameters:
          * layer - {<OpenLayers.Layer.Vector>}
          * options - {Object} An optional object whose properties will be used
          *     to extend the control.
          */
         initialize: function (layer, options) {

            // Call baseclass constructor
            OpenLayers.Control.StyleFeature.prototype.initialize.apply(this, [layer, options]);

         },

        CLASS_NAME: "OpenLayers.Editor.Control.StyleFeature"

    });
}