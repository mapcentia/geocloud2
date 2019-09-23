<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include("html_header.php");
use \app\conf\App;
?>

<link rel="stylesheet" href="//fonts.googleapis.com/css?family=Open+Sans:700,300">
<link rel="stylesheet" type="text/css"
      href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">

<link rel="stylesheet" href="/js/ext/examples/ux/superboxselect/superboxselect.css?d9fa260554f90c1f"/>
<link rel="stylesheet" href="/js/ext/resources/css/ext-all-notheme.css?51cabb17d7568573"/>
<link rel="stylesheet" href="/js/ext/resources/css/xtheme-dark.css?d597d957caed6c0e"/>
<link rel="stylesheet" href="/js/ext/examples/ux/gridfilters/css/GridFilters.css?fb821750e712f717"/>
<link rel="stylesheet" href="/js/ext/examples/ux/gridfilters/css/RangeMenu.css?d9fa260554f90c1f"/>

<!-- build:css /css/build/styles.min.css -->
<link rel="stylesheet" href="/js/bootstrap/css/bootstrap.icons.min.css?946b6da947019f90"/>
<link rel="stylesheet" href="/css/jquery.plupload.queue.css?0883487d9fdc30c9"/>
<link rel="stylesheet" href="/css/styles.css?6b07a34a1f155c03"/>
<!-- /build -->

</head>
<body>
<div id="loadscreen">
    <div class="center text">
        <div id="loadscreentext"></div>
    </div>
    <div class="center bounce">
        <div class="bouncywrap">
            <div class="dotcon dc1">
                <div class="dot"></div>
            </div>
            <div class="dotcon dc2">
                <div class="dot"></div>
            </div>
            <div class="dotcon dc3">
                <div class="dot"></div>
            </div>
        </div>

    </div>
</div>
<div style="display:none">
    <div id="map-settings"></div>
    <div id="authentication">
        HTTP Basic auth password for WMS and WFS
    </div>
    <div id="apikey">
        API key. This is used for the SQL API. You can always change the key
        <br>
        <br>
        <b><span id='apikeyholder'></span></b>
    </div>
    <div id="wfs-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>For WFS-T (editable WFS). This service supports workflow management and track changes.</td>
            </tr>
            <tr>
                <td>
                    <input class="service-url" type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/wfs/<?php echo ($_SESSION['subuser'] ? $_SESSION['subuser'] . "@" : "") . $_SESSION['screen_name']; ?>/<?php echo (\app\inc\Input::getPath()->part(3)) ? \app\inc\Input::getPath()->part(3) : "public"; ?>/<?php echo (\app\conf\App::$param["epsg"]) ?: "4326" ?>"
                    />
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="wms-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>The OWS services includes WMS (up to 1.3) and WFS (up to 2.0).</td>
            </tr>
            <tr>
                <td>
                    <input class="service-url" type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/ows/<?php echo ($_SESSION['subuser'] ? $_SESSION['subuser'] . "@" : "") . $_SESSION['screen_name']; ?>/<?php echo (\app\inc\Input::getPath()->part(3)) ?: "public"; ?>/"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="tms-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>Tile Map Service (WMTS, TMS and Google xyz).</td>
            </tr>
            <tr>
                <td>
                    <input class="service-url" type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/mapcache/<?php echo ($_SESSION['subuser'] ? $_SESSION['subuser'] . "@" : "") . $_SESSION['screen_name']; ?>/[wms|wmts|gmaps|tms]"
                    />
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="sql-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>The HTTP SQL API end point.</td>
            </tr>
            <tr>
                <td>
                    <input class="service-url" type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/sql/<?php echo ($_SESSION['subuser'] ? $_SESSION['subuser'] . "@" : "") . $_SESSION['screen_name']; ?>?q=[query]&key=[your_api_key]"
                    />
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="elasticsearch-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>Elasticsearch API end point.</td>
            </tr>
            <tr>
                <td>
                    <input class="service-url" type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/elasticsearch/[map|bulk|search|delete]/<?php echo $_SESSION['screen_name']; ?>/[index]/[type]"
                    />
                </td>
            </tr>
            <tr>
                <td>map: PUT, bulk: POST, search: GET, delete: DELETE</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
<div id="spinner"><span></span><i class="fa fa-cog fa-spin fa-3x fa-fw"></i></div>
<script src="/api/v1/baselayerjs"></script>
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
                str = " <span class='tt' ext:qtip='" + str + "' ext>[?]</span>";
            }
        }
        return str;
    };
    document.write("<script src='/js/i18n/" + window.gc2Al + ".js'><\/script>");
</script>

