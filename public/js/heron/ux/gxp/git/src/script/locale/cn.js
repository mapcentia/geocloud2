/**
 * @requires GeoExt/Lang.js
 */

GeoExt.Lang.add("en", {

    "gxp.menu.LayerMenu.prototype": {
        layerText: "图层"
    },

    "gxp.plugins.AddLayers.prototype": {
        addActionMenuText: "添加图层",
        addActionTip: "添加图层",
        addServerText: "添加新服务器",
        addButtonText: "添加图层",
        untitledText: "无标题",
        addLayerSourceErrorText: "WMS获取发生错误 ({msg}).\n请检查URL并重试",
        availableLayersText: "现有图层",
        expanderTemplateText: "<p><b>简介:</b> {简介}</p>",
        panelTitleText: "标题",
        layerSelectionText: "查看现有数据:",
        doneText: "完成",
        uploadText: "上传图层",
        addFeedActionMenuText: "Add feeds",
        searchText: "Search for layers"
    },
    
    "gxp.plugins.BingSource.prototype": {
        title: "Bing图层",
        roadTitle: "Bing道路",
        aerialTitle: "Bing航拍图片",
        labeledAerialTitle: "Bing航拍图片带标记"
    },

    "gxp.plugins.FeatureEditor.prototype": {
        splitButtonText: "编辑",
        createFeatureActionText: "创建",
        editFeatureActionText: "修改",
        createFeatureActionTip: "创建新图形",
        editFeatureActionTip: "修改已存在图形",
        commitTitle: "Commit message",
        commitText: "Please enter a commit message for this edit:"
    },
    
    "gxp.plugins.FeatureGrid.prototype": {
        displayFeatureText: "在地图上显示",
        firstPageTip: "第一页",
        previousPageTip: "前一页",
        zoomPageExtentTip: "聚焦到页面尺寸",
        nextPageTip: "下一页",
        lastPageTip: "最后一页",
        totalMsg: "图形 {1} 到 {2} 从 {0}"
    },

    "gxp.plugins.GoogleEarth.prototype": {
        menuText: "3D视角",
        tooltip: "切换到3D视角"
    },
    
    "gxp.plugins.GoogleSource.prototype": {
        title: "Google图层",
        roadmapAbstract: "显示街道",
        satelliteAbstract: "显示卫星图",
        hybridAbstract: "显示卫星图及街道名称",
        terrainAbstract: "显示街道和地形"
    },

    "gxp.plugins.LayerProperties.prototype": {
        menuText: "图层属性",
        toolTip: "图层属性"
    },
    
    "gxp.plugins.LayerTree.prototype": {
        shortTitle: "图层",
        rootNodeText: "图层",
        overlayNodeText: "叠加",
        baseNodeText: "基图层"
    },

    "gxp.plugins.Legend.prototype": {
        menuText: "显示图例",
        tooltip: "显示图例"
    },

    "gxp.plugins.LoadingIndicator.prototype": {
        loadingMapMessage: "读取地图..."
    },

    "gxp.plugins.MapBoxSource.prototype": {
        title: "MapBox图层",
        blueMarbleTopoBathyJanTitle: "Blue Marble Topography & Bathymetry (January)蓝大理石地形图和湖盆图（一月）",
        blueMarbleTopoBathyJulTitle: "Blue Marble Topography & Bathymetry (July)蓝大理石地形图和湖盆图（七月)",
        blueMarbleTopoJanTitle: "Blue Marble Topography (January)蓝大理石地形图（一月）",
        blueMarbleTopoJulTitle: "Blue Marble Topography (July)蓝大理石地形图（七月）",
        controlRoomTitle: "Control Room控制室",
        geographyClassTitle: "Geography Class地理课",
        naturalEarthHypsoTitle: "Natural Earth Hypsometric",
        naturalEarthHypsoBathyTitle: "Natural Earth Hypsometric & Bathymetry",
        naturalEarth1Title: "Natural Earth I",
        naturalEarth2Title: "Natural Earth II",
        worldDarkTitle: "World Dark",
        worldLightTitle: "World Light",
        worldPrintTitle: "World Print"
    },

    "gxp.plugins.Measure.prototype": {
        buttonText: "测量",
        lengthMenuText: "长度",
        areaMenuText: "面积",
        lengthTooltip: "测量长度",
        areaTooltip: "测量面积",
        measureTooltip: "测量"
    },

    "gxp.plugins.Navigation.prototype": {
        menuText: "平移地图",
        tooltip: "平移地图"
    },

    "gxp.plugins.NavigationHistory.prototype": {
        previousMenuText: "聚焦到前一尺寸",
        nextMenuText: "聚集到下一尺寸",
        previousTooltip: "聚焦到前一尺寸",
        nextTooltip: "聚焦到下一尺寸"
    },

    "gxp.plugins.OSMSource.prototype": {
        title: "OpenStreetMap图层",
        mapnikAttribution: "CC-By-SA数据<a href='http://openstreetmap.org/'>OpenStreetMap</a>",
        osmarenderAttribution: "CC-By-SA数据<a href='http://openstreetmap.org/'>OpenStreetMap</a>"
    },

    "gxp.plugins.Print.prototype": {
        buttonText:"打印",
        menuText: "打印地图",
        tooltip: "打印地图",
        previewText: "打印预览",
        notAllNotPrintableText: "并非所有图层都可打印",
        nonePrintableText: "现有地图中的图层都不可打印"
    },

    "gxp.plugins.MapQuestSource.prototype": {
        title: "MapQuest图层",
        osmAttribution: "栅格获取自 <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        osmTitle: "MapQuest OpenStreetMap",
        naipAttribution: "栅格获取自 <a href='http://open.mapquest.co.uk/' target='_blank'>MapQuest</a> <img src='http://developer.mapquest.com/content/osm/mq_logo.png' border='0'>",
        naipTitle: "MapQuest图片"
    },

    "gxp.plugins.QueryForm.prototype": {
        queryActionText: "查询",
        queryMenuText: "查询图层",
        queryActionTip: "查询被选图层",
        queryByLocationText: "根据现有地图尺寸查询",
        queryByAttributesText: "根据属性查询",
        queryMsg: "查询中...",
        cancelButtonText: "取消",
        noFeaturesTitle: "没有匹配对象",
        noFeaturesMessage: "您的查询未返回任何结果"
    },

    "gxp.plugins.RemoveLayer.prototype": {
        removeMenuText: "删除图层",
        removeActionTip: "删除图层"
    },
    
    "gxp.plugins.Styler.prototype": {
        menuText: "修改式样",
        tooltip: "管理图层式样"

    },

    "gxp.plugins.WMSGetFeatureInfo.prototype": {
        buttonText:"识别",
        infoActionTip: "获取图形信息",
        popupTitle: "图形信息"
    },

    "gxp.plugins.Zoom.prototype": {
        zoomMenuText: "聚焦框",
        zoomInMenuText: "放大",
        zoomOutMenuText: "缩小",
        zoomTooltip: "跟据划框聚焦",
        zoomInTooltip: "放大",
        zoomOutTooltip: "缩小"
    },
    
    "gxp.plugins.ZoomToExtent.prototype": {
        menuText: "聚焦到最大尺寸",
        tooltip: "聚焦到最大尺寸"
    },
    
    "gxp.plugins.ZoomToDataExtent.prototype": {
        menuText: "聚焦到图层尺寸",
        tooltip: "聚焦到图层尺寸"
    },

    "gxp.plugins.ZoomToLayerExtent.prototype": {
        menuText: "聚焦到图层尺寸",
        tooltip: "聚焦到图层尺寸"
    },
    
    "gxp.plugins.ZoomToSelectedFeatures.prototype": {
        menuText: "聚焦到被选图形",
        tooltip: "聚焦到被选图形"
    },

    "gxp.FeatureEditPopup.prototype": {
        closeMsgTitle: "保存修改?",
        closeMsg: "图形修改未被保存,您打算保存这些修改么？",
        deleteMsgTitle: "删除图形?",
        deleteMsg: "您确定要删除这些图形？",
        editButtonText: "修改",
        editButtonTooltip: "使此图形可编辑",
        deleteButtonText: "删除",
        deleteButtonTooltip: "删除这一图形",
        cancelButtonText: "取消",
        cancelButtonTooltip: "停止编辑,放弃修改",
        saveButtonText: "保存",
        saveButtonTooltip: "保存修改"
    },
    
    "gxp.FillSymbolizer.prototype": {
        fillText: "填满",
        colorText: "颜色",
        opacityText: "透明度"
    },
    
    "gxp.FilterBuilder.prototype": {
        builderTypeNames: ["任何", "全部", "没有", "非全部"],
        preComboText: "匹配",
        postComboText: "自下列:",
        addConditionText: "添加条件",
        addGroupText: "添加组",
        removeConditionText: "去除条件"
    },
    
    "gxp.grid.CapabilitiesGrid.prototype": {
        nameHeaderText : "名字",
        titleHeaderText : "标题",
        queryableHeaderText : "可查询",
        layerSelectionLabel: "查看现有数据",
        layerAdditionLabel: "或添加新服务器",
        expanderTemplateText: "<p><b>简介:</b> {简介:}</p>"
    },
    
    "gxp.PointSymbolizer.prototype": {
        graphicCircleText: "圆",
        graphicSquareText: "方",
        graphicTriangleText: "三角",
        graphicStarText: "星",
        graphicCrossText: "十字",
        graphicXText: "叉",
        graphicExternalText: "外部",
        urlText: "URL",
        opacityText: "透明度",
        symbolText: "标志",
        sizeText: "尺寸",
        rotationText: "旋转"
    },

    "gxp.QueryPanel.prototype": {
        queryByLocationText: "根据方位查询",
        currentTextText: "现有尺寸",
        queryByAttributesText: "根据属性查询",
        layerText: "图层"
    },
    
    "gxp.RulePanel.prototype": {
        scaleSliderTemplate: "{scaleType}比例 1:{scale}",
        labelFeaturesText: "标记图形",
        labelsText: "标记",
        basicText: "基本",
        advancedText: "高级",
        limitByScaleText: "用比例尺筛选",
        limitByConditionText: "用条件筛选",
        symbolText: "标志",
        nameText: "名字"
    },
    
    "gxp.ScaleLimitPanel.prototype": {
        scaleSliderTemplate: "{scaleType} 比例 1:{scale}",
        minScaleLimitText: "最小比例极限",
        maxScaleLimitText: "最大比例极限"
    },
    
    "gxp.StrokeSymbolizer.prototype": {
        solidStrokeName: "实线",
        dashStrokeName: "虚线",
        dotStrokeName: "点线",
        titleText: "线宽",
        styleText: "样式",
        colorText: "颜色",
        widthText: "宽度",
        opacityText: "透明度"
    },
    
    "gxp.StylePropertiesDialog.prototype": {   
        titleText: "常规",
        nameFieldText: "名称",
        titleFieldText: "标题",
        abstractFieldText: "简介"
    },
    
    "gxp.TextSymbolizer.prototype": {
        labelValuesText: "标记数值",
        haloText: "光晕",
        sizeText: "尺寸"
    },
    
    "gxp.WMSLayerPanel.prototype": {
        attributionText: "Attribution",
        aboutText: "关于",
        titleText: "标题",
        nameText: "名字",
        descriptionText: "描述",
        displayText: "显示",
        opacityText: "半透明",
        formatText: "格式",
        transparentText: "透明",
        cacheText: "缓存",
        cacheFieldText: "使用缓存版本",
        stylesText: "Available styles",
        infoFormatText: "格式信息",
        infoFormatEmptyText: "选择一种格式",
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
        publishMessage: "您的地图已经可以在网上发布！请拷贝以下HTML以将地图插入您的网站",
        heightLabel: '高',
        widthLabel: '宽',
        mapSizeLabel: '地图大小',
        miniSizeLabel: '迷你',
        smallSizeLabel: '小',
        premiumSizeLabel: '最佳',
        largeSizeLabel: '大'
    },
    
    "gxp.WMSStylesDialog.prototype": {
         addStyleText: "添加",
         addStyleTip: "添加新式样",
         chooseStyleText: "选择式样",
         deleteStyleText: "移除",
         deleteStyleTip: "删除被选式样",
         editStyleText: "修改",
         editStyleTip: "修改被选式样",
         duplicateStyleText: "复制",
         duplicateStyleTip: "复制被选式样",
         addRuleText: "添加",
         addRuleTip: "添加新规则",
         newRuleText: "新规则",
         deleteRuleText: "移除",
         deleteRuleTip: "删除被选规则",
         editRuleText: "修改",
         editRuleTip: "修改被选规则",
         duplicateRuleText: "复制",
         duplicateRuleTip: "复制被选规则",
         cancelText: "取消",
         saveText: "保存",
         styleWindowTitle: "用户式样: {0}",
         ruleWindowTitle: "式样规则: {0}",
         stylesFieldsetTitle: "式样",
         rulesFieldsetTitle: "规则"
    },

    "gxp.LayerUploadPanel.prototype": {
        titleLabel: "标题",
        titleEmptyText: "图层标题",
        abstractLabel: "描述",
        abstractEmptyText: "图层描述",
        fileLabel: "数据",
        fieldEmptyText: "浏览数据档案...",
        uploadText: "上传",
        uploadFailedText: "Upload failed",
        processingUploadText: "Processing upload...",
        waitMsgText: "上传您的数据",
        invalidFileExtensionText: "文件后缀名必须是: ",
        optionsText: "选项",
        workspaceLabel: "工作区",
        workspaceEmptyText: "默认工作区",
        dataStoreLabel: "数据包",
        dataStoreEmptyText: "生成新数据包",
        defaultDataStoreEmptyText: "默认数据包"
    },
    
    "gxp.NewSourceDialog.prototype": {
        title: "添加新服务器...",
        cancelText: "取消",
        addServerText: "添加服务器",
        invalidURLText: "请添加有效的URL以联接WMS端点(比如http://example.com/geoserver/wms)",
        contactingServerText: "联接服务器中..."
    },

    "gxp.ScaleOverlay.prototype": { 
        zoomLevelText: "聚焦度"
    },

    "gxp.Viewer.prototype": {
        saveErrorText: "Trouble saving: "
    },

    "gxp.FeedSourceDialog.prototype": {
        feedTypeText:"源",
        addPicasaText:"Picasa照片",
        addYouTubeText:"YouTube視頻",
        addRSSText:"其他的GeoRSS飼料",
        addFeedText:"地圖",
        addTitleText:"標題",
        keywordText:"關鍵字",
        doneText:"完成",
        titleText:"添加訂閱",
        maxResultsText:"最大項目"
    }

});
