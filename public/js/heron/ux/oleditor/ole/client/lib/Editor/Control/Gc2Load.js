OpenLayers.Editor.Control.Gc2Load = OpenLayers.Class(OpenLayers.Control.Button, {
    layer: null,
    initialize: function (layer, options) {
        this.layer = layer;
        OpenLayers.Control.Button.prototype.initialize.apply(this, [options]);
        this.trigger = this.load;
        this.title = OpenLayers.i18n('Load drawings from disk');
    },
    load: function () {
        var wkt = new OpenLayers.Format.WKT(),
            vLayer = this.layer,
            get = function () {
                $.get(host + "/controllers/drawing",
                    function (data, status) {
                        vLayer.addFeatures(wkt.read(data.data));
                        MapCentia.gc2.map.zoomToExtent(vLayer.getDataExtent(),false);
                    },
                    "json"
                );
            };
        if (vLayer.features.length > 0) {
            if (confirm("Are you sure? Any unsaved drawings will be lost!")) {
                vLayer.destroyFeatures();
                get();

            } else {
                return false;
            }
        } else {
            get();
        }
    },
    CLASS_NAME: 'OpenLayers.Editor.Control.Gc2Load'
});