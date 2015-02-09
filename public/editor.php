<?php
include("html_header.php");
?>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css?9ae21f2038e3c563"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css?49593e1feb591d0b"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css?84655ed526dfbc2a"/>
<link rel="stylesheet" type="text/css" href="/js/bootstrap/css/bootstrap.icons.min.css?946b6da947019f90"/>
<!-- build:css /css/build/styles.min.css -->
<link rel="stylesheet" type="text/css" href="/css/styles.css?e240952495887feb"/>
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
<script type="text/javascript" src="//maps.googleapis.com/maps/api/js?v=3.exp&sensor=false&libraries=places"></script>
<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js?7453012a468a8a38"></script>
<script type="text/javascript" src="/js/ext/ext-all.js?0035a5fa730b0514"></script>
<script type="text/javascript" src="/js/openlayers/OpenLayers.js?8b397f6ff00cc458"></script>
<!-- build:js /js/build/editor/all.min.js -->
<script type="text/javascript" src="/js/msg.js?e6ae61b3f174052d"></script>
<script type="text/javascript" src="/js/GeoExt/script/GeoExt.js?0494cd822345d162"></script>
<script type="text/javascript" src="/api/v1/js/api.js?9890f37fd070dd05"></script>
<script type="text/javascript" src="/api/v3/js/geocloud.js?91d5c4b0a2fc30d9" type="text/javascript"></script>
<script type="text/javascript" src="/js/wfseditor.js?29465d09f1460950"></script>
<script type="text/javascript" src="/js/attributeform.js?257465ed180e5e84"></script>
<script type="text/javascript" src="/js/filterfield.js?9fab5fb4d6b41f47"></script>
<script type="text/javascript" src="/js/filterbuilder.js?e2b0efb0da913a52"></script>
<script type="text/javascript" src="/js/comparisoncomboBox.js?8542bc57943e21ff"></script>
<script type="text/javascript" src="/js/openlayers/proj4js-combined.js?e3d43fb0b6487682"></script>
<!-- /build -->
</body>
</html>

