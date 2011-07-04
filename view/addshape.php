<?php include( "../header.php"); include( "../inc/oauthcheck.php"); ?>
<html>
	<head>
	<?php echo $screenNameJScode;?>
	<title>Upload ESRI shape file</title>
		<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js">
		</script>
		<script type="text/javascript" src="/js/ext/ext-all.js">
		</script>
		<!-- overrides to base library --> 
		<script type="text/javascript" src="/js/ext/examples/ux/fileuploadfield/FileUploadField.js"></script>
		<script type="text/javascript" src="/js/addshapeform.js">
		</script>
	
		
		<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/icons/silk.css"
		/>
		<link rel="stylesheet" type="text/css" href="/js/ext/examples/shared/examples.css" /> 
		<link rel="stylesheet" type="text/css" href="/js/ext/examples/ux/fileuploadfield/css/fileuploadfield.css"/>
		<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"
		/>
		<style type=text/css> 
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
		<?php echo $_SESSION["header_html"];?>
			<div id="capgrid">
			</div>
	</body>
</html>