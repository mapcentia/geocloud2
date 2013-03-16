<?php
// The editor is not being authenticated. Its only done in the WFS
include ("html_header.php");
$_SESSION['screen_name'] = $parts[2];
?>

<script type="text/javascript">var screenName =  '<?php echo $_SESSION['screen_name']; ?>'</script>
<script type="text/javascript">var schema =  '<?php echo $schemaFromUri; ?>'</script>
<script type="text/javascript" src="/api/v1/js/api.js"></script>
<script type="text/javascript" src="/js/wfseditor.js"></script>
<script type="text/javascript" src="/js/attributeform.js"></script>
<script type="text/javascript" src="/js/filterfield.js?format=txt"></script>
<script type="text/javascript" src="/js/filterbuilder.js?format=txt"></script>
<script type="text/javascript" src="/js/comparisoncomboBox.js?format=txt"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA-DSPlhVi52zBadpyTRa4cOtSr6WKDOgA&sensor=false">"></script>

<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css"/>
<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"/>
<link rel="stylesheet" type="text/css" href="/js/openlayers/examples/style.mobile.css"/>
<link rel="stylesheet" type="text/css" href="/js/extras.css?format=txt"/>
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
</style>
</head>
<body>
	<div id="mb7"></div>
	<div id="mapel" style=""></div>
	<div id="instructions">
		<p style="padding: 10px">
			Make a layer name in the layer tree active and click 'Edit layer'. Only Features in the view port will be loaded. So on big layers zoom in before you start to edit.
		</p>
		<p style="padding: 10px">
			For loading of tiles check the box beside the layer name.
		</p>
	</div>
	<?php
	include ("html_footer.php");
	?>
