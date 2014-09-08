<?php
include("html_header.php");
?>


<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"/>
<link rel="stylesheet" type="text/css" href="/api/v3/css/styles.css"/>
<link rel="stylesheet" type="text/css" href="/js/bootstrap/css/bootstrap.icons.min.css"/>
<style>
    html, body, div, dl, dt, dd, ul, ol, li, h1, h2, h3, h4, h5, h6, pre, form, fieldset, input, p, blockquote, th, td {
        margin: 0;
        padding: 0;
    }

    img, body, html {
        border: 0;
    }

    address, caption, cite, code, dfn, em, strong, th, var {
        font-style: normal;
        font-weight: normal;
    }

    ol, ul {
        list-style: none;
    }

    caption, th {
        text-align: left;
    }

    h1, h2, h3, h4, h5, h6 {
        font-size: 100%;
    }

    q:before, q:after {
        content: '';
    }

    .x-tree-node-expanded .x-tree-node-icon, .x-tree-node-collapsed .x-tree-node-icon {
        background-image: url(/js/bootstrap/img/glyphicons-halflings.png);
        display: inline-block;
        width: 14px;
        height: 14px;
        *margin-right: .3em;
        line-height: 10px;
        vertical-align: text-top;
        background-repeat: no-repeat;
        margin-top: -1px;
    }

    .x-tree-node-expanded .x-tree-node-icon {
        background-position: -408px -120px;
    }

    .x-tree-node-collapsed .x-tree-node-icon {
        background-position: -384px -120px;
    }

    .x-tree-no-lines .x-tree-elbow-end-minus, .x-tree-no-lines .x-tree-elbow-minus, .x-tree-no-lines .x-tree-elbow-end-plus, .x-tree-no-lines .x-tree-elbow-plus {
        background-image: url(/js/bootstrap/img/glyphicons-halflings.png);
        display: inline-block;
        width: 14px;
        height: 14px;
        *margin-right: .3em;
        line-height: 10px;
        vertical-align: text-top;
        background-repeat: no-repeat;
        margin-top: -1px;
    }

    .x-tree-no-lines .x-tree-elbow-end-plus, .x-tree-no-lines .x-tree-elbow-plus {
        background-position: 0 -96px;
    }

    .x-tree-no-lines .x-tree-elbow-end-minus, .x-tree-no-lines .x-tree-elbow-minus {
        background-position: -24px -96px;
    }

    .x-tree-node-icon {
        display: none;
    }

    .btn-gc {
        margin-top: -1px !important;
    }

    #upload a {
        z-index: 2000;
        position: absolute;
        bottom: 20px;
        display: block;
        color: #000000;
        text-decoration: none;

    }

    #upload img {
        position: relative;
        width: 100px;
        left: 40px;
    }

    #upload div {
        left: 10px;
        position: relative;
        font-family: verdana, arial, sans-serif;
        font-size: 7.5pt;

    }

    .layer-desc {
        font-family: verdana, arial, sans-serif;
        font-size: 7.5pt;
        padding: 10px;
        color: #ffffff;
        background-color: rgb(119, 119, 119);
    }

    .ol-zoom, .olControlZoom {
        background: #000000 !important;
        opacity: 0.7;
    }

    .ol-zoom-in, .ol-zoom-out, .olControlZoomIn, .olControlZoomOut {
        height: 30px !important;
        width: 30px !important;
        font-weight: bold;
        text-decoration: none;
    }

    .ol-zoom a, .ol-zoom a:hover, .olControlZoom a {
        font-size: 16px !important;
        font-weight: bold !important;
        background: none !important;
        line-height: 28px !important;
        text-decoration: none;
        color: white;
    }
    .olControlAttribution {
        bottom: 0 !important;
        font-family: verdana, arial, sans-serif !important;
        font-size: 7.5pt !important;
    }
    .pac-container{
        z-index: 10000;
    }
</style>
</head>
<body>
<div id="instructions"></div>
<div id="upload">
    <a href="#">
        <img src="/assets/images/upload_black.png">
        <div>.shp .geojson .gml .kml .tab .mif</div>
    </a>
</div>
<script type="text/javascript" src="/api/v1/baselayerjs"></script>
<script type="text/javascript" src="/api/v1/js/api.js"></script>
<script type="text/javascript" src="/js/wfseditor.js"></script>
<script type="text/javascript" src="/js/attributeform.js"></script>
<script type="text/javascript" src="/js/filterfield.js?format=txt"></script>
<script type="text/javascript" src="/js/filterbuilder.js?format=txt"></script>
<script type="text/javascript" src="/js/comparisoncomboBox.js?format=txt"></script>
<script type="text/javascript" src="/js/openlayers/proj4js-combined.js"></script>
<script type="text/javascript" src="//maps.google.com/maps/api/js?v=3&sensor=false&libraries=places"></script>
<script type="text/javascript" src="/api/v3/js/geocloud.js" type="text/javascript"></script>
<?php
include("html_footer.php");
?>
