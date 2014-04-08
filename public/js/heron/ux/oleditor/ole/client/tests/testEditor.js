
module("Editor");

test("test Editor constructor", 4, function() {

    var editor = new OpenLayers.Editor();

    ok(editor instanceof OpenLayers.Editor, "new editor returns OpenLayers.Editor object.");

    ok(editor.map instanceof OpenLayers.Map, "editor.map returns OpenLayers.Map object.");

    ok(editor.dialog instanceof OpenLayers.Editor.Control.Dialog, "editor.dialog returns OpenLayers.Editor.Control.Dialog object.");

    ok(editor.map.getControlsByClass('OpenLayers.Editor.Control.LayerSettings')[0] instanceof OpenLayers.Editor.Control.LayerSettings,
        "editor.map.controls contains OpenLayers.Editor.Control.LayerSettings object.");

});


test("test edit modes", 4, function() {

    var editor = new OpenLayers.Editor();

    equals(editor.editMode, false, "editMode" );

    editor.startEditMode();
    ok(editor.editMode, "started edit mode." );

    ok(editor.editorPanel instanceof OpenLayers.Editor.Control.EditorPanel, "editor.editorPanel returns OpenLayers.Editor.Control.EditorPanel object.");

    editor.stopEditMode();
    equals(editor.editMode, false, "stoped edit mode. editMode" );

});


test("test toMultiPolygon and toFeatures", 2, function() {

    var editor = new OpenLayers.Editor();

    var multiPolygon = wkt.read("MULTIPOLYGON(((620867.66739033 258915.13008619,620923.8234449 258935.22804256,620955.15261219 258848.92505343,620904.90772126 258827.64486432,620867.66739033 258915.13008619)),((623387.98007842 258955.81892266,623418.78268498 258949.9517595,623381.70426854 258903.45660237,623285.18267652 258906.98788013,623332.26637994 258966.4310557,623351.86501011 258962.69798329,623334.11963888 258996.69154629,623412.01863888 259045.90954629,623429.91463888 259012.51854629,623438.70563888 258996.06854629,623436.39063888 258994.23154629,623415.89990015 258980.87141875,623387.98007842 258955.81892266),(623384.647 259013.51475912,623385.23554629 258985.85308336,623415.83995352 258999.97819439,623407.01175913 259024.10859239,623384.647 259013.51475912)))");
    var features = [
        wkt.read("POLYGON((620867.66739033 258915.13008619,620923.8234449 258935.22804256,620955.15261219 258848.92505343,620904.90772126 258827.64486432,620867.66739033 258915.13008619,620867.66739033 258915.13008619))"),
        wkt.read("POLYGON((623387.98007842 258955.81892266,623418.78268498 258949.9517595,623381.70426854 258903.45660237,623285.18267652 258906.98788013,623332.26637994 258966.4310557,623351.86501011 258962.69798329,623334.11963888 258996.69154629,623412.01863888 259045.90954629,623429.91463888 259012.51854629,623438.70563888 258996.06854629,623436.39063888 258994.23154629,623415.89990015 258980.87141875,623387.98007842 258955.81892266),(623384.647 259013.51475912,623385.23554629 258985.85308336,623415.83995352 258999.97819439,623407.01175913 259024.10859239,623384.647 259013.51475912))"),
    ];

    var multiPolygonToFeatures = editor.toFeatures(multiPolygon);
    var featuresToMultiPolygon = editor.toMultiPolygon(features);

    equals(wkt.write(multiPolygonToFeatures), wkt.write(features), "toFeatures");

    equals(featuresToMultiPolygon.toString(), wkt.write(multiPolygon), "toMultiPolygon");

});