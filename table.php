<?php
// The editor is not being authenticated. Its only done in the WFS
include("html_header.php");
$_SESSION['screen_name'] = $parts[2];
?>
	<script type="text/javascript">var screenName='<?php echo $_SESSION['screen_name'];?>'</script>
	<script type="text/javascript">var schema='<?php echo $schemaFromUri;?>'</script>
	<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js"></script>
	<script type="text/javascript" src="/js/ext/ext-all.js"></script>
	<script type="text/javascript" src="/js/jquery/1.6.4/jquery.min.js"></script>
	<script type="text/javascript" src="/js/table.js"></script>
	<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
</head>
<body>
	<div id="mb7"></div>
	<div id="mapel" style=""></div>
<?php include("html_footer.php");?>