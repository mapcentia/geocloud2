<?php
include("html_header.php");
?>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css?9ae21f2038e3c563"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css?49593e1feb591d0b"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css?84655ed526dfbc2a"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/ux/css/Spinner.css?35ea7a99ab8c2113" />
<link rel="stylesheet" type="text/css" href="/js/bootstrap/css/bootstrap.icons.min.css?946b6da947019f90"/>
<link rel="stylesheet" type="text/css" href="/css/jquery.plupload.queue.css?0883487d9fdc30c9"/>
<!-- build:css /css/build/styles.min.css -->
<link rel="stylesheet" type="text/css" href="/css/styles.css?cb4483ba51351535"/>
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
                <td>Use this string in GIS that supports WFS:</td>
            </tr>
            <tr>
                <td>
                    <input type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ? : "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/wfs/<?php echo ($_SESSION['subuser']?$_SESSION['subuser']."@":"") . $_SESSION['screen_name']; ?>/<?php echo (\app\inc\Input::getPath()->part(3)) ? \app\inc\Input::getPath()->part(3) : "public"; ?>/4326"
                           size="65"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="wms-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>Use this string in GIS that supports WMS:</td>
            </tr>
            <tr>
                <td>
                    <input type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ? : "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/wms/<?php echo ($_SESSION['subuser']?$_SESSION['subuser']."@":"") . $_SESSION['screen_name']; ?>/<?php echo (\app\inc\Input::getPath()->part(3)) ? \app\inc\Input::getPath()->part(3) : "public"; ?>/"
                           size="65"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="tms-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>Use this string in GIS that supports TMS (Google style):</td>
            </tr>
            <tr>
                <td>
                    <input type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ? : "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/wms/mydb/tilecache/1.0.0/{layer}/7/32/25.png"
                           size="65"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="sql-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>This is the SQL API end point:</td>
            </tr>
            <tr>
                <td>
                    <input type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ? : "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/sql/<?php echo ($_SESSION['subuser']?$_SESSION['subuser']."@":"") . $_SESSION['screen_name']; ?>?q=[query]&key=[your_api_key]"
                           size="65"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>
    <div id="elasticsearch-dialog">
        <table border="0">
            <tbody>
            <tr>
                <td>This is the elasticsearch API end point:</td>
            </tr>
            <tr>
                <td>
                    <input type="text" readonly="readonly"
                           value="<?php echo \app\conf\App::$param['protocol'] ? : "http" ?>://<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/elasticsearch/[map|bulk|search|delete]/<?php echo $_SESSION['screen_name']; ?>/[index]/[type]"
                           size="65"/>
                </td>
            </tr>
            <tr>
                <td>map: PUT, bulk: POST, search: GET, delete: DELETE</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
<script type="text/javascript" src="/api/v1/baselayerjs"></script>
<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js?7453012a468a8a38"></script>
<script type="text/javascript" src="/js/ext/ext-all.js?0035a5fa730b0514"></script>
<script type="text/javascript" src="/js/ext/examples/ux/Spinner.js?e49f4b82150f1b22"></script>
<script type="text/javascript" src="/js/ext/examples/ux/SpinnerField.js?12cd89e35dc66bc2"></script>
<script type="text/javascript" src="/js/ext/examples/ux/CheckColumn.js?4ac75c8fbbf56910"></script>
<!-- build:js /js/build/store/all.min.js -->
<script type="text/javascript" src="/js/jquery/1.6.4/jquery.min.js?219073097031d9c1"></script>
<script type="text/javascript" src="/js/msg.js?e6ae61b3f174052d"></script>
<script type="text/javascript" src="/js/store.js?2859e29280c95f92"></script>
<script type="text/javascript" src="/js/edittablestructure.js?3e5e88d555665ea7"></script>
<script type="text/javascript" src="/js/cartomobilesetup.js?18b434a3917cddf4"></script>
<script type="text/javascript" src="/js/editwmsclass.js?4f93fc8ac1ee2be8"></script>
<script type="text/javascript" src="/js/editwmslayer.js?ac6b46d6d15b9718"></script>
<script type="text/javascript" src="/js/classwizards.js?65e7244c7231033c"></script>
<script type="text/javascript" src="/js/addshapeform.js?9c4ac94310c47df3"></script>
<script type="text/javascript" src="/js/addbitmapform.js?7f352c9bbaf8b3ce"></script>
<script type="text/javascript" src="/js/addrasterform.js?da336a6959702c26"></script>
<script type="text/javascript" src="/js/addfromscratch.js?e4a993729b4639ad"></script>
<script type="text/javascript" src="/js/addviewform.js?6977a4ed52a23fae"></script>
<script type="text/javascript" src="/js/addosmform.js?f003acc713efeafa"></script>
<script type="text/javascript" src="/js/colorfield.js?4c80541098c1f93d"></script>
<script type="text/javascript" src="/js/httpauthform.js?f68874434ef507cc"></script>
<script type="text/javascript" src="/js/apikeyform.js?255f5386bda54d03"></script>
<script type="text/javascript" src="/js/plupload/js/moxie.min.js?5eb0c30ea42430c9"></script>
<script type="text/javascript" src="/js/plupload/js/plupload.min.js?745552fc001e46c4"></script>
<script type="text/javascript" src="/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js?78b92aab54b9f806"></script>
<!-- /build -->
</body>
</html>

