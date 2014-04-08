DISTRIBUTION_NAME = ole-1.0

# Minifies OLE using Closure Compiler
minified:
	java -jar closurecompiler.jar \
	--charset 'UTF-8' \
	--jscomp_error=checkDebuggerStatement \
	--jscomp_warning=checkVars \
	--jscomp_warning=deprecated \
	--jscomp_warning=duplicate \
	--jscomp_warning=invalidCasts \
	--externs lib/externs.js \
	--js 'lib/compat.js' \
	--js 'lib/Editor.js' \
	--js 'lib/Editor/Control/CleanFeature.js' \
	--js 'lib/Editor/Control/DragFeature.js' \
	--js 'lib/Editor/Control/DeleteFeature.js' \
	--js 'lib/Editor/Control/Dialog.js' \
	--js 'lib/Editor/Control/DrawHole.js' \
	--js 'lib/Editor/Control/DrawPolygon.js' \
	--js 'lib/Editor/Control/DrawPath.js' \
	--js 'lib/Editor/Control/DrawPoint.js' \
    --js 'lib/Editor/Control/DrawRegular.js' \
	--js 'lib/Editor/Control/DeleteAllFeatures.js' \
    --js 'lib/Editor/Control/DownloadFeature.js' \
    --js 'lib/Editor/Control/DrawText.js' \
    --js 'lib/Editor/Control/UploadFeature.js' \
	--js 'lib/Editor/Control/EditorPanel.js' \
	--js 'lib/Editor/Control/ImportFeature.js' \
	--js 'lib/Editor/Control/LayerSettings.js' \
	--js 'lib/Editor/Control/MergeFeature.js' \
	--js 'lib/Editor/Control/TransformFeature.js' \
	--js 'lib/Editor/Control/FixedAngleDrawing.js' \
	--js 'lib/Editor/Layer.js' \
	--js 'lib/Editor/Layer/Snapping.js' \
	--js 'lib/Editor/Control/SnappingSettings.js' \
	--js 'lib/Editor/Control/SplitFeature.js' \
	--js 'lib/Editor/Control/UndoRedo.js' \
	--js 'lib/Editor/Control/CADTools.js' \
	--js 'lib/Editor/Control/ParallelDrawing.js' \
	--js 'lib/Editor/Lang/ca.js' \
	--js 'lib/Editor/Lang/de.js' \
	--js 'lib/Editor/Lang/en.js' \
	--js 'lib/Editor/Lang/hu.js' \
	--js 'lib/Editor/Lang/nl.js' \
	--js_output_file ole.min.js

pack_distribution: minified
	rm -f $(DISTRIBUTION_NAME).zip
	zip --recurse-paths $(DISTRIBUTION_NAME).zip examples/ lib/ tests/ theme/ documentation.md features.md license.txt Makefile README.md ole.min.js
	
	rm -f $(DISTRIBUTION_NAME).tar.gz
	tar -pczf $(DISTRIBUTION_NAME).tar.gz  examples/ lib/ tests/ theme/ documentation.md features.md license.txt Makefile README.md ole.min.js