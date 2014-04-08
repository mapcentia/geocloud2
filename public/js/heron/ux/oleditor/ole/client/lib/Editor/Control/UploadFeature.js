/**
 * @copyright  2011 geOps
 * @author     Just van den Broecke
 * @license    https://github.com/geops/ole/blob/master/license.txt
 * @link       https://github.com/geops/ole
 */

/**
 * Class: OpenLayers.Editor.Control.UploadFeature
 *
 * Provide uploading of Features from a local file and add these to the current drawing on the Map.
 * No server-side storage is involved, though a small server-side script is required that will echo
 * back an uploaded file. This response is captured into an hidden IFrame, a well-known technique.
 * See also: http://viralpatel.net/blogs/2008/11/ajax-style-file-uploading-using-hidden-iframe.html.
 *
 * Inherits from:
 *  - <OpenLayers.Control.Button>
 */
OpenLayers.Editor.Control.UploadFeature = OpenLayers.Class(OpenLayers.Control, {

    EVENT_TYPES: ["featureuploaded"],

    layer: null,

    title: OpenLayers.i18n('oleUploadFeature'),

    /**
     * APIProperty: url
     * {String} URL of server script to upload to,
     *         see example: https://code.google.com/p/geoext-viewer/source/browse/trunk/heron/services/heron.cgi.
     */
    url: '',

    /**
     * APIProperty: params
     * {Object} parameters to be passed to server (see url) in POST body
     */
    params: {
        action: 'upload',
        mime: 'text/html',
        encoding: 'escape'
    },

    /**
     * APIProperty: formats
     * {Array} array of supported formats, each format is object with name, extension, mime and formatter classname.
     */
    formats: [
        {name: 'GeoJSON', fileExt: '.json', mimeType: 'text/plain', formatter: 'OpenLayers.Format.GeoJSON'}
    ],

    /**
     * APIProperty: fileProjection
     * {<OpenLayers.Projection>} projection of features in file, for custom projections use Proj4.js.
     */
    fileProjection: null,

    /**
     * APIProperty: visibleOnUpload
     * Ensure that the Layer is visible when uploaded.
     */
    visibleOnUpload: true,

    /** OpenLayers.Format objects, to be populated from formats, non-API property. */
    formatters: {},

    /** Should all objects be replaced on upload ? */
    replaceFeatures: false,

    /**
     * Constructor: OpenLayers.Editor.Control.MergeFeature
     * Create a new control for merging features.
     *
     * Parameters:
     * layer - {<OpenLayers.Layer.Vector>}
     * options - {Object} An optional object whose properties will be used
     *     to extend the control.
     */
    initialize: function (layer, options) {
        // The editable layer
        this.layer = layer;

        this.title = OpenLayers.i18n('oleUploadFeature');

        OpenLayers.Control.Button.prototype.initialize.apply(this, [options]);
    },

    activate: function () {
        var activated = OpenLayers.Control.prototype.activate.call(this);
        if (activated) {
            this.openDialog();
        }
        return activated;
    },

    deactivate: function () {
        var deactivated = OpenLayers.Control.prototype.deactivate.call(this);
        if (deactivated) {
            this.cleanUp(this);
        }
        return deactivated;
    },

    /**
     * Clean up .
     */
    cleanUp: function (self) {
        this.getDialog().hide();
        if (!self.iframe) {
            return;
        }
        self.removeIFrame();
    },

    /**
     * Creates and pops up file chooser Dialog.
     */
    delayedOpenDialog: function () {
        var self = this;
        setTimeout(function () {
            self.openDialog();
        }, 200);

    },

    /**
     * Get the dialog object.
     * We may use the upload control outside the Editor.
     */
    getDialog: function () {
        if (this.map && this.map.editor && this.map.editor.dialog) {
            return this.map.editor.dialog;
        }
        if (!this.dialog) {
            this.dialog = new OpenLayers.Editor.Control.Dialog();
            this.map.addControl(this.dialog);
        }
        return this.dialog;
    },

    /**
     * Creates and pops up file chooser Dialog.
     */
    openDialog: function () {
        // this.cleanUp(this);

        // Create content div for Dialog
        var content = document.createElement("div");

        content.appendChild(document.createElement("p"));

        // Create upload Form
        var formElm = document.createElement("form");
        formElm.setAttribute('id', 'upload_form');
        formElm.setAttribute('method', "POST");
        formElm.setAttribute('action', this.url);
        formElm.setAttribute("target", "upload_iframe");
        formElm.setAttribute("enctype", "multipart/form-data");
        formElm.setAttribute("encoding", "multipart/form-data");

        // Input element for selecting local file
        formElm = this.createInputElm('oleFile', 'file', 'file', null, formElm);

        /* Create select element for file format options */
        var select = document.createElement("select");
        select.setAttribute("name", "format_select");
        select.setAttribute("id", "format_select");

        // Add file format options
        var option;
        for (var i = 0; i < this.formats.length; ++i) {
            option = document.createElement("option");

            // Some formatters are already objects
            // we cannot store these in elements, so save in map.
            // we use formatter's name in lookup in this.formatters map
            var format = this.formats[i];
            var formatter = format.formatter;
            if (typeof formatter == 'string') {
                formatter = eval('new ' + formatter + '()');
            }

            formatter.fileProjection = format.fileProjection;

            this.formatters[format.name] = formatter;

            // Set formatter's name as value

            option.setAttribute("value", format.name);
            option.innerHTML = format.name;
            option.fileExt = format.fileExt;
            option.mimeType = format.mimeType;

            // Extra optional format-specific params to be submitted to server script
            option.params = {};
            if (format.targetSrs) {
                // Have server reproject (i.s.o. OL)
                option.params.target_srs = format.targetSrs;
            }
            if (format.sourceSrs) {
                option.params.source_srs = format.sourceSrs;
            }
            select.appendChild(option);
        }

        // Add selector to Form
        formElm.appendChild(select);

        // Checkbox, if all features in Layer need to be replaced
        var replaceFeatures = document.createElement('p');
        var replaceFeaturesCB = document.createElement('input');
        replaceFeaturesCB.type = 'checkbox';
        replaceFeaturesCB.id = 'oleUploadFeatureReplace';
        replaceFeaturesCB.name = 'uploadFeatureReplace';
        replaceFeaturesCB.value = 'true';
        replaceFeaturesCB.checked = true;
        // The following IE is required due to a bug in IE (below version 8) to tick the checkbox prior inserting it into the DOM
        replaceFeaturesCB.defaultChecked = true;
        this.setReplaceFeatures(replaceFeaturesCB.checked);
        replaceFeatures.appendChild(replaceFeaturesCB);

        OpenLayers.Event.observe(replaceFeatures, 'click', OpenLayers.Function.bind(function (event) {
            // Prevent propagation of event to drawing controls
            OpenLayers.Event.stop(event, true);

            this.setReplaceFeatures(replaceFeaturesCB.checked);
        }, this));

        var element = document.createElement('label');
        element.htmlFor = 'oleUploadFeatureReplace';
        element.appendChild(document.createTextNode(OpenLayers.i18n('oleUploadFeatureReplace')));
        replaceFeatures.appendChild(element);
        formElm.appendChild(replaceFeatures);

        // Additional user-defined hidden elements
        for (var param in this.params) {
            formElm = this.createInputElm(null, param, 'hidden', this.params[param], formElm);
        }

        // Add Form to content
        content.appendChild(formElm);

        this.showUploadDialog(content);

        // Set format selector on selected format if file chosen based on file extension
        var self = this;
        document.getElementById('oleFile').onchange = function (e) {
            var fileName = self.fileInputValue(this);
            if (fileName) {
                var fnameArr = fileName.split('.');
                if (fnameArr.length > 1) {
                    var ext = '.' + fnameArr[fnameArr.length - 1].toLowerCase();
                    // Find option with .ext file extension
                    var selectElm = document.getElementById('format_select');
                    var options = selectElm.options;
                    for (var i = 0; i < options.length; ++i) {
                        if (ext == options[i].fileExt) {
                            selectElm.selectedIndex = i;
                            break;
                        }
                    }
                }
            }
        };

    },

    /**
     * Show message via Dialog.
     */
    showMessage: function (content, close) {
        this.getDialog().show({
            title: OpenLayers.i18n('oleUploadFeature'),
            content: content,
            close: close
        });
    },

    /**
     * Show file upload Dialog.
     * @param {!HTMLElement} content - HTML (form) content for the Dialog
     */
    showUploadDialog: function (content) {
        // Show the upload Dialog. We do Form-submit ourselves.
        this.getDialog().show({
            title: OpenLayers.i18n('oleUploadFeature'),
            content: content,
            save: OpenLayers.Function.bind(this.uploadFeature, this),
            cancel: OpenLayers.Function.bind(this.cancel, this),
            saveButtonText: 'oleDialogOkButton',
            noHideOnSave: true
        });
    },

    /**
     * Create empty hidden IFrame to receive uploaded features echoed from server.
     * @param {string} name - Name of element
     * @return {!Element}
     */
    createIFrame: function (name) {
        // Create the hidden empty iframe in which the response (file contents) will be received
        var iframe = document.createElement("iframe");
        iframe.setAttribute("id", name);
        iframe.setAttribute("name", name);
        iframe.setAttribute("width", "0");
        iframe.setAttribute("height", "0");
        iframe.setAttribute("border", "0");
        iframe.setAttribute("style", "width: 0; height: 0; border: none;");
        iframe.src = 'about:blank';
        return iframe;
    },

    /**
     * removes IFrame if existing.
     */
    removeIFrame: function () {
        var iframe = document.getElementById('upload_iframe');
        if (iframe) {
            iframe.parentNode.removeChild(iframe);
        }
        this.iframe = null;
    },

    /**
     * Cancel callback from Dialog.
     */
    cancel: function () {
        this.deactivate();
    },

    /**
     * Parse response (file contents) text into feature array.
     */
    parseFeatures: function (featuresText) {
        // Get selected format option
        var selectElm = document.getElementById('format_select');
        var option = selectElm.options[selectElm.selectedIndex];

        // Create a formatter from selected option
        // Either a string or an object
        var formatter = this.formatters[option.value];
        if (!formatter) {
            return null;
        }

        /* Need to reproject if projection in file is different from Layer */
        if (this.fileProjection) {
            formatter.internalProjection = this.layer.map.baseLayer.projection;
            formatter.externalProjection = formatter.fileProjection ? formatter.fileProjection : this.fileProjection;
        }

        var features = formatter.read(featuresText);
        if (!features || features.length < 1) {
            return null;
        }

        // We have features, determine extent of feature collection
        var bounds;
        if (features.constructor != Array) {
            // May be single feature
            features = [features];
        }
        for (var i = 0; i < features.length; ++i) {
            if (!bounds) {
                bounds = features[i].geometry.getBounds();
            } else {
                bounds.extend(features[i].geometry.getBounds());
            }
        }

        this.bounds = bounds;
        return features;
    },

    uploadFeature: function () {
        // At least one file needs to be specified
        var fileInputElm = document.getElementById('oleFile');
        if (!this.fileInputValue(fileInputElm)) {
            this.cleanUp(this);
            this.showMessage(OpenLayers.i18n('oleUploadFeatureNoFile'), OpenLayers.Function.bind(this.delayedOpenDialog, this));
            return;
        }

        // Create hidden IFrame to receive uploaded data and add to DOM.
        this.iframe = this.createIFrame('upload_iframe');
        document.body.appendChild(this.iframe);

        // Add event handler to handle response in IFrame
        var self = this;
        var iframe = self.iframe;
        var eventHandler = function () {

            if (iframe.detachEvent) {
                iframe.detachEvent("onload", eventHandler);
            } else {
                iframe.removeEventListener("load", eventHandler, false);
            }

            // Fetch response from server from iframe
            var content;
            if (iframe.contentDocument) {
                content = iframe.contentDocument.body.innerHTML;
            } else if (iframe.contentWindow) {
                content = iframe.contentWindow.document.body.innerHTML;
            } else if (iframe.document) {
                content = iframe.document.body.innerHTML;
            }

            // We normally use HTML-escaping, needed otherwise the iframe will garble GML (converting tags to lowercase)
            if (content && self.params.encoding == 'escape') {
                content = content.replace(/&quot;/g, '"')
                        .replace(/&gt;/g, '>')
                        .replace(/&lt;/g, '<')
                        .replace(/&amp;/g, '&')
            }

            var features = self.parseFeatures(content);

            if (self.replaceFeatures) {
                self.layer.destroyFeatures();
            }

            if (!features) {
                self.deactivate();
                self.showMessage(OpenLayers.i18n('oleUploadFeatureNone'));
                return;
            }

            // At least one feature present: add to map and zoom to extent
            self.layer.addFeatures(features);
            self.layer.map.zoomToExtent(self.bounds);

            if (self.visibleOnUpload) {
                self.layer.setVisibility(true);
            }

            // Del the iframe...
            setTimeout(function () {
                self.deactivate();
            }, 250);
        };

        if (iframe.addEventListener) {
            iframe.addEventListener("load", eventHandler, true);
        }
        if (iframe.attachEvent) {
            iframe.attachEvent("onload", eventHandler);
        }
        var form = document.getElementById('upload_form');

        // Optional per-format parameters to be included in form
        var selectElm = document.getElementById('format_select');
        var option = selectElm.options[selectElm.selectedIndex];
        for (var param in option.params) {
            form = this.createInputElm(null, param, 'hidden', option.params[param], form);
        }

        form.submit();
    },

    /**
     * Instantiates an Input Element for Form
     * @param {string} id Name of element
     * @param {string} name Name of element
     * @param {string} type Type of element, e.g. text or hidden
     * @param {string} value optional initial Value for element
     * @param {!HTMLElement} parentElm optional parent Element
     * @return {!HTMLElement}
     */
    createInputElm: function (id, name, type, value, parentElm) {
        var inputElm = document.createElement('input');
        inputElm.setAttribute('type', type);
        inputElm.id = id;
        inputElm.name = name;
        inputElm.value = value ? value : null;
        if (parentElm) {
            parentElm.appendChild(inputElm);
        }
        return parentElm;
    },

    /**
     * Get the filename value from a File Input element
     * @param {!HTMLElement} fileElm file input Element
     * @return {string}
     */
    fileInputValue: function (fileElm) {
        if (fileElm.files) {
            return fileElm.files.length == 1 ? fileElm.files[0].name : null;
        } else {
            return fileElm.value;
        }
    },

    /**
     * Shows or hides guides
     * @param {Boolean} guidesVisible Set to TRUE to show guides
     */
    setReplaceFeatures: function (replace) {
        this.replaceFeatures = replace;
    },

    CLASS_NAME: "OpenLayers.Editor.Control.UploadFeature"
});
