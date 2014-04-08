
module("Clean Feature");

asyncTest("test CleanFeature constructor and method", function() {

    var result = "";
    var editor = new OpenLayers.Editor(null,{dialog: {show: function (content) {result = content }}});
    var cleanFeature = new OpenLayers.Editor.Control.CleanFeature(editor.editLayer, {url: url+'clean'});
    var dirty = wkt.read("POLYGON((618790.43610598 265184.58711928,623253.97119041 265203.42060065,618865.77003145 262905.73587364,623574.14037368 262792.73498543,618790.43610598 265184.58711928))");
    var clean = wkt.read("GEOMETRYCOLLECTION(POLYGON((618790.43610598 265184.58711928,623253.97119041 265203.42060065,621055.270819361 264052.16976258956,618790.43610598 265184.58711928)),POLYGON((621055.270819361 264052.16976258956,623574.14037368 262792.73498543,618865.77003145 262905.73587364,621055.270819361 264052.16976258956)))");

    editor.map.addControl(cleanFeature);

    ok(cleanFeature instanceof OpenLayers.Editor.Control.CleanFeature,
        "new cleanFeature returns OpenLayers.Editor.Control.CleanFeature object.");

    ok(cleanFeature.map instanceof OpenLayers.Map,
        "cleanFeature.map returns OpenLayers.Map object.");

    cleanFeature.cleanFeature();
    equals(result, "oleCleanFeatureSelectFeature",
        "cleanFeature without selected features");

    editor.editLayer.addFeatures([dirty]);
    editor.editLayer.selectedFeatures.push(dirty);
    cleanFeature.cleanFeature();
    setTimeout(function(){
        equals(wkt.write(editor.editLayer.features), wkt.write(clean),
            "cleanFeature with selected features");
        start();
    }, 1000);

});