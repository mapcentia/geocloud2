/**
 * @requires GeoExt/Lang.js
 */

GeoExt.Lang.add("ca", {

    "gxp.menu.LayerMenu.prototype": {
        layerText: "Capa"
    },
    
    "gxp.plugins.AddLayers.prototype": {
        addActionMenuText: "Afegeix Capes",
        addActionTip: "Afegeix Capes",
        addServerText: "Afegeix servidor",
        addButtonText: "Afegeix Capes",
        untitledText: "Sense Títol",
        addLayerSourceErrorText: "Error obtenint les capabilities del WMS ({msg}).\nSi us plau, comproveu la URL i torneu-ho a intentar.",
        availableLayersText: "Capes disponibles",
        expanderTemplateText: "<p><b>Resum:</b> {abstract}</p>",
        panelTitleText: "Títol",
        layerSelectionText: "Veure dades disponibles de:",
        doneText: "Fet",
        uploadText: "Puja dades",
        addFeedActionMenuText: "Add feeds",
        searchText: "Search for layers"
    },
    
    "gxp.plugins.BingSource.prototype": {
        title: "Capes Bing",
        roadTitle: "Bing Carrerer",
        aerialTitle: "Bing Fotografia Aèria",
        labeledAerialTitle: "Bing Fotografia Aèria amb Etiquetes"
    },    

    "gxp.plugins.FeatureEditor.prototype": {
        splitButtonText: "Edit",
        createFeatureActionText: "Create",
        editFeatureActionText: "Modify",
        createFeatureActionTip: "Crea nou element",
        editFeatureActionTip: "Edita element existent",
        commitTitle: "Commit message",
        commitText: "Please enter a commit message for this edit:"
    },
    
    "gxp.plugins.FeatureGrid.prototype": {
        displayFeatureText: "Mostra al mapa",
        firstPageTip: "Primera pàgina",
        previousPageTip: "Pàgina anterior",
        zoomPageExtentTip: "Ajusta vista a l'extensió de la pàgina",
        nextPageTip: "Pàgina següent",
        lastPageTip: "Pàgina anterior",
        totalMsg: "Features {1} to {2} of {0}"
    },

    "gxp.plugins.GoogleEarth.prototype": { 
        menuText: "Vista 3D",
        tooltip: "Vista 3D"
    },
    
    "gxp.plugins.GoogleSource.prototype": {
        title: "Capes Google",
        roadmapAbstract: "Mostra carrerer",
        satelliteAbstract: "Mostra imatges de satèl·lit",
        hybridAbstract: "Mostra imatges amb noms de carrer",
        terrainAbstract: "Mostra carrerer amb terreny"
    },

    "gxp.plugins.LayerProperties.prototype": {
        menuText: "Propietats de la capa",
        toolTip: "Propietats de la capa"
    },
    
    "gxp.plugins.LayerTree.prototype": {
        shortTitle: "Capes",
        rootNodeText: "Capes",
        overlayNodeText: "Capes addicionals",
        baseNodeText: "Capa base"
    },

    "gxp.plugins.LayerManager.prototype": {
        baseNodeText: "Capa base"
    },

    "gxp.plugins.Legend.prototype": { 
        menuText: "Mostra Llegenda",
        tooltip: "Mostra Llegenda"
    },

    "gxp.plugins.LoadingIndicator.prototype": {
        loadingMapMessage: "Loading Map..."
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
        buttonText: "Mesura",
        lengthMenuText: "Longitud",
        areaMenuText: "Àrea",
        lengthTooltip: "Mesura Longitud",
        areaTooltip: "Mesura Àrea",
        measureTooltip: "Mesura"
    },

    "gxp.plugins.Navigation.prototype": {
        menuText: "Desplaça mapa",
        tooltip: "Desplaça mapa"
    },

    "gxp.plugins.NavigationHistory.prototype": {
        previousMenuText: "Vista anterior",
        nextMenuText: "Vista següent",
        previousTooltip: "Vista anterior",
        nextTooltip: "Vista següent"
    },

    "gxp.plugins.OSMSource.prototype": {
        title: "Capes OpenStreetMap",
        mapnikAttribution: "&copy; <a href='http://www.openstreetmap.org/copyright'>OpenStreetMap</a> contributors",
        osmarenderAttribution: "Daded CC-By-SA de <a href='http://openstreetmap.org/'>OpenStreetMap</a>"
    },

    "gxp.plugins.Print.prototype": {
        buttonText:"Imprimeix",
        menuText: "Imprimeix mapa",
        tooltip: "Imprimeix mapa",
        previewText: "Vista prèvia",
        notAllNotPrintableText: "No es poden imprimir totes les capes",
        nonePrintableText: "No es pot imprimir cap de les capes del mapa"
    },

    "gxp.plugins.MapQuestSource.prototype": {
        title: "MapQuest Layers",
        osmAttribution: "Tessel·les cortesia de <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        osmTitle: "MapQuest OpenStreetMap",
        naipAttribution: "Tessel·les cortesia de <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        naipTitle: "MapQuest Imatge"
    },

    "gxp.plugins.QueryForm.prototype": {
        queryActionText: "Consulta",
        queryMenuText: "Consulta layer",
        queryActionTip: "Consulta la capa sel·leccionada",
        queryByLocationText: "Query by current map extent",
        queryByAttributesText: "Consulta per atributs",
        queryMsg: "Consultant...",
        cancelButtonText: "Cancel·la",
        noFeaturesTitle: "Sense coincidències",
        noFeaturesMessage: "La vostra consulta no ha produït resultats."
    },

    "gxp.plugins.RemoveLayer.prototype": {
        removeMenuText: "Elimina Capa",
        removeActionTip: "Elimina Capa"
    },
    
    "gxp.plugins.Styler.prototype": {
        menuText: "Edita Estils",
        tooltip: "Gestiona els estils de les capes"
    },

    "gxp.plugins.WMSGetFeatureInfo.prototype": {
        buttonText:"Identify",
        infoActionTip: "Consulta elements",
        popupTitle: "Informació dels elements"
    },

    "gxp.plugins.Zoom.prototype": {
        zoomMenuText: "Zoom Box",
        zoomInMenuText: "Apropa",
        zoomOutMenuText: "Allunya",
        zoomTooltip: "Zoom by dragging a box",
        zoomInTooltip: "Apropa",
        zoomOutTooltip: "Allunya"
    },
    
    "gxp.plugins.ZoomToExtent.prototype": {
        menuText: "Mostra l'extensió total",
        tooltip: "Mostra l'extensió total"
    },
    
    "gxp.plugins.ZoomToDataExtent.prototype": {
        menuText: "Mostra tota la capa",
        tooltip: "Mostra tota la capa"
    },

    "gxp.plugins.ZoomToLayerExtent.prototype": {
        menuText: "Mostra tota la capa",
        tooltip: "Mostra tota la capa"
    },
    
    "gxp.plugins.ZoomToSelectedFeatures.prototype": {
        menuText: "Mostra els elements seleccionats",
        tooltip: "Mostra els elements seleccionats"
    },

    "gxp.FeatureEditPopup.prototype": {
        closeMsgTitle: "Desitgeu desar els canvis?",
        closeMsg: "Els canvis en aquest element no s'han desat. Desitja desar-los?",
        deleteMsgTitle: "Desitgeu esborrar l'element?",
        deleteMsg: "Esteu segurs de voler esborrar aquest element?",
        editButtonText: "Edita",
        editButtonTooltip: "Fes que aquest element sigui editable",
        deleteButtonText: "Esborra",
        deleteButtonTooltip: "Esborra aquest element",
        cancelButtonText: "Cancel·la",
        cancelButtonTooltip: "Deixa d'editar, descarta els canvis",
        saveButtonText: "Desa",
        saveButtonTooltip: "Desa els canvis"
    },
    
    "gxp.FillSymbolizer.prototype": {
        fillText: "Farcit",
        colorText: "Color",
        opacityText: "Opacitat"
    },
    
    "gxp.FilterBuilder.prototype": {
        builderTypeNames: ["alguna de", "totes", "cap de", "no totes"],
        preComboText: "Acompleix",
        postComboText: "les condicions següents:",
        addConditionText: "afegeix condició",
        addGroupText: "afegeix grup",
        removeConditionText: "treu condició"
    },
    
    "gxp.grid.CapabilitiesGrid.prototype": {
        nameHeaderText : "Nom",
        titleHeaderText : "Títol",
        queryableHeaderText : "Consultable",
        layerSelectionLabel: "Llista les capes de:",
        layerAdditionLabel: "o afegeix un altre servidor.",
        expanderTemplateText: "<p><b>Resum:</b> {abstract}</p>"
    },
    
    "gxp.PointSymbolizer.prototype": {
        graphicCircleText: "cercle",
        graphicSquareText: "quadrat",
        graphicTriangleText: "triangle",
        graphicStarText: "estrella",
        graphicCrossText: "creu",
        graphicXText: "x",
        graphicExternalText: "extern",
        urlText: "URL",
        opacityText: "opacitat",
        symbolText: "Símbol",
        sizeText: "Mides",
        rotationText: "Gir"
    },

    "gxp.QueryPanel.prototype": {
        queryByLocationText: "Consulta per lloc",
        currentTextText: "Vista actual",
        queryByAttributesText: "Consulta per atributs",
        layerText: "Capa"
    },
    
    "gxp.RulePanel.prototype": {
        scaleSliderTemplate: "{scaleType} Escala 1:{scale}",
        labelFeaturesText: "Etiqueta elements",
        labelsText: "Etiquetes",
        basicText: "Bàsic",
        advancedText: "Avançat",
        limitByScaleText: "Restringeix per escala",
        limitByConditionText: "Restringeix per condició",
        symbolText: "Símbol",
        nameText: "Nom"
    },
    
    "gxp.ScaleLimitPanel.prototype": {
        scaleSliderTemplate: "{scaleType} Escala 1:{scale}",
        minScaleLimitText: "Escala mínima",
        maxScaleLimitText: "Escala màxima"
    },
    
    "gxp.StrokeSymbolizer.prototype": {
        solidStrokeName: "continu",
        dashStrokeName: "guions",
        dotStrokeName: "punts",
        titleText: "Traç",
        styleText: "Estil",
        colorText: "Color",
        widthText: "Amplada",
        opacityText: "Opacitad"
    },
    
    "gxp.StylePropertiesDialog.prototype": {   
        titleText: "General",
        nameFieldText: "Nom",
        titleFieldText: "Títol",
        abstractFieldText: "Resum"
    },
    
    "gxp.TextSymbolizer.prototype": {
        labelValuesText: "Etiquetatge",
        haloText: "Halo",
        sizeText: "Mida"
    },
    
    "gxp.WMSLayerPanel.prototype": {
        attributionText: "Attribution",
        aboutText: "Quant a",
        titleText: "Títol",
        nameText: "Nom",
        descriptionText: "Descripció",
        displayText: "Mostra",
        opacityText: "Opacitat",
        formatText: "Format",
        transparentText: "Transparent",
        cacheText: "Caché",
        cacheFieldText: "Utiliza la versió en caché",
        stylesText: "Estils disponibles",
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
        publishMessage: "Ja podeu incloure el vostre mapa a altres webs! Simplement copieu el següent codi HTML allà on desitgeu incrustar-ho:",
        heightLabel: 'Alçària',
        widthLabel: 'Amplada',
        mapSizeLabel: 'Mida',
        miniSizeLabel: 'Mínima',
        smallSizeLabel: 'Petita',
        premiumSizeLabel: 'Premium',
        largeSizeLabel: 'Gran'
   },
    
    "gxp.WMSStylesDialog.prototype": {
        addStyleText: "Afegeix",
        addStyleTip: "Afegeix nou estil",
        chooseStyleText: "Escull estil",
        deleteStyleText: "Treu",
        deleteStyleTip: "Esborra l'estil sel·leccionat",
        editStyleText: "Canvia",
        editStyleTip: "Edita l'estil sel·leccionat",
        duplicateStyleText: "Clona",
        duplicateStyleTip: "Duplica l'estil sel·leccionat",
        addRuleText: "Afegeix",
        addRuleTip: "Afegeix nova regla",
        newRuleText: "Nova regla",
        deleteRuleText: "Treu",
        deleteRuleTip: "Esborra la regla sel·leccionada",
        editRuleText: "Edita",
        editRuleTip: "Edita la regla sel·leccionada",
        duplicateRuleText: "Clona",
        duplicateRuleTip: "Duplica la regla sel·leccionada",
        cancelText: "Cancel·la",
        saveText: "Desa",
        styleWindowTitle: "Estil: {0}",
        ruleWindowTitle: "Regla: {0}",
        stylesFieldsetTitle: "Estils",
        rulesFieldsetTitle: "Regles"
    },

    "gxp.LayerUploadPanel.prototype": {
        titleLabel: "Títol",
        titleEmptyText: "Títol de la capa",
        abstractLabel: "Descripció",
        abstractEmptyText: "Descripció de la capa",
        fileLabel: "Dades",
        fieldEmptyText: "Navegueu per les dades...",
        uploadText: "Puja",
        uploadFailedText: "Upload failed",
        processingUploadText: "Processing upload...",
        waitMsgText: "Pugeu les vostres dades...",
        invalidFileExtensionText: "L'extensió del fitxer ha de ser alguna d'aquestes: ",
        optionsText: "Opcions",
        workspaceLabel: "Espai de treball",
        workspaceEmptyText: "Espai de treball per defecte",
        dataStoreLabel: "Magatzem",
        dataStoreEmptyText: "Create new store",
        defaultDataStoreEmptyText: "Magatzem de dades per defecte"
    },
    
    "gxp.NewSourceDialog.prototype": {
        title: "Afegeix Servidor...",
        cancelText: "Cancel·la",
        addServerText: "Afegeix Servidor",
        invalidURLText: "Enter a valid URL to a WMS endpoint (e.g. http://example.com/geoserver/wms)",
        contactingServerText: "Connectant amb el Servidor..."
    },

    "gxp.ScaleOverlay.prototype": {
        zoomLevelText: "Escala"
    },

    "gxp.Viewer.prototype": {
        saveErrorText: "Problemes desant: "
    },

    "gxp.FeedSourceDialog.prototype": {
        feedTypeText: "Font",
        addPicasaText: "Picasa fotos",
        addYouTubeText: "YouTube Videos",
        addRSSText: "Feed GeoRSS Un altre",
        addFeedText: "Afegeix a Mapa",
        addTitleText: "Títol",
        keywordText: "Paraula clau",
        doneText: "Fet",
        titleText: "Afegir Feeds",
        maxResultsText: "Productes Max"
    }

});
