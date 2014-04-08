
module("Draw Polygon");

test("test Polygon constructor and method", 3, function() {

    var editor = new OpenLayers.Editor();
    var drawPolygon = new OpenLayers.Editor.Control.DrawPolygon(editor.editLayer);
    var polygon = wkt.read("POLYGON((616116.08175159 263772.07601661,617811.09507479 265542.42326529,619449.60795389 263772.07601661,617886.42900027 262190.06358162,616116.08175159 263772.07601661))");

    editor.map.addControl(drawPolygon);

    ok(drawPolygon instanceof OpenLayers.Editor.Control.DrawPolygon,
        "new drawPolygon returns OpenLayers.Editor.Control.DrawPolygon object.");

    ok(drawPolygon.map instanceof OpenLayers.Map,
        "drawPolygon.map returns OpenLayers.Map object.");

    editor.editLayer.destroyFeatures();
    drawPolygon.drawFeature(polygon.geometry);
    equals(wkt.write(editor.editLayer.features[0]), wkt.write(polygon),
        "draw Polygon");

});