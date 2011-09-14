<?php
// The editor is not being authenticated. Its only done in the WFS
include("html_header.php");
include("wms/mapfile.php.map");
$_SESSION['screen_name'] = $parts[2];
makeMapFile($_SESSION['screen_name']);
?>

		<script type="text/javascript">var screenName='<?php echo $_SESSION['screen_name'];?>'</script>
		<script type="text/javascript" src="/js/openlayers/lib/OpenLayers.js?mobile"></script>
		<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js">
		</script>
		<script type="text/javascript" src="/js/ext/ext-all.js">
		</script>
		<script type="text/javascript" src="/js/GeoExt/lib/GeoExt.js">
		</script>
		<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js">
		</script>
		<script type="text/javascript" src="/js/viewer.js">
		</script>
		<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=ABQIAAAAixUaqWcOE1cqF2LJyDYCdhS4p9AtMz66nyqFUaziGHLM44rOahQ1vHhpXeGXl_ifkSE8O1eT_foV2w"
		type="text/javascript">
		</script>
		<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
		<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"/>
		<link rel="stylesheet" type="text/css" href="/js/openlayers/examples/style.mobile.css"/>
</head>
<body>
	<h1>Tree using a GeoExt.tree.WMSCapabilitiesLoader</h1>

       <div id="desc"> 
            <p>This example shows how to use GeoExt.tree.WMSCapabilitiesLoader to populate a tree
            with the hierarchical structure of a WMS GetCapabilities response. The example
            also shows how to customize the loader's <tt>createNode</tt> method to add a checkbox
            with a <tt>checkchange</tt> listener that adds and removes layers to and from the map.
            </p>
            <p>See <a href="wms-tree.js">wms-tree.js</a> for the source code.</p>
        </div>

<?php include("html_footer.php");?>