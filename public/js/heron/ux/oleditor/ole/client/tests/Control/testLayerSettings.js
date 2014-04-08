
module("Layer Settings");

test("test Layer Settings constructor", 1, function() {

    var OLE = new OpenLayers.Editor();

    var layerSettings = OLE.map.getControlsByClass('OpenLayers.Editor.Control.LayerSettings')[0];

    ok(layerSettings instanceof OpenLayers.Editor.Control.LayerSettings,
        "new layerSettings returns OpenLayers.Editor.Control.LayerSettings object.");

});