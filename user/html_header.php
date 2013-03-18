<!DOCTYPE html>
<html lang="en-us">
	<head>
		<title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="description" content="Store geographical data and make online maps" />
		<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
		<meta name="author" content="Martin Hoegh" />
		<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->
		<link href="http://cdn.us1.mapcentia.com/js/bootstrap/css/bootstrap.css" rel="stylesheet">
		<link href="http://cdn.us1.mapcentia.com/js/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
		<link href="http://twitter.github.com/bootstrap/assets/css/docs.css" rel="stylesheet">
		<script src="http://twitter.github.com/bootstrap/assets/js/jquery.js"></script>
		<script src="http://twitter.github.com/bootstrap/assets/js/bootstrap-tooltip.js"></script>
		<script src="http://twitter.github.com/bootstrap/assets/js/bootstrap-popover.js"></script>
		<script src="http://beta.mygeocloud.cowi.webhouse.dk/js/jqote2/jquery.jqote2.js"></script>
		<style type="text/css">
			body {
				background: url(/theme/images/cartographer.png) repeat top left;
			}
			.popover-title{
				display: none !important;
			}
			h1, h2, h3, h4, h5, h6 {
				margin: 10px 0;
				font-family: inherit;
				font-weight: bold;
				line-height: 1;
				color: inherit;
				text-rendering: optimizelegibility;
			}
			.navbar .brand-dev:hover {
				text-decoration: none;
			}
			.navbar .brand-dev {
				float: left;
				display: block;
				padding: 8px 20px 12px;
				margin-left: -20px;
				font-size: 20px;
				font-weight: 200;
				line-height: 1;
				color: #ffffff;
			}
			.dialog, .dashboard{
				border: 1px solid black;
				
				padding: 40px;
				margin: auto;
				margin-top: 50px;
				background-color: white;
				border-radius: 6px;
				border: 3px solid rgba(0, 0, 0, 0.2);
				box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
				background-clip: padding-box;
			}
			.dialog {
				width: 440px;
			}
			
			.dashboard{
				
			}
			
			.first {
				margin-top: 20px
			}
			.box {
				-webkit-border-radius: 4px;
				-moz-border-radius: 4px;
				border-radius: 4px;
				adding: 10px;
				display: block;
				background: white;
				background: -webkit-gradient(linear,left top,left bottom,color-stop(0%,white),color-stop(100%,#DDD));
				background: -webkit-linear-gradient(top,white 0,#DDD 100%);
				background: -moz-linear-gradient(top,white 0,#DDD 100%);
				background: -ms-linear-gradient(top,white 0,#DDD 100%);
				background: -o-linear-gradient(top,white 0,#DDD 100%);
				background: linear-gradient(top,white 0,#DDD 100%);
				filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#ffffff',endColorstr='#dddddd',GradientType=0);
				border-left: solid 1px #BBB;
				border-right: solid 1px #CCC;
				border-bottom: solid 1px #AAA;
				border-top: solid 1px #DDD;
				-webkit-box-shadow: 0 1px 0 rgba(0,0,0,.1);
				-moz-box-shadow: 0 1px 0 rgba(0,0,0,.1);
				box-shadow: 0 1px 0 rgba(0,0,0,.1);
				height: 230px;
				position: relative;
			}
			.inner {
				padding: 10px;
			}
			.box h2 {
				display: block;
				padding: 10px 12px;
				margin-bottom: 12px;
				font-size: 20px;
				font-weight: bold;
				color: #777;
				border-bottom: 1px solid #E2E2E2;
				-webkit-box-shadow: 0 1px 0 #fff;
				-moz-box-shadow: 0 1px 0 #fff;
				box-shadow: 0 1px 0 #fff;
				-webkit-text-shadow: 0 1px 0 rgba(255,255,255,.6);
				-moz-text-shadow: 0 1px 0 rgba(255,255,255,.6);
				text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
				line-height: 20px;
			}
			h2 span {
				float: right;
			}
			h2 span i {
				font-size: 13px;
				font-weight: bold;
				font-style: normal
			}
			.icon-ok {
				margin-right: 5px;
			}
			.box .inner {
				color: #777;
				font-weight: bold;
				-webkit-text-shadow: 0 1px 0 rgba(255,255,255,.6);
				-moz-text-shadow: 0 1px 0 rgba(255,255,255,.6);
				text-shadow: 0 1px 0 rgba(255, 255, 255, .6);
				line-height: 20px;
			}
			.box .minus {
				color: #aaa;
			}
			.box .no-icon {
				visibility: hidden;
			}
			.round_border {
				-webkit-border-radius: 4px;
				-moz-border-radius: 4px;
				border-radius: 4px;
			}
			.btn-upgrade {
				position: absolute;
				bottom: 15px;
				right: 15px;
				float: right;
			}
			.all-plans i {
				margin-left: 20px;
			}
			.all-plans {
				margin-top: 15px;
			}
			.form {
				margin-bottom: 0px;
			}
		</style>
		<script type="text/javascript">
            var _gaq = _gaq || [];
            _gaq.push(['_setAccount', 'UA-28178450-1']);
            _gaq.push(['_setDomainName', 'mygeocloud.com']);
            _gaq.push(['_trackPageview']);

            (function() {
                var ga = document.createElement('script');
                ga.type = 'text/javascript';
                ga.async = true;
                ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                var s = document.getElementsByTagName('script')[0];
                s.parentNode.insertBefore(ga, s);
            })();

		</script>
	</head>
	<body>
		<div class="navbar navbar-inverse navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container">
					<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse"> <span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span> </a>
					<a class="brand" href="/">MyGeoCloud</a>
					<div class="nav-collapse">
						<ul class="nav">
							<li>
								<a href="">Feutures</a>
							</li>
							<li>
								<a href="/developers/index.html">Developers</a>
							</li>
							<li>
								<a href="/user/plans">Plans &amp; Pricing</a>
							</li>
							<li>
								<?php if 	(!$_SESSION['auth'] || !$_SESSION['screen_name']) {
								?>
								<a href="/user/login">Log in</a>
								<?php } else { ?>
								<a href="/user/login">Dashboard</a>
								<?php } ?>
							</li>

						</ul>
					</div><!--/.nav-collapse -->
				</div>
			</div>
		</div>