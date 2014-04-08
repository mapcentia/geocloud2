/**
 * @requires GeoExt/Lang.js
 */

GeoExt.Lang.add("nl", {

    "gxp.plugins.AddLayers.prototype": {
        addActionMenuText: "Voeg kaartlagen toe",
        addActionTip: "Voeg kaartlagen toe",
        addServerText: "Voeg service toe",
        untitledText: "Onbekend",
        addLayerSourceErrorText: "Probleem bij het ophalen van de Error WMS GetCapabilities ({msg}).\nControleer de URL en probeer opnieuw.",
        availableLayersText: "Beschikbare kaartlagen",
        doneText: "Klaar",
        addFeedActionMenuText: "Add feeds",
        searchText: "Zoek naar kaartlagen"
    },
    
    "gxp.plugins.BingSource.prototype": {
        title: "Bing kaartlagen",
        roadTitle: "Bing wegen",
        aerialTitle: "Bing luchtfoto's",
        labeledAerialTitle: "Bing luchtfoto's met labels"
    },    

    "gxp.plugins.FeatureEditor.prototype": {
        createFeatureActionTip: "Maak een nieuw object",
        editFeatureActionTip: "Wijzig een bestand object",
        commitTitle: "Wijzingsbeschrijving",
        commitText: "Voor a.u.b. een beschrijving in voor de wijziging:"
    },
    
    "gxp.plugins.FeatureGrid.prototype": {
        displayFeatureText: "Toon op kaart",
        firstPageTip: "Eerste pagina",
        previousPageTip: "Vorige pagina",
        zoomPageExtentTip: "Zoom naar de uitsnede van de pagina",
        nextPageTip: "Volgende pagina",
        lastPageTip: "Laatste pagina",
        totalMsg: "Features {1} to {2} of {0}"
    },

    "gxp.plugins.GoogleEarth.prototype": {
        menuText: "3D weergave",
        tooltip: "Bekijk kaart in 3D"
    },
    
    "gxp.plugins.GoogleSource.prototype": {
        title: "Google Maps kaartlagen",
        roadmapAbstract: "Toon stratenkaart",
        satelliteAbstract: "Toon satellietbeeld",
        hybridAbstract: "Toon rasterbeelden met labels",
        terrainAbstract: "Toon stratenkaart met terrein"
    },

    "gxp.plugins.LayerProperties.prototype": {
        menuText: "Kaartlaag eigenschappen",
        toolTip: "Kaartlaag eigenschappen"
    },
    
    "gxp.plugins.LayerTree.prototype": {
        shortTitle: "Kaartlagen",
        rootNodeText: "Kaartlagen",
        overlayNodeText: "Kaart overlays",
        baseNodeText: "Basis Kaarten"
    },

    "gxp.plugins.LayerManager.prototype": {
        baseNodeText: "Basis Kaart"
    },

    "gxp.plugins.Legend.prototype": {
        menuText: "Toon legenda",
        tooltip: "Toon legenda"
    },

    "gxp.plugins.LoadingIndicator.prototype": {
        loadingMapMessage: "Laden van de kaart..."
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
        splitButtonText: "Edit",
        createFeatureActionText: "Create",
        editFeatureActionText: "Modify",
        buttonText: "Meten",
        lengthMenuText: "Lengte",
        areaMenuText: "Oppervlakte",
        lengthTooltip: "Meet lengte",
        areaTooltip: "Meet oppervlakte",
        measureTooltip: "Meten"
    },

    "gxp.plugins.Navigation.prototype": {
        menuText: "Verschuif kaart",
        tooltip: "Verschuif kaart"
    },

    "gxp.plugins.NavigationHistory.prototype": {
        previousMenuText: "Zoom naar de vorige uitsnede",
        nextMenuText: "Zoom naar de volgende uitsnede",
        previousTooltip: "Zoom naar de vorige uitsnede",
        nextTooltip: "Zoom naar de volgende uitsnede"
    },

    "gxp.plugins.OSMSource.prototype": {
        title: "OpenStreetMap kaartlagen",
        mapnikAttribution: "&copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",
        osmarenderAttribution: "Data CC-By-SA by <a href='http://openstreetmap.org/'>OpenStreetMap</a>"
    },

    "gxp.plugins.Print.prototype": {
        buttonText:"Afdrukken",
        menuText: "Afdrukken kaart",
        tooltip: "Afdrukken kaart",
        previewText: "Voorvertoning",
        notAllNotPrintableText: "Niet alle lagen kunnen worden afgedrukt",
        nonePrintableText: "Geen van de huidige lagen kunnen worden afgedrukt"
    },

    "gxp.plugins.MapQuestSource.prototype": {
        title: "MapQuest kaartlagen",
        osmAttribution: "Tiles Courtesy of <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        osmTitle: "MapQuest OpenStreetMap",
        naipAttribution: "Tiles Courtesy of <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        naipTitle: "MapQuest Rasterbeelden"
    },

    "gxp.plugins.QueryForm.prototype": {
        queryActionText: "Bevraag",
        queryMenuText: "Bevraag kaartlaag",
        queryActionTip: "Bevraag de geselecteerde kaartlaag",
        queryByLocationText: "Query by current map extent",
        queryByAttributesText: "Bevraag middels attributen",
        queryMsg: "Bevragen...",
        cancelButtonText: "Annuleren",
        noFeaturesTitle: "Niks gevonden",
        noFeaturesMessage: "De bevraging heeft geen resultaten opgeleverd."
    },

    "gxp.plugins.RemoveLayer.prototype": {
        removeMenuText: "Verwijder kaartlaag",
        removeActionTip: "Verwijder kaartlaag"
    },

    "gxp.plugins.WMSGetFeatureInfo.prototype": {
        buttonText:"Identify",
        infoActionTip: "Attribuut-informatie",
        popupTitle: "Attribuut-informatie"
    },

    "gxp.plugins.Zoom.prototype": {
        zoomMenuText: "Zoom Box",
        zoomInMenuText: "Inzoomen",
        zoomOutMenuText: "Uitzoomen",
        zoomTooltip: "Zoom by dragging a box",
        zoomInTooltip: "Inzoomen",
        zoomOutTooltip: "Uitzoomen"
    },
    
    "gxp.plugins.ZoomToExtent.prototype": {
        menuText: "Zoom naar de maximale uitsnede",
        tooltip: "Zoom naar de maximale uitsnede"
    },
    
    "gxp.plugins.ZoomToDataExtent.prototype": {
        menuText: "Zoom naar de uitsnede van de kaartlaag",
        tooltip: "Zoom naar de uitsnede van de kaartlaag"
    },

    "gxp.plugins.ZoomToLayerExtent.prototype": {
        menuText: "Zoom naar de uitsnede van de kaartlaag",
        tooltip: "Zoom naar de uitsnede van de kaartlaag"
    },
    
    "gxp.plugins.ZoomToSelectedFeatures.prototype": {
        menuText: "Zoom naar de geselecteerde objecten",
        tooltip: "Zoom naar de geselecteerde objecten"
    },

    "gxp.FeatureEditPopup.prototype": {
        closeMsgTitle: "Wijzigingen opslaan?",
        closeMsg: "Het object is gewijzigd. Wilt u de wijzigingen opslaan?",
        deleteMsgTitle: "Verwijder object?",
        deleteMsg: "Weet u zeker dat u dit object wilt verwijderen?",
        editButtonText: "Wijzigen",
        editButtonTooltip: "Wijzig dit object",
        deleteButtonText: "Verwijderen",
        deleteButtonTooltip: "Verwijder dit object",
        cancelButtonText: "Annuleren",
        cancelButtonTooltip: "Stop met wijzigen, wijzigingen worden ongedaan gemaakt",
        saveButtonText: "Opslaan",
        saveButtonTooltip: "Wijzigingen opslaan"
    },
    
    "gxp.FillSymbolizer.prototype": {
        fillText: "Opvulling",
        colorText: "Kleur",
        opacityText: "Opaciteit"
    },
    
    "gxp.FilterBuilder.prototype": {
        builderTypeNames: ["om het even welk", "alle", "geen", "niet alle"],
        preComboText: "Overeenkomst",
        postComboText: "van de volgende:",
        addConditionText: "voeg voorwaarde toe",
        addGroupText: "voeg groep toe",
        removeConditionText: "verwijder voorwaarde"
    },
    
    "gxp.grid.CapabilitiesGrid.prototype": {
        nameHeaderText : "Naam",
        titleHeaderText : "Titel",
        queryableHeaderText : "Bevraagbaar",
        layerSelectionLabel: "Bekijk beschikbare data van:",
        layerAdditionLabel: "of voeg een nieuwe server toe.",
        expanderTemplateText: "<p><b>Samenvatting:</b> {abstract}</p>"
    },
    
    "gxp.PointSymbolizer.prototype": {
        graphicCircleText: "cirkel",
        graphicSquareText: "vierkant",
        graphicTriangleText: "driehoek",
        graphicStarText: "ster",
        graphicCrossText: "kruis",
        graphicXText: "x",
        graphicExternalText: "extern",
        urlText: "URL",
        opacityText: "opaciteit",
        symbolText: "Symbool",
        sizeText: "Grootte",
        rotationText: "Rotatie"
    },

    "gxp.QueryPanel.prototype": {
        queryByLocationText: "Bevraag middels locatie",
        currentTextText: "Huidige uitsnede",
        queryByAttributesText: "Bevraag middels attributen",
        layerText: "Kaartlaag"
    },
    
    "gxp.RulePanel.prototype": {
        scaleSliderTemplate: "{scaleType} Schaal 1:{scale}",
        labelFeaturesText: "Label objecten",
        advancedText: "Geavanceerd",
        limitByScaleText: "Beperk middels schaal",
        limitByConditionText: "Beperk middels voorwaarde",
        symbolText: "Symbool",
        nameText: "Naam"
    },
    
    "gxp.ScaleLimitPanel.prototype": {
        scaleSliderTemplate: "{scaleType} Schaal 1:{scale}",
        maxScaleLimitText: "Maximale schaal"
    },
    
    "gxp.TextSymbolizer.prototype": {
        labelValuesText: "Label waardes",
        haloText: "Halo",
        sizeText: "Grootte"
    },
    
    "gxp.WMSLayerPanel.prototype": {
        attributionText: "Bronvermelding",
        aboutText: "Informatie",
        titleText: "Titel",
        nameText: "Naam",
        descriptionText: "Omschrijving",
        displayText: "Toon",
        opacityText: "Opaciteit",
        formatText: "Formaat",
        transparentText: "Transparant",
        cacheText: "Cache",
        cacheFieldText: "Gebruik de versie vanuit de cache",
        stylesText: "Beschikbare Stijlen",
        infoFormatText: "Info formaat",
        infoFormatEmptyText: "Selecteer een formaat",
        displayOptionsText: "Weergave opties",
        queryText: "Begrens d.m.v. query",
        scaleText: "Bregens d.m.v. schaal",
        minScaleText: "Minimum schaal",
        maxScaleText: "Maximum schaal",
        switchToFilterBuilderText: "Terug naar de querybuilder",
        cqlPrefixText: "of ",
        cqlText: "gebruik een CQL filter",
        singleTileText: "Enkele kaarttegel",
        singleTileFieldText: "Gebruik 1 kaarttegel"
    },

    "gxp.EmbedMapDialog.prototype": {
        publishMessage: "Uw map is klaar voor publicatie! Kopieer de volgende HTML in uw website om de kaart in te voegen:",
        heightLabel: 'Hoogte',
        widthLabel: 'Breedte',
        mapSizeLabel: 'Kaartgrootte',
        miniSizeLabel: 'Mini',
        smallSizeLabel: 'Klein',
        premiumSizeLabel: 'Extra groot',
        largeSizeLabel: 'Groot'
    },
    
    "gxp.StylesDialog.prototype": {
         addStyleText: "Voeg toe",
         addStyleTip: "Voeg een nieuwe stijl toe",
         deleteStyleText: "Verwijder",
         deleteStyleTip: "Verwijder de geselecteerde stijl",
         editStyleText: "Wijzig",
         editStyleTip: "Wijzig de geselecteerde stijl",
         duplicateStyleText: "Dupliceer",
         duplicateStyleTip: "Dupliceer de geselecteerde stijl",
         addRuleText: "Voeg toe",
         addRuleTip: "Voeg een nieuwe klasse toe",
         deleteRuleText: "Verwijder",
         deleteRuleTip: "Verwijder de geselecteerde klasse",
         editRuleText: "Wijzig",
         editRuleTip: "Wijzig de geselecteerde klasse",
         duplicateRuleText: "Dupliceer",
         duplicateRuleTip: "Dupliceer de geselecteerde klasse",
         cancelText: "Annuleer",
         styleWindowTitle: "Kaartstijl: {0}",
         ruleWindowTitle: "Klasse: {0}",
         stylesFieldsetTitle: "Kaartstijlen",
         rulesFieldsetTitle: "Klassen"
    },

    "gxp.LayerUploadPanel.prototype": {
        titleLabel: "Titel",
        titleEmptyText: "Kaartlaag titel",
        abstractLabel: "Omschrijving",
        abstractEmptyText: "Kaartlaag omschrijving",
        fileLabel: "Data",
        fieldEmptyText: "Kies data archief...",
        uploadText: "Upload",
        uploadFailedText: "Upload failed",
        processingUploadText: "Processing upload...",
        waitMsgText: "Bezig met uploaden van de data...",
        invalidFileExtensionText: "Bestandsextensie is een van: ",
        optionsText: "Opties",
        workspaceLabel: "Werkruimte",
        workspaceEmptyText: "Standaard werkruimte",
        dataStoreLabel: "Archief",
        dataStoreEmptyText: "Create new store",
        defaultDataStoreEmptyText: "Standaard archief"
    },

    "gxp.NewSourceDialog.prototype": {
        title: "Add New Server...",
        cancelText: "Cancel",
        addServerText: "Add Server",
        invalidURLText: "Enter a valid URL to a WMS endpoint (e.g. http://example.com/geoserver/wms)",
        contactingServerText: "Contacting Server..."
    },

    "gxp.ScaleOverlay.prototype": { 
        zoomLevelText: "Zoom niveau"
    },

    "gxp.Viewer.prototype": {
        saveErrorText: "Problemen bij het opslaan: "
    },

    "gxp.FeedSourceDialog.prototype": {
    	feedTypeText: "Bron",
        addPicasaText: "Picasa Foto's",
        addYouTubeText: "YouTube video's",
        addRSSText: "Andere GeoRSS Feed",
        addFeedText: "Voeg toe aan Map",
        addTitleText: "Titel",
        keywordText: "Trefwoord",
        doneText: "Klaar",
        titleText: "Voeg Feeds",
        maxResultsText: "Max Items"
    }

});