<script>
    document.getElementById("loadscreentext").innerHTML = __("GC2 Admin is loading. Hang on...");
</script>

<script src="//maps.googleapis.com/maps/api/js?key=<?php echo App::$param["googleApiKey"]; ?>&v=3&libraries=places"></script>
<script src="/js/OpenLayers-2.12/OpenLayers.gc2.js?d8c5c284b9bacb96"></script>

<!-- build:js /js/admin/build/all.min.js -->
<script src="/js/canvasResize/binaryajax.js?0bd13c39b3649026"></script>
<script src="/js/canvasResize/exif.js?33363140df0199f5"></script>
<script src="/js/canvasResize/canvasResize.js?c1543e89ec75a4b0"></script>
<script src="/js/ext/adapter/ext/ext-base-debug.js?7eec3ebfb294b86f"></script>
<script src="/js/ext/ext-all-debug.js?0035a5fa730b0514"></script>
<script src="/js/ext/examples/ux/fileuploadfield/FileUploadField.js"></script>
<script src="/js/ext/examples/ux/Spinner.js?00006e0276bf36d4"></script>
<script src="/js/ext/examples/ux/SpinnerField.js?12cd89e35dc66bc2"></script>
<script src="/js/ext/examples/ux/CheckColumn.js?7ba8b5b8eb4a6981"></script>
<script src="/js/ext/examples/ux/gridfilters/menu/RangeMenu.js?77e3a4d93b747edc"></script>
<script src="/js/ext/examples/ux/gridfilters/menu/ListMenu.js?606a1414d8824c81"></script>
<script src="/js/ext/examples/ux/superboxselect/SuperBoxSelect.js"></script>
<script src="/js/ext/examples/ux/gridfilters/GridFilters.js?e2cd680acbd6d211"></script>
<script src="/js/ext/examples/ux/gridfilters/filter/Filter.js?91c56cbc41e461f1"></script>
<script src="/js/ext/examples/ux/gridfilters/filter/StringFilter.js?94547080e205a1bb"></script>
<script src="/js/jquery/1.10.0/jquery.min.js?c1c829b72179d9c3"></script>
<script src="/js/GeoExt/script/GeoExt.js?1627edfa794e03c7"></script>
<script src="/js/openlayers/proj4js-combined.js?e3d43fb0b6487682"></script>
<script src="/js/plupload/js/moxie.min.js?5eb0c30ea42430c9"></script>
<script src="/js/plupload/js/plupload.min.js?745552fc001e46c4"></script>
<script src="/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js?78b92aab54b9f806"></script>
<script src="/js/admin/msg.js?eee550936a4b4231"></script>
<script src="/js/admin/admin.js?f70703cd9c4737fa"></script>
<script src="/js/admin/edittablestructure.js?16bbb4ace3bc3977"></script>
<script src="/js/admin/elasticsearchmapping.js?9d7efee6c8406f63"></script>
<script src="/js/admin/editwmsclass.js?78db6f79d8ee26a1"></script>
<script src="/js/admin/editwmslayer.js?6cd3fec277824e02"></script>
<script src="/js/admin/edittilelayer.js?6cd3fec277824e02"></script>
<script src="/js/admin/classwizards.js?b8da68ac7ecfbe3b"></script>
<script src="/js/admin/addshapeform.js?a1a04d59aa4b5edb"></script>
<script src="/js/admin/addbitmapform.js?38dde20cbaec7c80"></script>
<script src="/js/admin/addrasterform.js?e9f707ad6e5100ff"></script>
<script src="/js/admin/addfromscratch.js?e4a993729b4639ad"></script>
<script src="/js/admin/addviewform.js?d223f9da67a51165"></script>
<script src="/js/admin/addosmform.js?6fa514ebc5d91d01"></script>
<script src="/js/admin/addqgisform.js?6fa514ebc5d91d01"></script>
<script src="/js/admin/colorfield.js?4c80541098c1f93d"></script>
<script src="/js/admin/httpauthform.js?2acab5ae23de010f"></script>
<script src="/js/admin/apikeyform.js?9485c6f6a26fab43"></script>
<script src="/js/admin/attributeform.js?522193f8e55216bb"></script>
<script src="/js/admin/filterfield.js?9fab5fb4d6b41f47"></script>
<script src="/js/admin/filterbuilder.js?e2b0efb0da913a52"></script>
<script src="/js/admin/comparisoncomboBox.js?8542bc57943e21ff"></script>
<script src="/js/openlayers/defs/EPSG3857.js"></script>
<!-- /build -->

<script src="/api/v1/js/api.js?1dde131588eeca14"></script>
<script src="/api/v3/js/geocloud.js?af2941abf63d3844"></script>

</body>
</html>

