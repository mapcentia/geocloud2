module("Transform Feature");

test("BBox strategies get temporarily disabled", function() {
    var bboxStrategy = new OpenLayers.Strategy.BBOX();
    var otherStrategy = new OpenLayers.Strategy.Refresh();
    var bboxLayer = new OpenLayers.Layer.Vector("Test Polygons", {
        strategies: [
            bboxStrategy,
            otherStrategy
        ],
        projection : "EPSG:4326",
        visibility: true
    });
    var editor = new OpenLayers.Editor(null,{
        activeControls: ['Navigation'],
        editLayer: bboxLayer
    });
    var transformFeature = new OpenLayers.Editor.Control.TransformFeature(editor.editLayer);
    editor.addEditorControl(transformFeature);
    
    ok(bboxStrategy.active, "BBox strategy initially enabled");
    ok(otherStrategy.active, "Other strategy initially enabled");
    transformFeature.activate();
    ok(bboxStrategy.active===false, "BBox strategy gets disabled");
    ok(otherStrategy.active, "Other strategy does not get disabled");
    transformFeature.deactivate();
    ok(bboxStrategy.active, "BBox strategy gets enabled");
});

test("No present strategies handled correctly", function() {
    var bboxLayer = new OpenLayers.Layer.Vector("Test Polygons", {
        projection : "EPSG:4326",
        visibility: true
    });
    var editor = new OpenLayers.Editor(null,{
        activeControls: ['Navigation'],
        editLayer: bboxLayer
    });
    var transformFeature = new OpenLayers.Editor.Control.TransformFeature(editor.editLayer);
    editor.addEditorControl(transformFeature);
    
    transformFeature.activate();
    transformFeature.deactivate();
});