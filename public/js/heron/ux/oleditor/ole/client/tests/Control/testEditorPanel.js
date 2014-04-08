
module("Editor Panel");

test("test Editor Panel constructor", 2, function() {

    var editor = new OpenLayers.Editor();
    var editorPanel = editor.editorPanel;

    ok(editorPanel instanceof OpenLayers.Editor.Control.EditorPanel,
        "new editorPanel returns OpenLayers.Editor.Control.EditorPanel object.");

    ok(editorPanel.map instanceof OpenLayers.Map,
        "editorPanel.map returns OpenLayers.Map object.");

});