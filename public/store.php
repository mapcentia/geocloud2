<?php
include("html_header.php");
?>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all-notheme.css?51cabb17d7568573"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-flat.css?d597d957caed6c0e"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css?84655ed526dfbc2a"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/ux/gridfilters/css/GridFilters.css?fb821750e712f717" />
<link rel="stylesheet" type="text/css" href="/js/ext/examples/ux/gridfilters/css/RangeMenu.css?d9fa260554f90c1f" />
<link rel="stylesheet" type="text/css" href="/js/bootstrap/css/bootstrap.icons.min.css?946b6da947019f90"/>
<link rel="stylesheet" type="text/css" href="/css/jquery.plupload.queue.css?0883487d9fdc30c9"/>
<link rel='stylesheet' href='//fonts.googleapis.com/css?family=Open+Sans:300' type='text/css'>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">


<!-- build:css /css/build/styles.min.css -->
<link rel="stylesheet" type="text/css" href="/css/styles.css?6b07a34a1f155c03"/>
<!-- /build -->
</head>
<body>
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
        <table border="0" class="pretty-tables">
            <tbody>
            <tr>
                <td>For WFS-T (editable WFS). This service supports workflow management and track changes.</td>
            </tr>
            <tr>
                <td>
                    <input type="text" readonly="readonly"
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
                    <input type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/ows/<?php echo ($_SESSION['subuser'] ? $_SESSION['subuser'] . "@" : "") . $_SESSION['screen_name']; ?>/<?php echo (\app\inc\Input::getPath()->part(3)) ? : "public"; ?>/"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="tms-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>Tile Map Service (TMS, Google style).</td>
            </tr>
            <tr>
                <td>
                    <input type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ?: "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/wms/mydb/tilecache/1.0.0/{layer}"
                           />
                </td>
            </tr>
            <tr>
                <td>Eg. <?php echo $_SERVER['HTTP_HOST']; ?>/wms/mydb/tilecache/1.0.0/<?php echo (\app\inc\Input::getPath()->part(3)) ? : "public"; ?>.mylayer</td>
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
                    <input type="text" readonly="readonly"
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
                    <input type="text" readonly="readonly"
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
<div id="spinner"><span></span><img src='/assets/images/ajax_loader.gif?5c2beec3d6a058e0'/></div>
<script type="text/javascript" src="/api/v1/baselayerjs"></script>
<script>window.__ = function (string, toolTip) {
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
<script type="text/javascript" src="/js/ext/adapter/ext/ext-base-debug.js?7eec3ebfb294b86f"></script>
<script type="text/javascript" src="/js/ext/ext-all.js?0035a5fa730b0514"></script>
<script type="text/javascript" src="/js/ext/examples/ux/Spinner.js?00006e0276bf36d4"></script>
<script type="text/javascript" src="/js/ext/examples/ux/SpinnerField.js?12cd89e35dc66bc2"></script>
<script type="text/javascript" src="/js/ext/examples/ux/CheckColumn.js?7ba8b5b8eb4a6981"></script>
<script type="text/javascript" src="/js/ext/examples/ux/gridfilters/menu/RangeMenu.js?77e3a4d93b747edc"></script>
<script type="text/javascript" src="/js/ext/examples/ux/gridfilters/menu/ListMenu.js?606a1414d8824c81"></script>

<script type="text/javascript" src="/js/ext/examples/ux/gridfilters/GridFilters.js?e2cd680acbd6d211"></script>
<script type="text/javascript" src="/js/ext/examples/ux/gridfilters/filter/Filter.js?91c56cbc41e461f1"></script>
<script type="text/javascript" src="/js/ext/examples/ux/gridfilters/filter/StringFilter.js?94547080e205a1bb"></script>

<!-- build:js /js/build/store/all.min.js -->
<script type="text/javascript" src="/js/jquery/1.10.0/jquery.min.js?c1c829b72179d9c3"></script>
<script type="text/javascript" src="/js/msg.js?eee550936a4b4231"></script>
<script type="text/javascript" src="/js/store.js?f70703cd9c4737fa"></script>
<script type="text/javascript" src="/js/edittablestructure.js?16bbb4ace3bc3977"></script>
<script type="text/javascript" src="/js/cartomobilesetup.js?18b434a3917cddf4"></script>
<script type="text/javascript" src="/js/elasticsearchmapping.js?9d7efee6c8406f63"></script>
<script type="text/javascript" src="/js/editwmsclass.js?78db6f79d8ee26a1"></script>
<script type="text/javascript" src="/js/editwmslayer.js?6cd3fec277824e02"></script>
<script type="text/javascript" src="/js/edittilelayer.js?6cd3fec277824e02"></script>
<script type="text/javascript" src="/js/classwizards.js?b8da68ac7ecfbe3b"></script>
<script type="text/javascript" src="/js/addshapeform.js?a1a04d59aa4b5edb"></script>
<script type="text/javascript" src="/js/addbitmapform.js?38dde20cbaec7c80"></script>
<script type="text/javascript" src="/js/addrasterform.js?e9f707ad6e5100ff"></script>
<script type="text/javascript" src="/js/addfromscratch.js?e4a993729b4639ad"></script>
<script type="text/javascript" src="/js/addviewform.js?d223f9da67a51165"></script>
<script type="text/javascript" src="/js/addosmform.js?6fa514ebc5d91d01"></script>
<script type="text/javascript" src="/js/colorfield.js?4c80541098c1f93d"></script>
<script type="text/javascript" src="/js/httpauthform.js?2acab5ae23de010f"></script>
<script type="text/javascript" src="/js/apikeyform.js?9485c6f6a26fab43"></script>
<script type="text/javascript" src="/js/plupload/js/moxie.min.js?5eb0c30ea42430c9"></script>
<script type="text/javascript" src="/js/plupload/js/plupload.min.js?745552fc001e46c4"></script>
<script type="text/javascript"
        src="/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js?78b92aab54b9f806"></script>
<!-- /build -->
</body>
</html>

