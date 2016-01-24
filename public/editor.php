<?php
include("html_header.php");
?>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all-notheme.css?51cabb17d7568573"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css?9ae21f2038e3c563"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-flat.css?d597d957caed6c0e"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css?84655ed526dfbc2a"/>
<link rel="stylesheet" type="text/css" href="/js/bootstrap/css/bootstrap.icons.min.css?946b6da947019f90"/>
<link rel='stylesheet' href='//fonts.googleapis.com/css?family=Open+Sans:300' type='text/css'>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">

<!-- build:css /css/build/styles.min.css -->
<link rel="stylesheet" type="text/css" href="/css/styles.css?6b07a34a1f155c03"/>
<!-- /build -->
</head>
<body>
<div id="instructions"></div>
<script type="text/javascript" src="/api/v1/baselayerjs"></script>
<script>
    window.__ = function (string, toolTip) {
        'use strict';
        var str;
        if (typeof gc2i18n !== 'undefined') {
            if (gc2i18n.dict[string]) {
                str = gc2i18n.dict[string];
            } else {
                str = string;
            }
            if (toolTip) {
                str = " <span class='tt' ext:qtip='" + string + "' ext>[?]</span>";
            }
        }
        return str;
    };
    document.write("<script src='/js/i18n/" + window.gc2Al + ".js'><\/script>");
</script>
<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?v=3&libraries=places"></script>
<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js?7eec3ebfb294b86f"></script>
<script type="text/javascript" src="/js/ext/ext-all.js?0035a5fa730b0514"></script>
<script type="text/javascript" src="/js/ext/examples/ux/fileuploadfield/FileUploadField.js?d259f3b931eb8104"></script>
<script type="text/javascript" src="/js/OpenLayers-2.12/OpenLayers.gc2.js?d8c5c284b9bacb96"></script>
<script type="text/javascript" src="/js/canvasResize/binaryajax.js?0bd13c39b3649026"></script>
<script type="text/javascript" src="/js/canvasResize/exif.js?33363140df0199f5"></script>
<script type="text/javascript" src="/js/canvasResize/canvasResize.js?c1543e89ec75a4b0"></script>
<!-- build:js /js/build/editor/all.min.js -->
<script type="text/javascript" src="/js/msg.js?eee550936a4b4231"></script>
<script type="text/javascript" src="/js/GeoExt/script/GeoExt.js?1627edfa794e03c7"></script>
<script type="text/javascript" src="/api/v1/js/api.js?1dde131588eeca14"></script>
<script type="text/javascript" src="/api/v3/js/geocloud.js?af2941abf63d3844"></script>
<script type="text/javascript" src="/js/wfseditor.js?7f7ddf2f21bdf6d4"></script>
<script type="text/javascript" src="/js/attributeform.js?522193f8e55216bb"></script>
<script type="text/javascript" src="/js/filterfield.js?9fab5fb4d6b41f47"></script>
<script type="text/javascript" src="/js/filterbuilder.js?e2b0efb0da913a52"></script>
<script type="text/javascript" src="/js/comparisoncomboBox.js?8542bc57943e21ff"></script>
<script type="text/javascript" src="/js/openlayers/proj4js-combined.js?e3d43fb0b6487682"></script>
<!-- /build -->
</body>
</html>

