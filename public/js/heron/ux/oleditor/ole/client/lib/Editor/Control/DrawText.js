/**
 * @copyright  2011 geOps
 * @author     Just van den Broecke
 * @license    https://github.com/geops/ole/blob/master/license.txt
 * @link       https://github.com/geops/ole
 */

/**
 * Class: OpenLayers.Editor.Control.DrawText
 *
 * Draw a text label at selected point. Save label text as feature
 * attribute.
 *
 * Inherits from:
 *  - <OpenLayers.Editor.Control.DrawPoint>
 */
OpenLayers.Editor.Control.DrawText = OpenLayers.Class(OpenLayers.Editor.Control.DrawPoint, {

    title: null,
    featureType: 'text',
    defaultStyle: 'defaultLabel',
    selectStyle: 'selectLabel',
    modal: true,

    /**
     * Constructor: OpenLayers.Editor.Control.DrawPath
     * Create a new control for drawing points.
     *
     * Parameters:
     * @param {OpenLayers.Layer.Vector} layer - Points will be added to this layer.
     * @param options - {Object} An optional object whose properties will be used
     *     to extend the control.
     */
    initialize: function (layer, options) {
        OpenLayers.Editor.Control.DrawPoint.prototype.initialize.apply(this,
                [layer, OpenLayers.Handler.Point, options]);

        this.title = OpenLayers.i18n('oleDrawText');

        // Capture events to do text-label specific processing
        layer.events.on({
            'beforefeatureadded': this.onBeforeFeatureAdded,
            'featureselected': this.onFeatureSelected,
            'featureunselected': this.onFeatureUnselected,
            scope: this
        });
    },

    deactivate: function () {
        var deactivated = OpenLayers.Control.Button.prototype.deactivate.call(this);
        if (this.popup && this.popup.feature) {
            var feature = this.popup.feature;
            // remove and destroy Popup
            if (feature.layer) {
                feature.layer.map.removePopup(feature.popup);
                feature.layer.removeFeatures([feature]);
            }
            feature.popup.destroy();
            feature.popup = null;

            this.popup = null;
        }
    },

    /**
     * Assign text to feature and redraw.
     *
     * Parameters:
     * feature - {<OpenLayers.Feature.Vector>} the Vector feature.
     * text - {String} the label's text string.
     */
    setLabelText: function (feature, text) {
        // Assign Popup-text to feature attribute 'label'
        feature.attributes.label = text;

        // Redraw single feature
        feature.layer.drawFeature(feature, this.defaultStyle);
    },

    /**
     * Remove and destroy Popup for this feature.
     *
     * Parameters:
     * feature - {<OpenLayers.Feature.Vector>} the Vector feature.
     */
    removePopup: function (feature) {
        this.popup = null;

        if (!feature.popup) {
            return;
        }
        // remove and destroy Popup
        feature.layer.map.removePopup(feature.popup);
        feature.popup.destroy();
        feature.popup = null;

        // End modality
        var editControl = feature.editControl;
        if (editControl) {
            editControl.activate();
        }
    },

    /**
     * Callback when Popup closed.
     *
     * Parameters:
     * evt - {Object} event object (unused)
     */
    onPopupClose: function (evt) {
        this.popup = null;

        // 'this' is the Popup.
        var feature = this.feature;
        if (!feature) {
            return false;
        }

        // 'self' is the DrawText Control.
        var self = feature.editControl;
        if (!self) {
            return false;
        }

        // Input element should contain label text
        var labelInput = document.getElementById('olLabelInput');

        // Assign Popup-text to feature attribute 'label' and close Popup
        self.setLabelText(feature, labelInput.value);
        self.removePopup(feature);

        // Feature does not exist without text: delete in that case
        if (!labelInput.value) {
            feature.layer.removeFeatures([feature]);
        }
    },

    /**
     * Is this a Text Feature?.
     *
     * Parameters:
     * feature - {<OpenLayers.Feature.Vector>} the Vector feature.
     */
    isTextFeature: function (feature) {
        return feature ? feature.featureType === 'text' : false;
    },

    /**
     * Callback when Feature selected.
     *
     * Parameters:
     * evt - {Object} event object with Feature
     */
    onFeatureSelected: function (evt) {
        var feature = evt.feature;
        if (this.isTextFeature(feature)) {
            feature.layer.drawFeature(feature, this.selectStyle);
        }
    },

    /**
     * Callback when Feature deselected.
     *
     * Parameters:
     * evt - {Object} event object with Feature
     */
    onFeatureUnselected: function (evt) {
        var feature = evt.feature;
        if (this.isTextFeature(feature)) {
            feature.layer.drawFeature(feature, this.defaultStyle);
        }
    },

    /**
     * Callback when Feature added.
     *
     * Parameters:
     * evt - {Object} event object with Feature
     */
    onBeforeFeatureAdded: function (evt) {
        var feature = evt.feature;
        if (!this.isTextFeature(feature)) {
            // Feature may be added from file upload, the featureType wil be Point
            // Promote to text-feature
            if (feature.attributes.label) {
                feature.editControl = this;
                feature.featureType = 'text';
                feature.layer.drawFeature(feature, this.defaultStyle);
            }
            return true;
        }

        // Create standard OL Popup to add/edit text
        var popup = new OpenLayers.Popup.FramedCloud("featurePopup",
                feature.geometry.getBounds().getCenterLonLat(),
                new OpenLayers.Size(100, 100),
                '<div class="oleDrawTextPopup"><input type="text" size="32" id="olLabelInput"/>' +
                        '<p>' + OpenLayers.i18n('oleDrawTextEdit') + '</p>' +
                        '</div>',
                null, true, this.onPopupClose);

        feature.popup = popup;
        feature.editControl = this;
        popup.feature = feature;

        // Show popup
        this.layer.map.addPopup(popup, true);

        // Modality: do not allow other labels to be drawn
        this.deactivate();

        // Close and assign on pressing return-key
        var labelInput = document.getElementById('olLabelInput');
        var self = this;
        labelInput.onkeypress = function (event) {
            var keyCode = window.event ? window.event.keyCode : event.keyCode;
            if (keyCode == 13) {
                self.setLabelText(feature, labelInput.value);
                self.removePopup(feature);
            }
        };

        labelInput.focus();
        this.popup = popup;
    },

    CLASS_NAME: 'OpenLayers.Editor.Control.DrawText'
});