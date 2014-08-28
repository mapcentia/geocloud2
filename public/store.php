<?php
include("html_header.php");
?>
<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js"></script>
<script type="text/javascript" src="/js/ext/ext-all.js"></script>

<!-- Ext 4.0 -->
<!--<link rel="stylesheet" type="text/css" href="/js/ext4/resources/css/ext-all.css" />-->
<!-- Stick to ext-all-debug.js (not bootstrap.js) while migrating. Legacy code will not work with bootstrap! -->
<!--<script type="text/javascript" src="/js/ext4/ext-all-debug.js"></script>-->

<!-- Ext 3.x compatibility -->
<!--<script type="text/javascript" src="/js/compatibility/ext3-core-compat.js"></script>-->
<!--<script type="text/javascript" src="/js/compatibility/ext3-compat.js"></script>-->

<script type="text/javascript" src="/api/v1/baselayerjs"></script>
<script type="text/javascript" src="/js/msg.js"></script>
<script type="text/javascript" src="/js/jquery/1.6.4/jquery.min.js"></script>
<script type="text/javascript" src="/js/store.js"></script>
<script type="text/javascript" src="/js/edittablestructure.js"></script>
<script type="text/javascript" src="/js/cartomobilesetup.js"></script>
<script type="text/javascript" src="/js/editwmsclass.js"></script>
<script type="text/javascript" src="/js/editwmslayer.js"></script>
<script type="text/javascript" src="/js/classwizards.js"></script>
<script type="text/javascript" src="/js/addshapeform.js"></script>
<script type="text/javascript" src="/js/addbitmapform.js"></script>
<script type="text/javascript" src="/js/addrasterform.js"></script>
<script type="text/javascript" src="/js/addfromscratch.js"></script>
<script type="text/javascript" src="/js/colorfield.js"></script>
<script type="text/javascript" src="/js/httpauthform.js"></script>
<script type="text/javascript" src="/js/apikeyform.js"></script>
<!--<script type="text/javascript" src="/js/ext/examples/ux/RowEditor.js"></script>-->

<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"/>
<link rel="stylesheet" type="text/css" href="/css/legend.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/ux/css/RowEditor.css"/>
<link href="/js/bootstrap/css/bootstrap.icons.min.css" rel="stylesheet">

<!-- overrides to base library -->
<script type="text/javascript" src="/js/ext/examples/ux/Spinner.js"></script>
<script type="text/javascript" src="/js/ext/examples/ux/SpinnerField.js"></script>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/ux/css/Spinner.css" />
<!-- extensions -->
<script type="text/javascript" src="/js/ext/examples/ux/CheckColumn.js"></script>

<link rel="stylesheet" type="text/css" href="/css/jquery.plupload.queue.css"/>
<script type="text/javascript" src="/js/plupload/js/moxie.min.js"></script>
<script type="text/javascript" src="/js/plupload/js/plupload.min.js"></script>
<script type="text/javascript" src="/js/plupload/js/jquery.plupload.queue/jquery.plupload.queue.min.js"></script>
<style>
    .btn-gc {
        margin-top: -1px !important;
    }

    .x-box-item {
        font-family: arial, tahoma, helvetica, sans-serif;
        font-size: 11px;
        font-style: normal;
        font-variant: normal;
        font-weight: normal;
        vertical-align: middle;
    }
    .plupload_header{
        color: #ffffff;
        background-color: rgb(119, 119, 119);
    }

    .map-thumbs {
         background-size: 283px 35px;
         width: 283px;
         height:35px;
        vertical-align: middle !important;

    }
    .map-thumbs b {
        font-size: 14px;
        line-height: 5px;
        padding-left: 5px;
        padding-top: 5px;
        color: #000000;
        text-shadow:
            -1px -1px 0 #FFF,
            1px -1px 0 #FFF,
            -1px 1px 0 #FFF,
            1px 1px 0 #FFF;
    }
</style>
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/wfs/<?php echo ($_SESSION['subuser']?$_SESSION['subuser']."@":"") . $_SESSION['screen_name']; ?>/<?php echo (\app\inc\Input::getPath()->part(3)) ? \app\inc\Input::getPath()->part(3) : "public"; ?>/4326"
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/wms/<?php echo ($_SESSION['subuser']?$_SESSION['subuser']."@":"") . $_SESSION['screen_name']; ?>/<?php echo (\app\inc\Input::getPath()->part(3)) ? \app\inc\Input::getPath()->part(3) : "public"; ?>/"
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/wms/mydb/tilecache/1.0.0/{layer}/7/32/25.png"
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/sql/<?php echo ($_SESSION['subuser']?$_SESSION['subuser']."@":"") . $_SESSION['screen_name']; ?>?q=[query]&key=[your_api_key]"
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/elasticsearch/[map|bulk|search|delete]/<?php echo $_SESSION['screen_name']; ?>/[index]/[type]"
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
<?php
include("html_footer.php");
?>
