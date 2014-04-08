/**
 * @copyright  2011 geOps
 * @license    https://github.com/geops/ole/blob/master/license.txt
 * @link       https://github.com/geops/ole
 */

/**
 * @requires Editor/Control/EditorPanel.js
 */

/**
 * Class: OpenLayers.Editor
 * The OpenLayers Editor provides basic methods and informations for map editing.
 *     Highlevel functions are implemented in different controls and can be
 *     activated by the editor constructor.
 *
 * @constructor
 * @param {OpenLayers.Map} map
 * @param {Object=} options
 */
OpenLayers.Editor = OpenLayers.Class({

    /**
     * Property: map
     * {<OpenLayers.Map>} this gets set in the constructor.
     */
    map: null,

    /**
     * Property: id
     * {String} Unique identifier for the Editor.
     */
    id: null,

    /**
     * Property: editLayer
     * {<OpenLayers.Layer.Vector>} Editor workspace.
     */
    editLayer: null,

    /**
     * Property: editorPanel
     * {<OpenLayers.Editor.Control.EditorPanel>} Contains icons for active controls
     *     and gets set by startEditMode() and unset by stopEditMode().
     */
    editorPanel: null,

    /**
     * Property: editMode
     * {Boolean} The editor is active.
     */
    editMode: false,

    /**
     * Property: dialog
     * {<OpenLayers.Editor.Control.Dialog>} ...
     */
    dialog: null,

    /**
     * Property: status
     * @type {function(string, string)} Function to display states, receives status type and message
     */
    showStatus: function (status, message) {
        if (status === 'error') {
            alert(message);
        }
    },

    /**
     * Property: activeControls
     * {Array} ...
     */
    activeControls: [],

    /**
     * Property: editorControls
     * {Array} Contains names of all available editor controls. In particular
     *   this information is needed by this EditorPanel.
     */
    editorControls: ['CleanFeature', 'DeleteFeature', 'DeleteAllFeatures', 'Dialog', 'DrawHole', 'DrawRegular',
        'DrawPolygon', 'DrawPath', 'DrawPoint', 'DrawText', 'EditorPanel', 'ImportFeature',
        'MergeFeature', 'SnappingSettings', 'SplitFeature', 'CADTools',
        'TransformFeature'],

    /**
     * Geometry types available for editing
     * {Array}
     */
    featureTypes: ['text', 'point', 'path', 'polygon', 'regular'],

    /**
     * Property: sourceLayers
     * {Array} ...
     */
    sourceLayers: [],

    /**
     * Property: parameters
     * {Object} ...
     */
    params: {},

    geoJSON: new OpenLayers.Format.GeoJSON(),

    /**
     * Property: options
     * {Object} ...
     */
    options: {},

    /**
     * Property: URL of processing service.
     * {String}
     */
    oleUrl: '',

    /**
     * Instantiated controls
     * {Objects}
     */
    controls: {},

    /**
     * Property: undoRedoActive
     * {Boolean} Indicates if the UndoRedo control is active. Only read on
     *     initialization right now. Default is true.
     */
    undoRedoActive: true,

    /**
     * @param {OpenLayers.Map} map A map that shall be equipped with an editor; can be left undefined in which case a map is created.
     * @param {Object} options
     */
    initialize: function (map, options) {

        OpenLayers.Util.extend(this, options);

        if (map instanceof OpenLayers.Map) {
            this.map = map;
        } else {
            this.map = new OpenLayers.Map();
        }

        if (!options) {
            options = {};
        }

        if (!options.dialog) {
            this.dialog = new OpenLayers.Editor.Control.Dialog();
            this.map.addControl(this.dialog);
        }

        this.id = OpenLayers.Util.createUniqueID('OpenLayers.Editor_');

        if (options.editLayer) {
            this.editLayer = options.editLayer
        } else {
            this.editLayer = new OpenLayers.Layer.Vector('Editor', {
                displayInLayerSwitcher: false
            });
        }
        if (options.styleMap) {
            this.editLayer.styleMap = options.styleMap;
        } else {
            this.editLayer.styleMap = new OpenLayers.StyleMap({
                'default': new OpenLayers.Style({
                    fillColor: '#07f',
                    fillOpacity: 0.8,
                    strokeColor: '#037',
                    strokeWidth: 2,
                    graphicZIndex: 1,
                    pointRadius: 5
                }),
                // defaultLabel and selectLabel Styles are needed for DrawText Control
                'defaultLabel': new OpenLayers.Style({
                    fillColor: '#07f',
                    fillOpacity: 0.8,
                    strokeColor: '#037',
                    strokeWidth: 2,
                    graphicZIndex: 11,
                    pointRadius: 0,
                    cursor: 'default',
                    label: '${label}',
                    fontColor: '#000000',
                    fontSize: "11px",
                    fontFamily: "Verdana, Arial, Helvetica, sans-serif",
                    fontWeight: "bold",
//					labelAlign: "cm",
//					labelXOffset: 0,
//					labelYOffset: 0,
                    labelOutlineColor: '#FFFFFF',
                    labelOutlineWidth: 4,
                    labelSelect: true
                }),
                'select': new OpenLayers.Style({
                    fillColor: '#fc0',
                    strokeColor: '#f70',
                    graphicZIndex: 2
                }),
                // defaultLabel and selectLabel Styles are needed for DrawText Control
                'selectLabel': new OpenLayers.Style({
                    pointRadius: 5,
                    label: '${label}',
                    fontColor: 'black',
                    fontSize: "11px",
                    fontFamily: "Verdana, Arial, Helvetica, sans-serif",
                    fontWeight: "bold",
                    labelAlign: "cm",
                    labelXOffset: "${xOffset}",
                    labelYOffset: "${yOffset}",
                    fillColor: '#fc0',
                    strokeColor: '#f70',
                    labelOutlineColor: '#fc0',
                    labelOutlineWidth: 6,
                    graphicZIndex: 2
                }),
                'temporary': new OpenLayers.Style({
                    fillColor: '#fc0',
                    fillOpacity: 0.8,
                    strokeColor: '#f70',
                    strokeWidth: 2,
                    graphicZIndex: 2,
                    pointRadius: 5
                })
            });
        }

        var selectionContext = {
            editor: this,
            layer: this.editLayer,
            controls: [
                'OpenLayers.Editor.Control.DeleteFeature',
                'OpenLayers.Editor.Control.CleanFeature',
                'OpenLayers.Editor.Control.MergeFeature',
                'OpenLayers.Editor.Control.SplitFeature'
            ]};
        this.editLayer.events.register('featureselected', selectionContext, this.selectionChanged);
        this.editLayer.events.register('featureunselected', selectionContext, this.selectionChanged);

        for (var i = 0, il = this.featureTypes.length; i < il; i++) {
            if (this.featureTypes[i] == 'polygon') {
                this.activeControls.push('DrawPolygon');
            }
            else if (this.featureTypes[i] == 'path') {
                this.activeControls.push('DrawPath');
            }
            else if (this.featureTypes[i] == 'point') {
                this.activeControls.push('DrawPoint');
            }
            else if (this.featureTypes[i] == 'regular') {
                this.activeControls.push('DrawRegular');
            }
            else if (this.featureTypes[i] == 'text') {
                this.activeControls.push('DrawText');
            }
        }

        for (var i = 0, il = this.sourceLayers.length; i < il; i++) {
            var selectionContext = {
                editor: this,
                layer: this.sourceLayers[i],
                controls: ['OpenLayers.Editor.Control.ImportFeature']
            };
            this.sourceLayers[i].events.register('featureselected', selectionContext, this.selectionChanged);
            this.sourceLayers[i].events.register('featureunselected', selectionContext, this.selectionChanged);
            this.sourceLayers[i].styleMap = new OpenLayers.StyleMap({
                'default': new OpenLayers.Style({
                    fillColor: '#0c0',
                    fillOpacity: 0.8,
                    strokeColor: '#070',
                    strokeWidth: 2,
                    graphicZIndex: 1,
                    pointRadius: 5
                }),
                'select': new OpenLayers.Style({
                    fillColor: '#fc0',
                    strokeColor: '#f70',
                    graphicZIndex: 2
                }),
                'temporary': new OpenLayers.Style({
                    fillColor: '#fc0',
                    fillOpacity: 0.8,
                    strokeColor: '#f70',
                    strokeWidth: 2,
                    graphicZIndex: 2,
                    pointRadius: 5
                })
            });
            this.map.addLayer(this.sourceLayers[i]);
        }

        this.map.editor = this;
        this.map.addLayer(this.editLayer);
        this.map.addControl(new OpenLayers.Editor.Control.LayerSettings(this));

        if (this.undoRedoActive) {
            this.map.addControl(new OpenLayers.Editor.Control.UndoRedo(this.editLayer));
        }

        this.addEditorControls();

        return this;
    },

    /**
     * Enable or disable controls that depend on selected features.
     *
     * Requires an active SelectFeature control and the following context variables:
     * - editor: this
     * - layer: The layer with selected features.
     * - controls: An array of class names.
     */
    selectionChanged: function () {

        var selectFeature = this.editor.editorPanel.getControlsByClass('OpenLayers.Control.SelectFeature')[0];

        if (this.layer.selectedFeatures.length > 0 && selectFeature && selectFeature.active) {
            // enable controls
            for (var ic = 0, lic = this.controls.length; ic < lic; ic++) {
                var control = this.editor.editorPanel.getControlsByClass(this.controls[ic])[0];
                if (control) {
                    OpenLayers.Element.removeClass(control.panel_div, 'oleControlDisabled');
                }
            }
        } else {
            // disable controls
            for (var ic = 0, lic = this.controls.length; ic < lic; ic++) {
                var control = this.editor.editorPanel.getControlsByClass(this.controls[ic])[0];
                if (control) {
                    OpenLayers.Element.addClass(control.panel_div, 'oleControlDisabled');
                }
            }
        }

        this.editor.editorPanel.redraw();
    },

    /**
     * Makes the toolbar appear and allows editing
     */
    startEditMode: function () {
        this.editMode = true;
        this.editorPanel.activate();
    },

    /**
     * Hides the toolbar and prevents editing
     */
    stopEditMode: function () {
        this.editMode = false;
        this.editorPanel.deactivate();
    },

    /**
     * Initializes configured controls and shows them
     */
    addEditorControls: function () {
        var control = null, controls = [];
        var editor = this;

        for (var i = 0, len = editor.activeControls.length; i < len; i++) {
            control = editor.activeControls[i];

            if (OpenLayers.Util.indexOf(editor.editorControls, control) > -1) {
                controls.push(new OpenLayers.Editor.Control[control](
                        editor.editLayer, editor.options[control]
                ));
            }

            switch (control) {

                case 'Separator':
                    controls.push(new OpenLayers.Control.Button({
                        displayClass: 'olControlSeparator'
                    }));
                    break;

                case 'Navigation':
                    controls.push(new OpenLayers.Control.Navigation(
                            OpenLayers.Util.extend(
                                    {title: OpenLayers.i18n('oleNavigation')},
                                    editor.options.Navigation)
                    ));
                    break;

                case 'DragFeature':
                    controls.push(new OpenLayers.Editor.Control.DragFeature(editor.editLayer,
                            OpenLayers.Util.extend({}, editor.options.DragFeature)
                    ));
                    break;

                case 'ModifyFeature':
                    controls.push(new OpenLayers.Control.ModifyFeature(editor.editLayer,
                            OpenLayers.Util.extend(
                                    {title: OpenLayers.i18n('oleModifyFeature')},
                                    editor.options.ModifyFeature)
                    ));
                    break;

                case 'SelectFeature':
                    controls.push(new OpenLayers.Control.SelectFeature(
                            editor.sourceLayers.concat([editor.editLayer]),
                            OpenLayers.Util.extend(
                                    {
                                        title: OpenLayers.i18n('oleSelectFeature'),
                                        clickout: true,
                                        toggle: false,
                                        multiple: false,
                                        hover: false,
                                        toggleKey: "ctrlKey",
                                        multipleKey: "ctrlKey",
                                        box: true
                                    },
                                    editor.options.SelectFeature)
                    ));
                    break;

                case 'DownloadFeature':
                    controls.push(new OpenLayers.Editor.Control.DownloadFeature(editor.editLayer,
                            OpenLayers.Util.extend({}, this.DownloadFeature)
                    ));
                    break;

                case 'UploadFeature':
                    controls.push(new OpenLayers.Editor.Control.UploadFeature(editor.editLayer,
                            OpenLayers.Util.extend({}, this.UploadFeature)
                    ));
                    break;

                default:
                    // Allow externally-defined controls, developers are responsible for providing an
                    // OpenLayers.Editor.Control[control] class and corresponding CSS.
                    // See FeatureStyler-example: lib.heron-mc.org/heron/latest/examples/vectorstyler
                    // (would otherwise require Heron/GXP/GeoExt).
                    if (OpenLayers.Util.indexOf(editor.editorControls, control) == -1 && OpenLayers.Editor.Control[control]) {
                        controls.push(new OpenLayers.Editor.Control[control](editor.editLayer,
                                OpenLayers.Util.extend({}, editor.options[control])
                        ));
                    }
                    break;
            }

            // Save instance in editor's controls mapping
            this.controls[control] = controls[controls.length - 1];
        }

        // Add toolbar to map
        this.editorPanel = this.createEditorPanel(controls);
        editor.map.addControl(this.editorPanel);
    },

    /**
     * Adds a control to the editor and its panel
     * @param {OpenLayers.Editor.Control} control
     */
    addEditorControl: function (control) {
        this.controls[control.CLASS_NAME] = control;
        this.editorPanel.addControls([control]);
        this.map.addControl(control);
    },

    /**
     * Instantiates the container which displays the tools.
     * To be called by OLE only and intended to be overridden by subclasses that want to display something else instead of the default toolbar
     * @param {Array.<OpenLayers.Control>} controls Editing controls
     * @return {OpenLayers.Editor.Control.EditorPanel} Widget to display editing tools
     */
    createEditorPanel: function (controls) {
        var editorPanel = new OpenLayers.Editor.Control.EditorPanel(this);
        editorPanel.addControls(controls);
        return editorPanel;
    },

    status: function (options) {
        if (options.type == 'error') {
            alert(options.content);
        }
    },

    /**
     * Destroys existing features and loads the provided one into editor
     * @param {Array.<OpenLayers.Feature.Vector>} features
     */
    loadFeatures: function (features) {
        this.editLayer.destroyFeatures();
        if (features) {
            this.editLayer.addFeatures(features);
            this.map.zoomToExtent(this.editLayer.getDataExtent());
        }
    },

    /**
     * Callback to update selected feature with result of server side processing
     */
    requestComplete: function (response) {
        var responseJSON = new OpenLayers.Format.JSON().read(response.responseText);
        this.map.editor.stopWaiting();
        if (!responseJSON) {
            this.showStatus('error', OpenLayers.i18n('oleNoJSON'))
        } else if (responseJSON.error) {
            this.showStatus('error', responseJSON.message)
        } else {
            if (responseJSON.params) {
                OpenLayers.Util.extend(this.params, responseJSON.params);
            }
            if (responseJSON.geo) {
                var geo = this.geoJSON.read(responseJSON.geo);
                this.editLayer.removeFeatures(this.editLayer.selectedFeatures);
                this.editLayer.addFeatures(this.toFeatures(geo));
                this.editLayer.events.triggerEvent('featureselected');
            }
        }
    },

    /**
     * Flattens multipolygons and returns a list of their features
     * @param {Object|Array} multiPolygon Geometry or list of geometries to flatten. Geometries can be of types
     *     OpenLayers.Geometry.MultiPolygon, OpenLayers.Geometry.Collection,
     *     OpenLayers.Geometry.Polygon.
     * @return {Array} List for features of type OpenLayers.Feature.Vector.
     */
    toFeatures: function (multiPolygon) {
        if (multiPolygon === null || typeof(multiPolygon) !== 'object') {
            throw new Error('Parameter does not match expected type.');
        }
        var features = [];
        if (!(multiPolygon instanceof Array)) {
            multiPolygon = [multiPolygon];
        }
        for (var i = 0, li = multiPolygon.length; i < li; i++) {
            if (multiPolygon[i].geometry.CLASS_NAME === 'OpenLayers.Geometry.MultiPolygon' ||
                    multiPolygon[i].geometry.CLASS_NAME === 'OpenLayers.Geometry.Collection') {
                for (var j = 0, lj = multiPolygon[i].geometry.components.length; j < lj; j++) {
                    features.push(new OpenLayers.Feature.Vector(
                            multiPolygon[i].geometry.components[j]
                    ));
                }
            } else if (multiPolygon[i].geometry.CLASS_NAME === 'OpenLayers.Geometry.Polygon') {
                features.push(new OpenLayers.Feature.Vector(multiPolygon[i].geometry));
            }
        }
        return features;
    },

    toMultiPolygon: function (features) {
        var components = [];
        for (var i = 0, l = features.length; i < l; i++) {
            if (features[i].geometry.CLASS_NAME === 'OpenLayers.Geometry.Polygon') {
                components.push(features[i].geometry);
            }
        }
        return new OpenLayers.Geometry.MultiPolygon(components);
    },

    startWaiting: function (panel_div) {
        OpenLayers.Element.addClass(panel_div, 'olEditorWaiting');
        OpenLayers.Element.addClass(this.map.div, 'olEditorWaiting');
        this.waitingDiv = panel_div;
    },

    stopWaiting: function () {
        OpenLayers.Element.removeClass(this.waitingDiv, 'olEditorWaiting');
        OpenLayers.Element.removeClass(this.map.div, 'olEditorWaiting');
    },

    CLASS_NAME: 'OpenLayers.Editor'
});

/**
 * @constructor
 */
OpenLayers.Editor.Control = OpenLayers.Class(OpenLayers.Control, {

    initialize: function (options) {
        OpenLayers.Control.prototype.initialize.apply(this, [options]);
    },

    CLASS_NAME: 'OpenLayers.Editor.Control'
});

/**
 * Version number of OpenLayers Editor.
 * @const
 * @type {string}
 */
OpenLayers.Editor.VERSION_NUMBER="1.0-beta4";
