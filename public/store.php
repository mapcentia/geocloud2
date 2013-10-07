<?php
//god dav
include ("html_header.php");
$_SESSION['schema'] = $schemaFromUri;
$postgisschema = $schemaFromUri;
//include ("wms/mapfile.php.map");
//include ("wms/tilecache.cfg.php");
//makeMapFile($postgisdb);
//makeTileCacheFile($postgisdb);
?>
<script type="text/javascript">var screenName = '<?php echo $postgisdb; ?>'</script>
<script type="text/javascript">var schema = '<?php echo $postgisschema; ?>'</script>
<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js"></script>
<script type="text/javascript" src="/js/ext/ext-all.js"></script>
<script type="text/javascript" src="/js/msg.js"></script>
<script type="text/javascript" src="/js/jquery/1.6.4/jquery.min.js"></script>
<script type="text/javascript" src="/js/store.js"></script>
<script type="text/javascript" src="/js/edittablestructure.js"></script>
<script type="text/javascript" src="/js/cartomobilesetup.js"></script>
<script type="text/javascript" src="/js/editwmsclass.js"></script>
<script type="text/javascript" src="/js/editwmslayer.js"></script>
<script type="text/javascript" src="/js/addshapeform.js"></script>
<script type="text/javascript" src="/js/addmapinfoform.js"></script>
<script type="text/javascript" src="/js/addgmlform.js"></script>
<script type="text/javascript" src="/js/addfromscratch.js"></script>
<script type="text/javascript" src="/js/colorfield.js"></script>
<script type="text/javascript" src="/js/httpauthform.js"></script>
<script type="text/javascript" src="/js/apikeyform.js"></script>

<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"/>
<link href="/js/bootstrap/css/bootstrap.icons.min.css" rel="stylesheet">

<!-- overrides to base library -->
<script type="text/javascript" src="/js/ext/examples/ux/fileuploadfield/FileUploadField.js"></script>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/ux/fileuploadfield/css/fileuploadfield.css"/>
<!-- extensions -->
<script type="text/javascript" src="/js/ext/examples/ux/CheckColumn.js"></script>
<style type="text/css">
    .upload-icon {
        background: url('/js/ext/examples/shared/icons/fam/image_add.png') no-repeat 0 0 !important;
    }

    #fi-button-msg {
        border: 2px solid #ccc;
        padding: 5px 10px;
        background: #eee;
        margin: 5px;
        float: left;
    }

    .btn-gc {
        margin-top: -1px !important;
    }
</style>
</head>
<body>
<div style="display:none">
    <div id="map-settings"></div>
    <div id="authentication">
        HTTP authentication password for WMS and WFS
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/wfs/<?php echo $postgisdb; ?>/<?php echo $postgisschema; ?>/4326"
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/wms/<?php echo $postgisdb; ?>/<?php echo $postgisschema; ?>/"
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
                           value="http://<?php echo $_SERVER['HTTP_HOST']; ?>/api/v1/sql/<?php echo $postgisdb; ?>?q=[query]&key=[your_api_key]"
                           size="65"/>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

</div>
<?php
include ("html_footer.php");
?>
