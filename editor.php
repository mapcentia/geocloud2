<?php
// The editor is not being authenticated. Its only done in the WFS
include("html_header.php");
$_SESSION['screen_name'] = $parts[2];
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
		<script type="text/javascript" src="/js/wfseditor.js">
		</script>
			<script type="text/javascript" src="/js/attributeform.js">
		</script>
		<script src="http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=<?php echo $gMapsApiKey;?>"
		type="text/javascript">
		</script>
		<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
		<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"/>
		<link rel="stylesheet" type="text/css" href="/js/openlayers/examples/style.mobile.css"/>
</head>
<body>
	<div id="mb7"></div>
	<div id="mapel" style=""></div>
<?php include("html_footer.php");?>