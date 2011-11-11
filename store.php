<?php
//god dav
include("html_header.php");
$_SESSION['schema'] = $schemaFromUri;
$postgisschema = $schemaFromUri;

include("wms/mapfile.php.map");
makeMapFile($_SESSION['screen_name']);

?>
		<script type="text/javascript">var screenName='<?php echo $_SESSION['screen_name'];?>'</script>
		<script type="text/javascript">var schema='<?php echo $_SESSION['schema'];?>'</script>
		<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js">
		</script>
		<script type="text/javascript" src="/js/ext/ext-all.js">
		</script>
		<script type="text/javascript" src="/js/jquery/1.6.4/jquery.min.js">
		</script>
		<script type="text/javascript" src="/js/store.js">
		</script>
		<script type="text/javascript" src="/js/edittablestructure.js">
		</script>
		<script type="text/javascript" src="/js/editwmsclass.js">
		</script>
		<script type="text/javascript" src="/js/editwmslayer.js">
		</script>
		<script type="text/javascript" src="/js/addshapeform.js">
		</script>
		<script type="text/javascript" src="/js/addmapinfoform.js">
		</script>
		<script type="text/javascript" src="/js/addgmlform.js">
		</script>
		<script type="text/javascript" src="/js/addfromscratch.js">
		</script>
		<script type="text/javascript" src="/js/colorfield.js">
		</script>
		<script type="text/javascript" src="/js/httpauthform.js">
		</script>
	
		
		<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"
		/>
		<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"
		/>
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
    </style> 
	</head>
	<body>
	<div class="desc">
<?php include("inc/topbar.php");
echo $_SESSION['header_html'];?>
</div>


<?php include("html_footer.php");?>
