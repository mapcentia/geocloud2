Ext.namespace("Heron.data");

/** api: (define)
 *  module = Heron.data
 *  class = DataExporter
 *  base_link = `Ext.DomHelper <http://docs.sencha.com/ext-js/3-4/#!/api/Ext.DomHelper>`_
 */

/**
 * Define functions to help with data export.
 */
Heron.data.DataExporter = {

    /** Format data using Ext.ux.Exporter. */
    formatStore: function (store, config) {
        var formatter = new Ext.ux.Exporter[config.formatter]();
        var data = formatter.format(store, config);
        if (config.encoding && config.encoding == 'base64') {
            data = Base64.encode(data);
        }
        return data;
    },

    /** Trigger file download by submitting data to a server script. */
    download: function (data, config) {
        // See also http://www.sencha.com/forum/showthread.php?81897-FYI-Very-simple-approach-to-JS-triggered-file-downloads
        try {
            // Cleanup previous form if required
            Ext.destroy(Ext.get('hr_uploadForm'));
        }
        catch (e) {
        }

        var formFields = [
            {tag: 'input', type: 'hidden', name: 'data', value: data},
            // Server sends HTTP Header: Content-Disposition: attachment; filename="%s"' % filename
            {tag: 'input', type: 'hidden', name: 'filename', value: config.fileName},
            {tag: 'input', type: 'hidden', name: 'mime', value: config.mimeType}
        ];

        if (config.format) {
            // Format is an OL Formatter object like OpenLayers.Format.WKT  or OpenLayers.Format.GML.v2  or a String class name
            var format = config.format instanceof OpenLayers.Format ?  config.format.CLASS_NAME.split(".") : config.format.split(".");
            format = format.length == 4 ? format[2] : format.pop();
            formFields.push({tag: 'input', type: 'hidden', name: 'source_format', value: format});
        }
        if (config.encoding) {
            formFields.push({tag: 'input', type: 'hidden', name: 'encoding', value: config.encoding});
        }
        if (config.targetFormat) {
            formFields.push({tag: 'input', type: 'hidden', name: 'target_format', value: config.targetFormat});
        }
        if (config.assignSrs) {
            formFields.push({tag: 'input', type: 'hidden', name: 'assign_srs', value: config.assignSrs});
            formFields.push({tag: 'input', type: 'hidden', name: 'source_srs', value: config.assignSrs});
        }
        if (config.sourceSrs) {
            formFields.push({tag: 'input', type: 'hidden', name: 'source_srs', value: config.sourceSrs});
        }
        if (config.targetSrs) {
            formFields.push({tag: 'input', type: 'hidden', name: 'target_srs', value: config.targetSrs});
        }

        var form = Ext.DomHelper.append(
                document.body,
                {
                    tag: 'form',
                    id: 'hr_uploadForm',
                    method: 'post',
                    /** Heron CGI URL, see /services/heron.cgi. */
                    action: Heron.globals.serviceUrl,
                    children: formFields
                }
        );

        // Add Form to document and submit
        document.body.appendChild(form);
        form.submit();
    },

    /** Trigger file download by requesting data as attachment via hidden iframe (mainly GeoServer). */
    directDownload: function (url) {
        // See also http://www.sencha.com/forum/showthread.php?81897-FYI-Very-simple-approach-to-JS-triggered-file-downloads
        try {
            // Cleanup previous iframe if required
            Ext.destroy(Ext.get('hr_directdownload'));
        }
        catch (e) {
        }

        // Create hidden iframe, this prevents that the browser opens a new window/document.
        var iframe = Ext.DomHelper.append(
                document.body,
                {
                    tag: 'iframe',
                    id: 'hr_directdownload',
                    name: 'hr_directdownload',
                    width: '0px',
                    height: '0px',
                    border: '0px',
                    style: 'width: 0; height: 0; border: none;',
                    src: url
                }
        );

        // Add iframe to document and submit
        document.body.appendChild(iframe);
    }
};
