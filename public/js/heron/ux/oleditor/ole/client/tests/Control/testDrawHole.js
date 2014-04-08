
module("Draw Hole");

test("test DrawHole constructor and method", 4, function() {

    var editor = new OpenLayers.Editor();
    var drawHole = new OpenLayers.Editor.Control.DrawHole(editor.editLayer);
    var polygon = wkt.read("POLYGON((616116.08175159 263772.07601661,617811.09507479 265542.42326529,619449.60795389 263772.07601661,617886.42900027 262190.06358162,616116.08175159 263772.07601661))");
    var noHole = wkt.read("POLYGON((616153.74871433 265617.75719076,615588.74427326 264958.58534285,616342.08352802 264468.91482726,616888.25448772 265128.08667517,616153.74871433 265617.75719076))");
    var hole = wkt.read("POLYGON((617716.92766795 264355.91393905,617208.42367099 263790.90949798,618037.09685122 263112.9041687,618545.60084818 263696.74209114,617716.92766795 264355.91393905))");
    var polygonWithHole = wkt.read("POLYGON((616116.08175159 263772.07601661,617811.09507479 265542.42326529,619449.60795389 263772.07601661,617886.42900027 262190.06358162,616116.08175159 263772.07601661),(617716.92766795 264355.91393905,617208.42367099 263790.90949798,618037.09685122 263112.9041687,618545.60084818 263696.74209114,617716.92766795 264355.91393905))");

    editor.map.addControl(drawHole);

    ok(drawHole instanceof OpenLayers.Editor.Control.DrawHole,
        "new drawHole returns OpenLayers.Editor.Control.DrawHole object.");

    ok(drawHole.map instanceof OpenLayers.Map,
        "drawHole.map returns OpenLayers.Map object.");

    editor.editLayer.destroyFeatures();
    editor.editLayer.addFeatures(polygon);
    drawHole.drawFeature(noHole.geometry);
    equals(wkt.write(editor.editLayer.features[0]), wkt.write(polygon),
        "draw noHole");

    drawHole.drawFeature(hole.geometry);
    equals(wkt.write(editor.editLayer.features[0]), wkt.write(polygonWithHole),
        "draw hole");

});