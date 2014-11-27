<?php
include("html_header.php");
?>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css?9ae21f2038e3c563"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css?49593e1feb591d0b"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css?84655ed526dfbc2a"/>
<link rel="stylesheet" type="text/css" href="/js/bootstrap/css/bootstrap.icons.min.css?946b6da947019f90"/>
<!-- build:css /css/build/styles.min.css -->
<link rel="stylesheet" type="text/css" href="/css/styles.css?cb4483ba51351535"/>
<!-- /build -->
</head>
<body>
<div id="instructions"></div>
<div id="upload">
    <a href="#">
        <img src="/assets/images/upload_black.png?00f5d5e5a884600f">
        <div>.shp .geojson .gml .kml .tab .mif</div>
    </a>
</div>
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
<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places"></script>
<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js?7453012a468a8a38"></script>
<script type="text/javascript" src="/js/ext/ext-all.js?0035a5fa730b0514"></script>
<script type="text/javascript" src="/js/openlayers/OpenLayers.js?8b397f6ff00cc458"></script>
<!-- build:js /js/build/editor/all.min.js -->
<script type="text/javascript" src="/js/msg.js?e6ae61b3f174052d"></script>
<script type="text/javascript" src="/js/GeoExt/script/GeoExt.js?0494cd822345d162"></script>
<script type="text/javascript" src="/api/v1/js/api.js?9890f37fd070dd05"></script>
<script type="text/javascript" src="/api/v3/js/geocloud.js?b7b47372b32dde62" type="text/javascript"></script>
<script type="text/javascript" src="/js/wfseditor.js?be0adf348442f73a"></script>
<script type="text/javascript" src="/js/attributeform.js?257465ed180e5e84"></script>
<script type="text/javascript" src="/js/filterfield.js?9fab5fb4d6b41f47"></script>
<script type="text/javascript" src="/js/filterbuilder.js?e2b0efb0da913a52"></script>
<script type="text/javascript" src="/js/comparisoncomboBox.js?8542bc57943e21ff"></script>
<script type="text/javascript" src="/js/openlayers/proj4js-combined.js?e3d43fb0b6487682"></script>
<!-- /build -->
</body>
</html>

