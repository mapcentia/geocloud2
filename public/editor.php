<?php
include("html_header.php");
?>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css?9ae21f2038e3c563"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css?49593e1feb591d0b"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css?84655ed526dfbc2a"/>
<link rel="stylesheet" type="text/css" href="/js/bootstrap/css/bootstrap.icons.min.css?946b6da947019f90"/>
<link rel="stylesheet" type="text/css" href="/api/v3/css/styles.css?b1639e04e4f6da62"/>
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
<script type="text/javascript" src="//maps.google.com/maps/api/js?v=3&sensor=false&libraries=places"></script>
<script type="text/javascript" src="/api/v1/js/api.js?be90740c4a1f891e"></script>
<!-- build:js /api/v3/js/geocloud.min.js -->
<script type="text/javascript" src="/api/v3/js/geocloud.js?a439ffa6e8bc2dd0" type="text/javascript"></script>
<!-- /build -->
<!-- build:js /js/build/editor/all.min.js -->
<script type="text/javascript" src="/js/wfseditor.js?3cfc745d190f76ac"></script>
<script type="text/javascript" src="/js/attributeform.js?257465ed180e5e84"></script>
<script type="text/javascript" src="/js/filterfield.js?9fab5fb4d6b41f47"></script>
<script type="text/javascript" src="/js/filterbuilder.js?e2b0efb0da913a52"></script>
<script type="text/javascript" src="/js/comparisoncomboBox.js?8542bc57943e21ff"></script>
<script type="text/javascript" src="/js/openlayers/proj4js-combined.js?e3d43fb0b6487682"></script>
<!-- /build -->
</body>
</html>

