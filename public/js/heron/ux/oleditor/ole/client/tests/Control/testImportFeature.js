
module("Import Feature");

test("test ImportFeature constructor and methods", 5, function() {

    var result,
        sourceLayer = new OpenLayers.Layer.Vector("Source Layer"),
        sourceFeature = wkt.read("POLYGON((620867.66739033 258915.13008619,620923.8234449 258935.22804256,620955.15261219 258848.92505343,620904.90772126 258827.64486432,620867.66739033 258915.13008619))"),
        OLE = new OpenLayers.Editor(null, {
        showStatus: function(type, message) {result = message},
        activeControls: ['ImportFeature']
    });
    OLE.startEditMode();
    sourceLayer.addFeatures([sourceFeature]);

    var importFeature = OLE.editorPanel.getControlsByClass('OpenLayers.Editor.Control.ImportFeature')[0];

    ok(importFeature instanceof OpenLayers.Editor.Control.ImportFeature,
        "new importFeature returns OpenLayers.Editor.Control.ImportFeature object.");

    ok(importFeature.map instanceof OpenLayers.Map,
        "importFeature.map returns OpenLayers.Map object.");

    importFeature.importFeature();
    equals(result, "oleImportFeatureSourceLayer",
        "importFeature without selected import layer");

    OLE.sourceLayers = [sourceLayer];
    importFeature.importFeature();
    equals(result, "oleImportFeatureSourceFeature",
        "importFeature with selected import layer but without selected feature");

    sourceLayer.selectedFeatures.push(sourceFeature);
    importFeature.importFeature();
    equals(wkt.write(OLE.editLayer.features[0]), wkt.write(sourceFeature),
        "importFeature");

});