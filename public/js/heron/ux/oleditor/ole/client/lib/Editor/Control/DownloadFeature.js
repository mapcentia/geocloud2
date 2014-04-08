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
 * @copyright  2011 geOps
 * @author     Just van den Broecke
 * @license    https://github.com/geops/ole/blob/master/license.txt
 * @link       https://github.com/geops/ole
 */

/**
 * Class: OpenLayers.Editor.Control.DownloadFeature
 *
 * Allow downloading of Features drawn on Map with Editor to user's local disk.
 * As downloading into a file from the browser's DOM is tricky. Not all browsers, notably
 * IE will allow downloading via encoded data. Also solutions involving Flash and HTML5 local storage
 * have browser-isses. Hence we POST the encoded geometry data to a very small server side script that
 * merely echoes back the POSTed content, thereby setting the Content-Disposition: attachment; HTTP header.
 * This triggers a true download within the browser (a file chooser popup).   For an example of such a script see:
 * https://code.google.com/p/geoext-viewer/source/browse/trunk/heron/services/heron.cgi
 *
 * Inherits from:
 *  - <OpenLayers.Control.Button>
 */
OpenLayers.Editor.Control.DownloadFeature = OpenLayers.Class(OpenLayers.Control, {

    EVENT_TYPES: ["featuredownloaded"],

    layer: null,

    title: OpenLayers.i18n('oleDownloadFeature'),

    /**
     * APIProperty: url
     * {String} URL of server script to force download from via attachment,
     *         see example: https://code.google.com/p/geoext-viewer/source/browse/trunk/heron/services/heron.cgi.
     */
    url: '',

    /**
     * APIProperty: params
     * {Object} parameters to be passed to server (see url) in POST body
     */
    params: {
        action: 'download',
        mime: 'text/plain',
        filename: 'editor',
        encoding: 'none'
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

    formatters: {},

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

        this.layer = layer;

        this.title = OpenLayers.i18n('oleDownloadFeature');

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
            if (this.dialog) {
                this.dialog.hide();
                this.dialog = null;
            }
        }
        return deactivated;
    },

    /**
     * Cancel file download Dialog.
     */
    cancelDialog: function () {
        this.dialog = null;
        this.deactivate();
    },

    /**
     * Open popup Dialog, for selecting download options like format.
     */
    openDialog: function () {
        // No use proceeding if Layer is empty
        if (!this.layer.features || this.layer.features.length <= 0) {
            // Show popup Dialog when no features available
            this.showMessage(OpenLayers.i18n('oleDownloadFeatureEmpty'));
            this.deactivate();
            return;
        }

        // Create content for the popup Dialog
        var content = document.createElement("div");

        var text = document.createElement("p");
        text.innerHTML = OpenLayers.i18n('oleDownloadFeatureFileFormat');
        content.appendChild(text);

        // Create  Form to trigger server-initiated download
        var formElm = document.createElement("form");
        formElm.setAttribute('id', 'download_form');
        formElm.setAttribute('method', "POST");
        formElm.setAttribute('action', this.url);

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

            // Format may have fixed projection, e.g. EPSG:4326 for KML and GPX
            formatter.fileProjection = format.fileProjection;
            this.formatters[format.name] = formatter;

            option.setAttribute("value", format.name);
            option.innerHTML = format.name;
            option.fileExt = format.fileExt;
            option.mimeType = format.mimeType;
            option.sourceFormat = formatter.CLASS_NAME.split(".")[2];
            option.targetFormat = format.targetFormat;
            option.assignSrs = format.fileProjection ? format.fileProjection.getCode() : undefined;
            option.sourceSrs = format.sourceSrs ? option.sourceSrs : option.assignSrs;
            option.targetSrs = format.targetSrs;

            select.appendChild(option);
        }
        formElm.appendChild(select);

        // Add Form to content
        content.appendChild(formElm);

        // Show popup Dialog
        this.dialog = this.map.editor.dialog;
        this.dialog.show({
            title: OpenLayers.i18n('oleDownloadFeature'),
            content: content,
            save: OpenLayers.Function.bind(this.downloadFeature, this),
            cancel: OpenLayers.Function.bind(this.cancelDialog, this),
            noHideOnSave: true
        });
    },

    /**
     * Feature data upload that triggers file download (callback from Dialog).
     */
    downloadFeature: function () {
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

        // For some formats like GML it is better to have SRS (projection) encoded
        formatter.srsName = formatter.externalProjection ? formatter.externalProjection.getCode() : this.layer.map.projection;

        // Convert features to chosen format in String
        var data = formatter.write(this.layer.features);

        // Populate the upload Form further using Format option values
        var formElm = document.getElementById('download_form');

        var filenameBase = this.params['filename'];
        this.params['filename'] = filenameBase + option.fileExt;
        this.params['mime'] = option.mimeType;
        this.params['data'] = data;
        this.params['source_format'] = option.sourceFormat;

        // Optional server-side conversion, use GeoJSON to format and upload
        // Server can convert to OGR-formats like ESRI Shapefile (zipped) with file projection.
        // Example: {name: 'ESRI Shapefile (zipped, WGS84)', fileExt: '.zip', mimeType: 'application/zip',
        //   formatter: 'OpenLayers.Format.GeoJSON', targetFormat: 'ESRI Shapefile',
        //   fileProjection: new OpenLayers.Projection('EPSG:4326')}
        this.params['target_format'] = option.targetFormat;
        this.params['assign_srs'] = option.assignSrs;
        this.params['source_srs'] = option.sourceSrs;
        this.params['target_srs'] = option.targetSrs;

        // Additional user-defined hidden elements from params
        for (var param in this.params) {
            if (!this.params[param]) {
                continue;
            }
            formElm = this.createInputElm(param, 'hidden', this.params[param], formElm);
        }

        // Restore file basename
        this.params['filename'] = filenameBase;

        // Submit the data: forces download of file
        // this.map.editor.startWaiting(this.panel_div);
        formElm.submit();

        // Cleans popup
        var self = this;
        setTimeout(function () {
            self.deactivate();
        }, 2000);
    },

    /**
     * Show message via Dialog.
     */
    showMessage: function (content) {
        this.map.editor.dialog.show({
            title: OpenLayers.i18n('oleDownloadFeature'),
            content: content
        });
    },

    /**
     * Instantiates an Input Element for Form
     * @param {string} name Name of element
     * @param {string} type Type of element, e.g. text or hidden
     * @param {string} value optional initial Value for element
     * @param {HTMLElement} parentElm optional parent Element
     * @return {!HTMLElement|null}
     */
    createInputElm: function (name, type, value, parentElm) {
        var inputElm = document.createElement('input');
        inputElm.setAttribute('type', type);
        inputElm.name = name;
        inputElm.value = value ? value : null;
        if (parentElm) {
            parentElm.appendChild(inputElm);
        }
        return parentElm;
    },
    CLASS_NAME: "OpenLayers.Editor.Control.DownloadFeature"
});
