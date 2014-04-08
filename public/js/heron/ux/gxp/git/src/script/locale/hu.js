/**
 * @requires GeoExt/Lang.js
 */

GeoExt.Lang.add("hu", {

    "gxp.menu.LayerMenu.prototype": {
        layerText: "Réteg"
    },

    "gxp.plugins.AddLayers.prototype": {
        addActionMenuText: "Rétegek hozzáadása",
        addActionTip: "Rétegek hozzáadása",
        addServerText: "Új Szerver hozzáadása",
        addButtonText: "Rétegek hozzáadása",
        untitledText: "Névtelen",
        addLayerSourceErrorText: "Hiba történt a WMS capabilities lekérdezésekor ({msg}).\nEllenőrizze az elérési címet.",
        availableLayersText: "Elérhető rétegek",
        expanderTemplateText: "<p><b>Abstractt:</b> {abstract}</p>",
        panelTitleText: "Cím",
        layerSelectionText: "Rendelkezésre álló adat megtekintése innen:",
        doneText: "Kész",
        uploadText: "Rétegek feltöltése",
        addFeedActionMenuText: "Feed hozzáadása",
        searchText: "Rétegek keresése"
    },
    
    "gxp.plugins.BingSource.prototype": {
        title: "Bing Rétegek",
        roadTitle: "Bing Roads",
        aerialTitle: "Bing Aerial",
        labeledAerialTitle: "Bing Aerial Feliratokkal"
    },

    "gxp.plugins.FeatureEditor.prototype": {
        splitButtonText: "Szerkeszt",
        createFeatureActionText: "Létrehoz",
        editFeatureActionText: "Módosít",
        createFeatureActionTip: "Új elem létrehozása",
        editFeatureActionTip: "Létező elem módosítása",
        commitTitle: "Commit message", //TODO
        commitText: "Please enter a commit message for this edit:" //TODO
    },
    
    "gxp.plugins.FeatureGrid.prototype": {
        displayFeatureText: "Térképen mutat",
        firstPageTip: "Első oldal",
        previousPageTip: "Előző oldal",
        zoomPageExtentTip: "Lap kiterjedésére nagyít", //Zoom to page extent //TODO
        nextPageTip: "Következő lap",
        lastPageTip: "Utolsó lap",
        totalMsg: "Features {1} to {2} of {0}" //TODO
    },

    "gxp.plugins.GoogleEarth.prototype": {
        menuText: "3D Nézet",
        tooltip: "3D nézetbe váltás"
    },
    
    "gxp.plugins.GoogleSource.prototype": {
        title: "Google Rétegek",
        roadmapAbstract: "Show street map", //TODO
        satelliteAbstract: "Show satellite imagery", //TODO
        hybridAbstract: "Show imagery with street names", //TODO
        terrainAbstract: "Show street map with terrain" //TODO
    },

    "gxp.plugins.LayerProperties.prototype": {
        menuText: "Réteg tulajdonságok",
        toolTip: "Réteg tulajdonságok"
    },
    
    "gxp.plugins.LayerTree.prototype": {
        shortTitle: "Rétegek",
        rootNodeText: "Rétegek",
        overlayNodeText: "Fedvények",
        baseNodeText: "Alaptérképek"
    },

    "gxp.plugins.LayerManager.prototype": {
        baseNodeText: "Alaptérképek"
    },

    "gxp.plugins.Legend.prototype": {
        menuText: "Jelmagyarázatot mutat",
        tooltip: "Jelmagyarázatot mutat"
    },

    "gxp.plugins.LoadingIndicator.prototype": {
        loadingMapMessage: "Térkép betöltése..."
    },

    "gxp.plugins.MapBoxSource.prototype": {
        title: "MapBox Layers", //TODO
        blueMarbleTopoBathyJanTitle: "Blue Marble Topography & Bathymetry (January)",//TODO
        blueMarbleTopoBathyJulTitle: "Blue Marble Topography & Bathymetry (July)",//TODO
        blueMarbleTopoJanTitle: "Blue Marble Topography (January)",//TODO
        blueMarbleTopoJulTitle: "Blue Marble Topography (July)",//TODO
        controlRoomTitle: "Control Room",//TODO
        geographyClassTitle: "Geography Class",//TODO
        naturalEarthHypsoTitle: "Natural Earth Hypsometric",//TODO
        naturalEarthHypsoBathyTitle: "Natural Earth Hypsometric & Bathymetry",//TODO
        naturalEarth1Title: "Natural Earth I",//TODO
        naturalEarth2Title: "Natural Earth II",//TODO
        worldDarkTitle: "World Dark",//TODO
        worldLightTitle: "World Light",//TODO
        worldPrintTitle: "World Print"//TODO
    },

    "gxp.plugins.Measure.prototype": {
        buttonText: "Mérés",//TODO
        lengthMenuText: "Hossz",
        areaMenuText: "Terület",
        lengthTooltip: "Hosszmérés",
        areaTooltip: "Területmérés",
        measureTooltip: "Mérés"//TODO
    },

    "gxp.plugins.Navigation.prototype": {
        menuText: "Térkép eltolás",
        tooltip: "Térkép eltolás"
    },

    "gxp.plugins.NavigationHistory.prototype": {
        previousMenuText: "Előző nézetre nagyít",
        nextMenuText: "Következő nézetre nagyít",
        previousTooltip: "Előző nézetre nagyít",
        nextTooltip: "Következő nézetre nagyít"
    },

    "gxp.plugins.OSMSource.prototype": {
        title: "OpenStreetMap Rétegek",
        mapnikAttribution: "&copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",//TODO
        osmarenderAttribution: "Data CC-By-SA by <a href='http://openstreetmap.org/'>OpenStreetMap</a>"//TODO
    },

    "gxp.plugins.Print.prototype": {
        buttonText:"Nyomtat",
        menuText: "Térkép nyomtatása",
        tooltip: "Térkép nyomtatása",
        previewText: "Térkép előnézet",
        notAllNotPrintableText: "Nem nyomtatható minden réteg",
        nonePrintableText: "Nem nyomtatható minden réteg"
    },

    "gxp.plugins.MapQuestSource.prototype": {
        title: "MapQuest Rétegek",
        osmAttribution: "Tiles Courtesy of <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",//TODO
        osmTitle: "MapQuest OpenStreetMap",//TODO
        naipAttribution: "Tiles Courtesy of <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",//TODO
        naipTitle: "MapQuest Imagery"//TODO
    },

    "gxp.plugins.QueryForm.prototype": {
        queryActionText: "Lekérdezés",
        queryMenuText: "Réteg lekérdezés",
        queryActionTip: "A kiválasztott réteg lekérdezése",
        queryByLocationText: "Keresés térbeli helyzet alapján",
        queryByAttributesText: "Keresés attribútumok alapján",
        queryMsg: "Keresés...",
        cancelButtonText: "Mégsem",
        noFeaturesTitle: "Nincs találat",
        noFeaturesMessage: "A keresés nem hozott eredményt."
    },

    "gxp.plugins.RemoveLayer.prototype": {
        removeMenuText: "Réteg eltávolítása",
        removeActionTip: "Réteg eltávolítása"
    },
    
    "gxp.plugins.Styler.prototype": {
        menuText: "Réteg stílusok",
        tooltip: "Réteg stílusok"
    },

    "gxp.plugins.WMSGetFeatureInfo.prototype": {
        buttonText:"Aonosít",
        infoActionTip: "Elem azonosítás (Get Feature Info)",
        popupTitle: "Elem azonosítás (Feature Info)"
    },

    "gxp.plugins.Zoom.prototype": {
        zoomMenuText: "Zoom box",//TODO
        zoomInMenuText: "Nagyít",
        zoomOutMenuText: "Kicsinyít",
        zoomTooltip: "Zoom by dragging a box",//TODO
        zoomInTooltip: "Nagyít",
        zoomOutTooltip: "Kicsinyít"
    },
    
    "gxp.plugins.ZoomToExtent.prototype": {
        menuText: "Teljes kiterjedésre nagyít",
        tooltip: "Teljes kiterjedésre nagyít"
    },
    
    "gxp.plugins.ZoomToDataExtent.prototype": {
        menuText: "Réteg kiterjedésre nagyít",
        tooltip: "Réteg kiterjedésre nagyít"
    },

    "gxp.plugins.ZoomToLayerExtent.prototype": {
        menuText: "Réteg kiterjedésre nagyít",
        tooltip: "Réteg kiterjedésre nagyít"
    },
    
    "gxp.plugins.ZoomToSelectedFeatures.prototype": {
        menuText: "Kiválasztott elemekre nagyít",
        tooltip: "Kiválasztott elemekre nagyít"
    },

    "gxp.FeatureEditPopup.prototype": {
        closeMsgTitle: "Menti a változtatásokat?",
        closeMsg: "This feature has unsaved changes. Would you like to save your changes?", //TODO
        deleteMsgTitle: "Törli az elemet?",
        deleteMsg: "Biztos benne, hogy törli az elemet?",
        editButtonText: "Szerkeszt",
        editButtonTooltip: "Elem szerkeszthetővé tétele",
        deleteButtonText: "Törlés",
        deleteButtonTooltip: "Elem törlése",
        cancelButtonText: "Mégsem",
        cancelButtonTooltip: "Szerkesztés befejezése, változtatások elvetése",
        saveButtonText: "Mentés",
        saveButtonTooltip: "Változtatások mentése"
    },
    
    "gxp.FillSymbolizer.prototype": {
        fillText: "Kitöltés",
        colorText: "Szín",
        opacityText: "Átlátszóság"
    },
    
    "gxp.FilterBuilder.prototype": {
        builderTypeNames: ["bármelyik", "mindegyik", "egyik sem", "nem mindegyik"],
        preComboText: "Teljesüljön",
        postComboText: "a következőkből:",
        addConditionText: "feltétel hozzáadása",
        addGroupText: 'csoport hozzáadása',
        removeConditionText: "feltétel törlése"
    },
    
    "gxp.grid.CapabilitiesGrid.prototype": {
        nameHeaderText : "Név",
        titleHeaderText : "Cím",
        queryableHeaderText : "Kereshető",
        layerSelectionLabel: "View available data from:",//TODO
        layerAdditionLabel: "or add a new server.",//TODO
        expanderTemplateText: "<p><b>Abstract:</b> {abstract}</p>"//TODO
    },
    
    "gxp.PointSymbolizer.prototype": {
        graphicCircleText: "kör",
        graphicSquareText: "négyzet",
        graphicTriangleText: "háromszög",
        graphicStarText: "csillag",
        graphicCrossText: "kereszt",
        graphicXText: "x",
        graphicExternalText: "külső",
        urlText: "URL",
        opacityText: "átlátszóság",
        symbolText: "Szimbólum",
        sizeText: "Méret",
        rotationText: "Elforgatás"
    },

    "gxp.QueryPanel.prototype": {
        queryByLocationText: "Keresés térbeli helyzet alapján",
        currentTextText: "Aktuális kiterjedés",
        queryByAttributesText: "Keresés attribútumok alapján",
        layerText: "Réteg"
    },
    
    "gxp.RulePanel.prototype": {
        scaleSliderTemplate: "{scaleType} Méretarány 1:{scale}",
        labelFeaturesText: "Elemek címkézése",
        labelsText: "Címkék",
        basicText: "Alapbeállítások",
        advancedText: "Haladó",
        limitByScaleText: "Szűrés méretarány szerint",
        limitByConditionText: "Szűrés feltétel szerint",
        symbolText: "Szimbólum",
        nameText: "Név"
    },
    
    "gxp.ScaleLimitPanel.prototype": {
        scaleSliderTemplate: "{scaleType} Lépték 1:{scale}",
        minScaleLimitText: "Min lépték",
        maxScaleLimitText: "Max lépték"
    },
    
    "gxp.StrokeSymbolizer.prototype": {
        solidStrokeName: "tömör",
        dashStrokeName: "szaggatott",
        dotStrokeName: "pontozott",
        titleText: "Körvonal",
        styleText: "Stílus",
        colorText: "Szín",
        widthText: "Szélesség",
        opacityText: "Átlátszóság"
    },
    
    "gxp.StylePropertiesDialog.prototype": {   
        titleText: "Általános",
        nameFieldText: "Név",
        titleFieldText: "Cím",
        abstractFieldText: "Abstract"
    },
    
    "gxp.TextSymbolizer.prototype": {
        labelValuesText: "Címke mező",
        haloText: "Körvonal (maszk)",
        sizeText: "Méret",
		fontColorTitle: "Betű szín és átlátszóság"
    },
    
    "gxp.WMSLayerPanel.prototype": {
        attributionText: "Attribution",//TODO
        aboutText: "About",//TODO
        titleText: "Title",//TODO
        nameText: "Name",//TODO
        descriptionText: "Description",//TODO
        displayText: "Display",//TODO
        opacityText: "Opacity",//TODO
        formatText: "Format",//TODO
        transparentText: "Transparent",//TODO
        cacheText: "Cache",//TODO
        cacheFieldText: "Use cached version",//TODO
        stylesText: "Available Styles",//TODO
        infoFormatText: "Info format",//TODO
        infoFormatEmptyText: "Select a format", //TODO
        displayOptionsText: "Display options", //TODO
        queryText: "Limit with filters",//TODO
        scaleText: "Limit by scale",//TODO
        minScaleText: "Min scale",//TODO
        maxScaleText: "Max scale",//TODO
        switchToFilterBuilderText: "Switch back to filter builder",//TODO
        cqlPrefixText: "or ",//TODO
        cqlText: "use CQL filter instead",//TODO
        singleTileText: "Single tile",//TODO
        singleTileFieldText: "Use a single tile"//TODO
    },

    "gxp.EmbedMapDialog.prototype": {
        publishMessage: "Az Ön térképe készen áll a publikálásra. Másolja be a következő HTML-t a honlapjára", //Simply copy the following HTML to embed the map in your website:",//TODO
        heightLabel: 'Magasság',
        widthLabel: 'Szélesség',
        mapSizeLabel: 'Térkép mérete',
        miniSizeLabel: 'Mini',
        smallSizeLabel: 'Kicsi',
        premiumSizeLabel: 'Prémium',
        largeSizeLabel: 'Nagy'
    },
    
    "gxp.WMSStylesDialog.prototype": {
         addStyleText: "Hozzáad",
         addStyleTip: "Új stílus hozzáadása",
         chooseStyleText: "Válasszon stílust",
         deleteStyleText: "Töröl",
         deleteStyleTip: "A kiválasztott stílus törlése",
         editStyleText: "Szerkeszt",
         editStyleTip: "A kiválasztott stílus szerkesztése",
         duplicateStyleText: "Duplikál",
         duplicateStyleTip: "A kiválasztott stílus duplikálása",
         addRuleText: "Hozzáad",
         addRuleTip: "Új szabály hozzáadása",
         newRuleText: "Új szabály",
         deleteRuleText: "Töröl",
         deleteRuleTip: "A kiválasztott szabály törlése",
         editRuleText: "Szerkeszt",
         editRuleTip: "A kiválasztott szabály szerkesztése",
         duplicateRuleText: "Duplikál",
         duplicateRuleTip: "A kiválasztott szabály duplikálása",
         cancelText: "Mégsem",
         saveText: "Mentés",
         styleWindowTitle: "Felhasználói stílus: {0}",
         ruleWindowTitle: "Stílusszabály: {0}",
         stylesFieldsetTitle: "Stílusok",
         rulesFieldsetTitle: "Szabályok"
    },

    "gxp.LayerUploadPanel.prototype": {
        titleLabel: "Cím",
        titleEmptyText: "Réteg neve",
        abstractLabel: "Leírás",
        abstractEmptyText: "Réteg leírás",
        fileLabel: "Adat",
        fieldEmptyText: "Browse for data archive...",//TODO
        uploadText: "Feltöltés",
        uploadFailedText: "Feltöltés sikertelen",
        processingUploadText: "Feltöltés folyamatban...",
        waitMsgText: "Adat feltöltése...",
        invalidFileExtensionText: "File extension must be one of: ",//TODO
        optionsText: "Options",//TODO
        workspaceLabel: "Munkaterület",
        workspaceEmptyText: "Alapértelmezett munkaterület",
        dataStoreLabel: "Store",//TODO
        dataStoreEmptyText: "Create new store",//TODO
        defaultDataStoreEmptyText: "Default data store"//TODO
    },
    
    "gxp.NewSourceDialog.prototype": {
        title: "Új szerver hozzáadása...",
        cancelText: "Mégsem",
        addServerText: "Szerver hozzáadása",
        invalidURLText: "Adjon meg helyes URL-t a WMS híváshoz (pl. http://example.com/geoserver/wms)",
        contactingServerText: "Kapcsolódás a szerverhez..."
    },

    "gxp.ScaleOverlay.prototype": { 
        zoomLevelText: "Zoom szint"
    },

    "gxp.Viewer.prototype": {
        saveErrorText: "Probléma a mentés során: "
    },

    "gxp.FeedSourceDialog.prototype": {
        feedTypeText: "Source",//TODO
        addPicasaText: "Picasa Photos",//TODO
        addYouTubeText: "YouTube Videos",//TODO
        addRSSText: "Other GeoRSS Feed",//TODO
        addFeedText: "Add to Map",//TODO
        addTitleText: "Cím",
        keywordText: "Kulcsszó",
        doneText: "Kész",
        titleText: "Add Feeds",//TODO
        maxResultsText: "Max Items"//TODO
    },
	
	"gxp.StylesDialog.prototype": {
		cancelText: "Mégsem",
		saveText: "Mentés",
		addStyleText: "Hozzáad",
		addStyleTip: "Új stílus hozzáadása",
		chooseStyleText: "Válasszon stílust",
		deleteStyleText: "Eltávolít",
		deleteStyleTip: "Kiválasztott stílus törlése",
		editStyleText: "Szerkeszt",
		editStyleTip: "Kiválasztott stílus szerkesztése",
		duplicateStyleText: "Duplikál",
		duplicateStyleTip: "Kiválasztott stílus duplikálása",
		addRuleText: "Hozzáad",
		addRuleTip: "Új szabály hozzáadása",
		newRuleText: "Új szabály",
		deleteRuleText: "Eltávolít",
		deleteRuleTip: "Kiválasztott szabály törlése",
		editRuleText: "Szerkeszt",
		editRuleTip: "Kiválasztott szabály szerkesztése",
		duplicateRuleText: "Duplikál",
		duplicateRuleTip: "Kiválasztott szabály duplikálása",
		styleWindowTitle: "Felhasználói stílus: {0}",
		ruleWindowTitle: "Stílusszabály: {0}",
		stylesFieldsetTitle: "Stílusok",
		rulesFieldsetTitle: "Szabályok",
		errorTitle: "Hiba történt a stílus mentése során",
		errorMsg: "Hiba történt a stílus szerverre történő mentése során."
	}

});
