/**
 * @requires GeoExt/Lang.js
 */

GeoExt.Lang.add("en", {

    "gxp.menu.LayerMenu.prototype": {
        layerText: "Layer"
    },

    "gxp.plugins.AddLayers.prototype": {
        addActionMenuText: "Add layers",
        addActionTip: "Add layers",
        addServerText: "Add a New Server",
        addButtonText: "Add layers",
        untitledText: "Untitled",
        addLayerSourceErrorText: "Error getting WMS capabilities ({msg}).\nPlease check the url and try again.",
        availableLayersText: "Available Layers",
        expanderTemplateText: "<p><b>Abstract:</b> {abstract}</p>",
        panelTitleText: "Title",
        layerSelectionText: "View available data from:",
        doneText: "Done",
        uploadText: "Upload layers",
        addFeedActionMenuText: "Add feeds",
        searchText: "Search for layers"
    },
    
    "gxp.plugins.BingSource.prototype": {
        title: "Bing Layers",
        roadTitle: "Bing Roads",
        aerialTitle: "Bing Aerial",
        labeledAerialTitle: "Bing Aerial With Labels"
    },

    "gxp.plugins.FeatureEditor.prototype": {
        splitButtonText: "Edit",
        createFeatureActionText: "Create",
        editFeatureActionText: "Modify",
        createFeatureActionTip: "Create a new feature",
        editFeatureActionTip: "Edit existing feature",
        commitTitle: "Commit message",
        commitText: "Please enter a commit message for this edit:"
    },
    
    "gxp.plugins.FeatureGrid.prototype": {
        displayFeatureText: "Display on map",
        firstPageTip: "First page",
        previousPageTip: "Previous page",
        zoomPageExtentTip: "Zoom to page extent",
        nextPageTip: "Next page",
        lastPageTip: "Last page",
        totalMsg: "Features {1} to {2} of {0}"
    },

    "gxp.plugins.GoogleEarth.prototype": {
        menuText: "3D Viewer",
        tooltip: "Switch to 3D Viewer"
    },
    
    "gxp.plugins.GoogleSource.prototype": {
        title: "Google Layers",
        roadmapAbstract: "Show street map",
        satelliteAbstract: "Show satellite imagery",
        hybridAbstract: "Show imagery with street names",
        terrainAbstract: "Show street map with terrain"
    },

    "gxp.plugins.LayerProperties.prototype": {
        menuText: "Layer Properties",
        toolTip: "Layer Properties"
    },
    
    "gxp.plugins.LayerTree.prototype": {
        shortTitle: "Layers",
        rootNodeText: "Layers",
        overlayNodeText: "Overlays",
        baseNodeText: "Base Layers"
    },

    "gxp.plugins.LayerManager.prototype": {
        baseNodeText: "Base Maps"
    },

    "gxp.plugins.Legend.prototype": {
        menuText: "Show legend",
        tooltip: "Show legend"
    },

    "gxp.plugins.LoadingIndicator.prototype": {
        loadingMapMessage: "Loading map..."
    },

    "gxp.plugins.MapBoxSource.prototype": {
        title: "MapBox Layers",
        blueMarbleTopoBathyJanTitle: "Blue Marble Topography & Bathymetry (January)",
        blueMarbleTopoBathyJulTitle: "Blue Marble Topography & Bathymetry (July)",
        blueMarbleTopoJanTitle: "Blue Marble Topography (January)",
        blueMarbleTopoJulTitle: "Blue Marble Topography (July)",
        controlRoomTitle: "Control Room",
        geographyClassTitle: "Geography Class",
        naturalEarthHypsoTitle: "Natural Earth Hypsometric",
        naturalEarthHypsoBathyTitle: "Natural Earth Hypsometric & Bathymetry",
        naturalEarth1Title: "Natural Earth I",
        naturalEarth2Title: "Natural Earth II",
        worldDarkTitle: "World Dark",
        worldLightTitle: "World Light",
        worldPrintTitle: "World Print"
    },

    "gxp.plugins.Measure.prototype": {
        buttonText: "Measure",
        lengthMenuText: "Length",
        areaMenuText: "Area",
        lengthTooltip: "Measure length",
        areaTooltip: "Measure area",
        measureTooltip: "Measure"
    },

    "gxp.plugins.Navigation.prototype": {
        menuText: "Pan map",
        tooltip: "Pan map"
    },

    "gxp.plugins.NavigationHistory.prototype": {
        previousMenuText: "Zoom to previous extent",
        nextMenuText: "Zoom to next extent",
        previousTooltip: "Zoom to previous extent",
        nextTooltip: "Zoom to next extent"
    },

    "gxp.plugins.OSMSource.prototype": {
        title: "OpenStreetMap Layers",
        mapnikAttribution: "&copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",
        osmarenderAttribution: "Data CC-By-SA by <a href='http://openstreetmap.org/'>OpenStreetMap</a>"
    },

    "gxp.plugins.Print.prototype": {
        buttonText:"Print",
        menuText: "Print map",
        tooltip: "Print map",
        previewText: "Print Preview",
        notAllNotPrintableText: "Not All Layers Can Be Printed",
        nonePrintableText: "None of your current map layers can be printed"
    },

    "gxp.plugins.MapQuestSource.prototype": {
        title: "MapQuest Layers",
        osmAttribution: "Tiles Courtesy of <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        osmTitle: "MapQuest OpenStreetMap",
        naipAttribution: "Tiles Courtesy of <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        naipTitle: "MapQuest Imagery"
    },

    "gxp.plugins.QueryForm.prototype": {
        queryActionText: "Query",
        queryMenuText: "Query layer",
        queryActionTip: "Query the selected layer",
        queryByLocationText: "Query by current map extent",
        queryByAttributesText: "Query by attributes",
        queryMsg: "Querying...",
        cancelButtonText: "Cancel",
        noFeaturesTitle: "No Match",
        noFeaturesMessage: "Your query did not return any results."
    },

    "gxp.plugins.RemoveLayer.prototype": {
        removeMenuText: "Remove layer",
        removeActionTip: "Remove layer"
    },
    
    "gxp.plugins.Styler.prototype": {
        menuText: "Layer Styles",
        tooltip: "Layer Styles"

    },

    "gxp.plugins.WMSGetFeatureInfo.prototype": {
        buttonText:"Identify",
        infoActionTip: "Get Feature Info",
        popupTitle: "Feature Info"
    },

    "gxp.plugins.Zoom.prototype": {
        zoomMenuText: "Zoom box",
        zoomInMenuText: "Zoom in",
        zoomOutMenuText: "Zoom out",
        zoomTooltip: "Zoom by dragging a box",
        zoomInTooltip: "Zoom in",
        zoomOutTooltip: "Zoom out"
    },
    
    "gxp.plugins.ZoomToExtent.prototype": {
        menuText: "Zoom to max extent",
        tooltip: "Zoom to max extent"
    },
    
    "gxp.plugins.ZoomToDataExtent.prototype": {
        menuText: "Zoom to layer extent",
        tooltip: "Zoom to layer extent"
    },

    "gxp.plugins.ZoomToLayerExtent.prototype": {
        menuText: "Zoom to layer extent",
        tooltip: "Zoom to layer extent"
    },
    
    "gxp.plugins.ZoomToSelectedFeatures.prototype": {
        menuText: "Zoom to selected features",
        tooltip: "Zoom to selected features"
    },

    "gxp.FeatureEditPopup.prototype": {
        closeMsgTitle: "Save Changes?",
        closeMsg: "This feature has unsaved changes. Would you like to save your changes?",
        deleteMsgTitle: "Delete Feature?",
        deleteMsg: "Are you sure you want to delete this feature?",
        editButtonText: "Edit",
        editButtonTooltip: "Make this feature editable",
        deleteButtonText: "Delete",
        deleteButtonTooltip: "Delete this feature",
        cancelButtonText: "Cancel",
        cancelButtonTooltip: "Stop editing, discard changes",
        saveButtonText: "Save",
        saveButtonTooltip: "Save changes"
    },
    
    "gxp.FillSymbolizer.prototype": {
        fillText: "Fill",
        colorText: "Color",
        opacityText: "Opacity"
    },
    
    "gxp.FilterBuilder.prototype": {
        builderTypeNames: ["any", "all", "none", "not all"],
        preComboText: "Match",
        postComboText: "of the following:",
        addConditionText: "add condition",
        addGroupText: "add group",
        removeConditionText: "remove condition"
    },
    
    "gxp.grid.CapabilitiesGrid.prototype": {
        nameHeaderText : "Name",
        titleHeaderText : "Title",
        queryableHeaderText : "Queryable",
        layerSelectionLabel: "View available data from:",
        layerAdditionLabel: "or add a new server.",
        expanderTemplateText: "<p><b>Abstract:</b> {abstract}</p>"
    },
    
    "gxp.PointSymbolizer.prototype": {
        graphicCircleText: "Circle",
        graphicSquareText: "Square",
        graphicTriangleText: "Triangle",
        graphicStarText: "Star",
        graphicCrossText: "Cross",
        graphicXText: "X",
        graphicExternalText: "External",
        urlText: "URL",
        opacityText: "Opacity",
        symbolText: "Symbol",
        sizeText: "Size",
        rotationText: "Rotation"
    },

    "gxp.QueryPanel.prototype": {
        queryByLocationText: "Query by location",
        currentTextText: "Current extent",
        queryByAttributesText: "Query by attributes",
        layerText: "Layer"
    },
    
    "gxp.RulePanel.prototype": {
        scaleSliderTemplate: "{scaleType} Scale 1:{scale}",
        labelFeaturesText: "Label Features",
        labelsText: "Labels",
        basicText: "Basic",
        advancedText: "Advanced",
        limitByScaleText: "Limit by scale",
        limitByConditionText: "Limit by condition",
        symbolText: "Symbol",
        nameText: "Name"
    },
    
    "gxp.ScaleLimitPanel.prototype": {
        scaleSliderTemplate: "{scaleType} Scale 1:{scale}",
        minScaleLimitText: "Min scale limit",
        maxScaleLimitText: "Max scale limit"
    },
    
    "gxp.StrokeSymbolizer.prototype": {
        solidStrokeName: "Solid",
        dashStrokeName: "Dash",
        dotStrokeName: "Dot",
        titleText: "Stroke",
        styleText: "Style",
        colorText: "Color",
        widthText: "Width (px)",
        opacityText: "Opacity"
    },
    
    "gxp.StylePropertiesDialog.prototype": {   
        titleText: "General",
        nameFieldText: "Name",
        titleFieldText: "Title",
        abstractFieldText: "Abstract"
    },
    
    "gxp.TextSymbolizer.prototype": {
        labelValuesText: "Label values",
        haloText: "Halo",
        sizeText: "Size"
    },
    
    "gxp.WMSLayerPanel.prototype": {
        attributionText: "Attribution",
        aboutText: "About",
        titleText: "Title",
        nameText: "Name",
        descriptionText: "Description",
        displayText: "Display",
        opacityText: "Opacity",
        formatText: "Format",
        transparentText: "Transparent",
        cacheText: "Cache",
        cacheFieldText: "Use cached version",
        stylesText: "Available Styles",
        infoFormatText: "Info format",
        infoFormatEmptyText: "Select a format",
        displayOptionsText: "Display options",
        queryText: "Limit with filters",
        scaleText: "Limit by scale",
        minScaleText: "Min scale",
        maxScaleText: "Max scale",
        switchToFilterBuilderText: "Switch back to filter builder",
        cqlPrefixText: "or ",
        cqlText: "use CQL filter instead",
        singleTileText: "Single tile",
        singleTileFieldText: "Use a single tile"
    },

    "gxp.EmbedMapDialog.prototype": {
        publishMessage: "Your map is ready to be published to the web! Simply copy the following HTML to embed the map in your website:",
        heightLabel: 'Height',
        widthLabel: 'Width',
        mapSizeLabel: 'Map Size',
        miniSizeLabel: 'Mini',
        smallSizeLabel: 'Small',
        premiumSizeLabel: 'Premium',
        largeSizeLabel: 'Large'
    },
    
    "gxp.StylesDialog.prototype": {
         addStyleText: "Add",
         addStyleTip: "Add a new style",
         chooseStyleText: "Choose style",
         deleteStyleText: "Remove",
         deleteStyleTip: "Delete the selected style",
         editStyleText: "Edit",
         editStyleTip: "Edit the selected style",
         duplicateStyleText: "Duplicate",
         duplicateStyleTip: "Duplicate the selected style",
         addRuleText: "Add",
         addRuleTip: "Add a new rule",
         newRuleText: "New Rule",
         deleteRuleText: "Remove",
         deleteRuleTip: "Delete the selected rule",
         editRuleText: "Edit",
         editRuleTip: "Edit the selected rule",
         duplicateRuleText: "Duplicate",
         duplicateRuleTip: "Duplicate the selected rule",
         cancelText: "Cancel",
         saveText: "Save",
         styleWindowTitle: "User Style: {0}",
         ruleWindowTitle: "Style Rule: {0}",
         stylesFieldsetTitle: "Styles",
         rulesFieldsetTitle: "Rules"
    },

    "gxp.LayerUploadPanel.prototype": {
        titleLabel: "Title",
        titleEmptyText: "Layer title",
        abstractLabel: "Description",
        abstractEmptyText: "Layer description",
        fileLabel: "Data",
        fieldEmptyText: "Browse for data archive...",
        uploadText: "Upload",
        uploadFailedText: "Upload failed",
        processingUploadText: "Processing upload...",
        waitMsgText: "Uploading your data...",
        invalidFileExtensionText: "File extension must be one of: ",
        optionsText: "Options",
        workspaceLabel: "Workspace",
        workspaceEmptyText: "Default workspace",
        dataStoreLabel: "Store",
        dataStoreEmptyText: "Choose a store",
        dataStoreNewText: "Create new store",
        crsLabel: "CRS",
        crsEmptyText: "Coordinate Reference System ID",
        invalidCrsText: "CRS identifier should be an EPSG code (e.g. EPSG:4326)"
    },
    
    "gxp.NewSourceDialog.prototype": {
        title: "Add new server...",
        cancelText: "Cancel",
        addServerText: "Add Server",
        invalidURLText: "Enter a valid URL to a WMS endpoint (e.g. http://example.com/geoserver/wms)",
        contactingServerText: "Contacting Server..."
    },

    "gxp.ScaleOverlay.prototype": { 
        zoomLevelText: "Zoom level"
    },

    "gxp.Viewer.prototype": {
        saveErrorText: "Trouble saving: "
    },

    "gxp.FeedSourceDialog.prototype": {
        feedTypeText: "Source",
        addPicasaText: "Picasa Photos",
        addYouTubeText: "YouTube Videos",
        addRSSText: "Other GeoRSS Feed",
        addFeedText: "Add to Map",
        addTitleText: "Title",
        keywordText: "Keyword",
        doneText: "Done",
        titleText: "Add Feeds",
        maxResultsText: "Max Items"
    }

});
