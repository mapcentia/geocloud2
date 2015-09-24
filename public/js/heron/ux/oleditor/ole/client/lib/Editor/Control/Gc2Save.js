OpenLayers.Editor.Control.Gc2Save = OpenLayers.Class(OpenLayers.Control.Button, {
    layer: null,
    title: OpenLayers.i18n('oleImportFeature'),
    initialize: function (layer, options) {
        this.layer = layer;
        OpenLayers.Control.Button.prototype.initialize.apply(this, [options]);
        this.trigger = this.save;
        this.title = OpenLayers.i18n('Save drawing to disk');
    },
    save: function () {
        var features = this.layer.features,
            wkt = new OpenLayers.Format.WKT(),
            wktStr = wkt.write(features);
        $.post(host + "/controllers/drawing",
            wktStr,
            function (data, status) {
                //alert("Data: " + data + "\nStatus: " + status);
            },
            "text"
        );
    },
    CLASS_NAME: 'OpenLayers.Editor.Control.Gc2Save'
});