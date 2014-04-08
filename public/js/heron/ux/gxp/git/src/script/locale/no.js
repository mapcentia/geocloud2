/**
* @requires GeoExt/Lang.js
*/
 
GeoExt.Lang.add("no", {
 
    "gxp.menu.LayerMenu.prototype": {
        layerText: "Kartlag"
    },
 
    "gxp.plugins.AddLayers.prototype": {
        addActionMenuText: "Legg til kartlag",
        addActionTip: "Legg til kartlag",
        addServerText: "Legg til en ny server",
        addButtonText: "Legg til kartlag",
        untitledText: "Uten titel",
        addLayerSourceErrorText: "Feil ved henting av WMS capabilities ({msg}).\nSjekk urlen og prøv igjen.",
        availableLayersText: "Tilgjengelige kartlag",
        expanderTemplateText: "<p><b>Abstrakt:</b> {abstract}</p>",
        panelTitleText: "Titel",
        layerSelectionText: "Vis tilgjengelige data fra:",
        doneText: "Ferdig",
        uploadText: "Last opp kartlag",
        addFeedActionMenuText: "Legg til feeds",
        searchText: "Søk etter kartlag"
    },
   
    "gxp.plugins.BingSource.prototype": {
        title: "Bing kartlag",
        roadTitle: "Bing Roads",
        aerialTitle: "Bing Aerial",
        labeledAerialTitle: "Bing Aerial med Etikett"
    },
 
    "gxp.plugins.FeatureEditor.prototype": {
        splitButtonText: "Editer",
        createFeatureActionText: "Lag",
        editFeatureActionText: "Modifiser",
        createFeatureActionTip: "Lag en ny feature",
        editFeatureActionTip: "Editer eksisterende feature",
        commitTitle: "Sjekk inn melding",
        commitText: "Skriv in en innsjekkingsmelding for denne editeringen:"
    },
   
    "gxp.plugins.FeatureGrid.prototype": {
        displayFeatureText: "Vis på kart",
        firstPageTip: "Første side",
        previousPageTip: "Neste side",
        zoomPageExtentTip: "Zoom til side utstrekning",
        nextPageTip: "Neste side",
        lastPageTip: "Siste side",
        totalMsg: "Features {1} til {2} av {0}"
    },
 
    "gxp.plugins.GoogleEarth.prototype": {
        menuText: "3D Visning",
        tooltip: "Bytt til 3D Visning"
    },
   
    "gxp.plugins.GoogleSource.prototype": {
        title: "Google kartlag",
        roadmapAbstract: "Vis street map",
        satelliteAbstract: "Vis satelitt bilder",
        hybridAbstract: "Vis bilder med gatenavn",
        terrainAbstract: "Vis street map med terreng"
    },
 
    "gxp.plugins.LayerProperties.prototype": {
        menuText: "Kartlag Egenskaper",
        toolTip: "Kartlag Egenskaper"
    },
   
    "gxp.plugins.LayerTree.prototype": {
        shortTitle: "kartlag",
        rootNodeText: "Kartlag",
        overlayNodeText: "Kartlag",
        baseNodeText: "Bakgrunnskart"
    },
 
    "gxp.plugins.LayerManager.prototype": {
        baseNodeText: "Bakgrunnskart"
    },
 
    "gxp.plugins.Legend.prototype": {
        menuText: "Vis tegnforklaring",
        tooltip: "Vis tegnforklaring"
    },
 
    "gxp.plugins.LoadingIndicator.prototype": {
        loadingMapMessage: "Laster kart..."
    },
 
    "gxp.plugins.MapBoxSource.prototype": {
        title: "MapBox kartlag",
        blueMarbleTopoBathyJanTitle: "Blue Marble Topografi & Batymetri (januar)",
        blueMarbleTopoBathyJulTitle: "Blue Marble Topografi & Batymetri (Juli)",
        blueMarbleTopoJanTitle: "Blue Marble Topografi (januar)",
        blueMarbleTopoJulTitle: "Blue Marble Topografi (Juli)",
        controlRoomTitle: "Kontrollrom",
        geographyClassTitle: "Geografi Klasse",
        naturalEarthHypsoTitle: "Naturlig Jordklode Hypsometri",
        naturalEarthHypsoBathyTitle: "Naturlig Jordklode  Hypsometri & Batymetri",
        naturalEarth1Title: "Naturlig Jordklode I",
        naturalEarth2Title: "Naturlig Jordklode II",
        worldDarkTitle: "Verden Mørk",
        worldLightTitle: "Verden Lys",
        worldPrintTitle: "Verden utskrift"
    },
 
    "gxp.plugins.Measure.prototype": {
        buttonText: "Mål",
        lengthMenuText: "Lengde",
        areaMenuText: "Areal",
        lengthTooltip: "Mål lengde",
        areaTooltip: "Mål areal",
        measureTooltip: "Mål"
    },
 
    "gxp.plugins.Navigation.prototype": {
        menuText: "Panorer kart",
        tooltip: "Panorer kart"
    },
 
    "gxp.plugins.NavigationHistory.prototype": {
        previousMenuText: "Zoom til forrige utstrekning",
        nextMenuText: "Zoom til neste utstrekning",
        previousTooltip: "Zoom til forrige utstrekning",
        nextTooltip: "Zoom til neste utstrekning"
    },
 
    "gxp.plugins.OSMSource.prototype": {
        title: "OpenStreetMap Kartlag",
        mapnikAttribution: "&kopier; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a> bidragsytere",
        osmarenderAttribution: "Data CC-By-SA av <a href='http://openstreetmap.org/'>OpenStreetMap</a>"
    },
 
    "gxp.plugins.Print.prototype": {
        buttonText:"Skriv ut",
        menuText: "Skriv ut kart",
        tooltip: "Skriv ut kart",
        previewText: "Forhåndsvinsning av utskrift",
        notAllNotPrintableText: "Ikke alle kartlag kan skrives ut",
        nonePrintableText: "Ingen av dine valgte kartlag kan skrives ut"
    },
 
    "gxp.plugins.MapQuestSource.prototype": {
        title: "MapQuest Kartlag",
        osmAttribution: "Titler levert av <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        osmTitle: "MapQuest OpenStreetMap",
        naipAttribution: "Tutker levert av <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        naipTitle: "MapQuest bilder"
    },
 
    "gxp.plugins.QueryForm.prototype": {
        queryActionText: "Spør",
        queryMenuText: "Spør kartlag",
        queryActionTip: "Spør det valgte kartlaget",
        queryByLocationText: "Spør ved valgt kartlags utstrekning",
        queryByAttributesText: "Spør ved attributter",
        queryMsg: "Spørring...",
        cancelButtonText: "Kanseler",
        noFeaturesTitle: "Ingen treff",
        noFeaturesMessage: "Din spørring gav ingen treff."
    },
 
    "gxp.plugins.RemoveLayer.prototype": {
        removeMenuText: "Fjern kartlag",
        removeActionTip: "Fjern kartlag"
    },
   
    "gxp.plugins.Styler.prototype": {
        menuText: "Kartlag Stiler",
        tooltip: "Kartlag Stiler"
 
    },
 
    "gxp.plugins.WMSGetFeatureInfo.prototype": {
        buttonText:"Identifiser",
        infoActionTip: "Hent Feature Info",
        popupTitle: "Feature Info"
    },
 
    "gxp.plugins.Zoom.prototype": {
        zoomMenuText: "Zoom firkant",
        zoomInMenuText: "Zoom inn",
        zoomOutMenuText: "Zoom ut",
        zoomTooltip: "Zoom ved å tegne en firkant",
        zoomInTooltip: "Zoom inn",
        zoomOutTooltip: "Zoom ut"
    },
   
    "gxp.plugins.ZoomToExtent.prototype": {
        menuText: "Zoom til maks utstrekning",
        tooltip: "Zoom til maks utstrekning"
    },
   
    "gxp.plugins.ZoomToDataExtent.prototype": {
        menuText: "Zoom til kartlagets utstrekning",
        tooltip: "Zoom til kartlagets utstrekning"
    },
 
    "gxp.plugins.ZoomToLayerExtent.prototype": {
        menuText: "Zoom til kartlagets utstrekning",
        tooltip: "Zoom til kartlagets utstrekning"
    },
   
    "gxp.plugins.ZoomToSelectedFeatures.prototype": {
        menuText: "Zoom til valgte features",
        tooltip: "Zoom til valgte features"
    },
 
    "gxp.FeatureEditPopup.prototype": {
        closeMsgTitle: "Lagre Endringer?",
        closeMsg: "Denne featuren har nye endringer som ikke er lagret. Vill du lagre endringene?",
        deleteMsgTitle: "Slett Feature?",
        deleteMsg: "Er du sikker på at du vil slette denne featuren?",
        editButtonText: "Editer",
        editButtonTooltip: "Gjør denne featuren editerbar",
        deleteButtonText: "Slett",
        deleteButtonTooltip: "Slett denne featuren",
        cancelButtonText: "Kanseler",
        cancelButtonTooltip: "Stopp editering, forkast endringer",
        saveButtonText: "Lagre",
        saveButtonTooltip: "Lagre endringer"
    },
   
    "gxp.FillSymbolizer.prototype": {
        fillText: "Fyll ut",
        colorText: "Farge",
        opacityText: "Gjennomskinnelighet"
    },
   
    "gxp.FilterBuilder.prototype": {
        builderTypeNames: ["en", "alle", "ingen", "ikke alle"],
        preComboText: "Treff",
        postComboText: "av følgende:",
        addConditionText: "legg til betingelse",
        addGroupText: "Legg til gruppe",
        removeConditionText: "fjern betingelse"
    },
   
    "gxp.grid.CapabilitiesGrid.prototype": {
        nameHeaderText : "Navn",
        titleHeaderText : "Tittel",
        queryableHeaderText : "Mulig å spørre",
        layerSelectionLabel: "Vis tilgjengelig data fra:",
        layerAdditionLabel: "eller legg til en ny server.",
        expanderTemplateText: "<p><b>Abstrakt:</b> {abstract}</p>"
    },
   
    "gxp.PointSymbolizer.prototype": {
        graphicCircleText: "sirkel",
        graphicSquareText: "firkant",
        graphicTriangleText: "triangel",
        graphicStarText: "stjerne",
        graphicCrossText: "kryss",
        graphicXText: "x",
        graphicExternalText: "ekstern",
        urlText: "URL",
        opacityText: "gjennomskinnelighet",
        symbolText: "Symbol",
        sizeText: "Størrelse",
        rotationText: "Rotasjon"
    },
 
    "gxp.QueryPanel.prototype": {
        queryByLocationText: "Spør etter plassering",
        currentTextText: "Nåværende utstrekning",
        queryByAttributesText: "Spør etter attributter",
        layerText: "Kartlag"
    },
   
    "gxp.RulePanel.prototype": {
        scaleSliderTemplate: "{scaleType} Målestokk 1:{scale}",
        labelFeaturesText: "Etikett Features",
        labelsText: "Etiketter",
        basicText: "Grunnleggende",
        advancedText: "Avansert",
        limitByScaleText: "Målestokksbegrensning",
        limitByConditionText: "Begrensning på tilstand",
        symbolText: "Symbol",
        nameText: "Navn"
    },
   
    "gxp.ScaleLimitPanel.prototype": {
        scaleSliderTemplate: "{scaleType} Målestokk 1:{scale}",
        minScaleLimitText: "Minimum målestokksgrense",
        maxScaleLimitText: "Maximum målestokksgrense"
    },
   
    "gxp.StrokeSymbolizer.prototype": {
        solidStrokeName: "solid",
        dashStrokeName: "dash",
        dotStrokeName: "dot",
        titleText: "Strek",
        styleText: "Stil",
        colorText: "Farge",
        widthText: "Bredde",
        opacityText: "gjennomskinnelighet"
    },
   
    "gxp.StylePropertiesDialog.prototype": {  
        titleText: "Generel",
        nameFieldText: "Navn",
        titleFieldText: "Tittel",
        abstractFieldText: "Abstrakt"
    },
   
    "gxp.TextSymbolizer.prototype": {
        labelValuesText: "Etikett verdier",
        haloText: "Halo",
        sizeText: "Størrelse"
    },
   
    "gxp.WMSLayerPanel.prototype": {
        attributionText: "Tilskrivende",
        aboutText: "Om",
        titleText: "Tittel",
        nameText: "Navn",
        descriptionText: "Beskrivelse",
        displayText: "Visning",
        opacityText: "Gjennomskinnelighet",
        formatText: "Format",
        transparentText: "Gjennomskinnelighet",
        cacheText: "Cache",
        cacheFieldText: "Bruk cachet versjon",
        stylesText: "Tilgjengelige Stiler",
        infoFormatText: "Info format",
        infoFormatEmptyText: "Velg et format",
        displayOptionsText: "Visningsvalg",
        queryText: "Begrensning ved filter",
        scaleText: "Begrensning ved målestokk",
        minScaleText: "Minimum målestokk",
        maxScaleText: "Maximum målestokk",
        switchToFilterBuilderText: "Bytt tilbake til filter bygger",
        cqlPrefixText: "eller ",
        cqlText: "bruk CQL filter istedenfor",
        singleTileText: "Enkel tile",
        singleTileFieldText: "Bruk enkel tile"
    },
 
    "gxp.EmbedMapDialog.prototype": {
        publishMessage: "Ditt kartlag kan publisers til web!! Kopier følgende HTML for å legge ditt kartlag til din webside:",
        heightLabel: 'Høyde',
        widthLabel: 'Bredde',
        mapSizeLabel: 'Kart størrelse',
        miniSizeLabel: 'Mini',
        smallSizeLabel: 'Liten',
        premiumSizeLabel: 'Premium',
        largeSizeLabel: 'Stor'
    },
   
    "gxp.WMSStylesDialog.prototype": {
         addStyleText: "Legg til",
         addStyleTip: "Legg til en ny stil",
         chooseStyleText: "Velg en stil",
         deleteStyleText: "Fjern",
         deleteStyleTip: "Slett den valgte stilen",
         editStyleText: "Editer",
         editStyleTip: "Editer den valgte stilen",
         duplicateStyleText: "Reproduser",
         duplicateStyleTip: "Reproduser den valgte stilen",
         addRuleText: "Legg til",
         addRuleTip: "Legg til ny regel",
         newRuleText: "Ny Regel",
         deleteRuleText: "Fjern",
         deleteRuleTip: "Slett den valgte regelen",
         editRuleText: "Editer",
         editRuleTip: "Editer den valgte regelen",
         duplicateRuleText: "Reproduser",
         duplicateRuleTip: "Reproduser den valgte regelen",
         cancelText: "Kanseler",
         saveText: "Lagre",
         styleWindowTitle: "Bruker Stil: {0}",
         ruleWindowTitle: "Stil regel: {0}",
         stylesFieldsetTitle: "Stiler",
         rulesFieldsetTitle: "Regler"
    },
 
    "gxp.LayerUploadPanel.prototype": {
        titleLabel: "Titel",
        titleEmptyText: "Kartlags titel",
        abstractLabel: "Beskrivelse",
        abstractEmptyText: "Kartlag beskrivelse",
        fileLabel: "Data",
        fieldEmptyText: "Bla igjennom data arkiv...",
        uploadText: "Last opp",
        uploadFailedText: "Opplasting feilet",
        processingUploadText: "Prosesserer opplasting...",
        waitMsgText: "Laster opp dataene...",
        invalidFileExtensionText: "Filtype må være en av: ",
        optionsText: "Valt",
        workspaceLabel: "arbeidsområde",
        workspaceEmptyText: "standard arbeidsområde",
        dataStoreLabel: "Lagre",
        dataStoreEmptyText: "Velg et lagringsmedium",
        dataStoreNewText: "Velg et nytt lagringsmedium",
        crsLabel: "KRS",
        crsEmptyText: "Koordinat Referanse System ID",
        invalidCrsText: "KRS identifikatorer må være en EPSG kode (e.g. EPSG:4326)"
    },
   
    "gxp.NewSourceDialog.prototype": {
        title: "Legg til ny Server...",
        cancelText: "Kanseler",
        addServerText: "Legg til Server",
        invalidURLText: "Skriv inn en gyldig URL til et WMS endepunkt (f.eks. http://example.com/geoserver/wms)",
        contactingServerText: "Kontakter Server..."
    },
 
    "gxp.ScaleOverlay.prototype": {
        zoomLevelText: "Zoom nivå"
    },
 
    "gxp.Viewer.prototype": {
        saveErrorText: "Problemer med å lagre: "
    },
 
    "gxp.FeedSourceDialog.prototype": {
        feedTypeText: "Kilde",
        addPicasaText: "Picasa Foto",
        addYouTubeText: "YouTube Videoer",
        addRSSText: "Andre GeoRSS Feed",
        addFeedText: "Legg til Kart",
        addTitleText: "Tittel",
        keywordText: "Nøkkelord",
        doneText: "Ferdig",
        titleText: "Legg til Feeds",
        maxResultsText: "Max elementer"
    }
 
});
