<!DOCTYPE html>
<?php session_start();?>
<html >
	<head>
		<title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="description" content="Store geographical data and make online maps" />
		<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
		<meta name="author" content="Martin Hoegh" />
		<script src="http://connect.facebook.net/en_US/all.js#xfbml=1"></script>
		<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->

		<link href="/js/bootstrap/css/bootstrap.css" rel="stylesheet">
		<link href="/js/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
		<link href="http://twitter.github.com/bootstrap/assets/css/docs.css" rel="stylesheet">
		<style>
			h1, h2, h3, h4, h5, h6 {
				margin: 10px 0;
				font-family: inherit;
				font-weight: bold;
				line-height: 1;
				color: inherit;
				text-rendering: optimizelegibility;
			}
			p {
				margin: 0 0 10px;
			}
			.jumbotron::after {
background: none;
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
		<div class="navbar navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container">
					<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse"> <span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span> </a>
					<a class="brand" href="/">MyGeoCloud</a>
					<div class="nav-collapse">
						<ul class="nav">
							<li>
								<a href="/developers/index.html">Developers</a>
							</li>
				
							<li>
								<?php if 	(!$_SESSION['auth'] || !$_SESSION['screen_name']) {?>
								<a href="/user/login">Log in</a>
								<?php } else { ?>
								<a href="/user/logout">Log out</a>
								<?php } ?>
							</li>

						</ul>
					</div><!--/.nav-collapse -->
				</div>
			</div>
		</div>
		<div class="jumbotron masthead" style="box-shadow: 0 1px 0 rgba(0, 0, 0, .1);
		background: url(/theme/images/cartographer.png) repeat top left;
		">
			<div class="container">
				<h1>MyGeoCloud</h1>
				<p>
					Analyze and map your data the easy way.
				</p>
				<p>
					<a href="/user/signup" class="btn btn-warning btn-large">Get started - its free</a>
				</p>
				<ul class="masthead-links">
					<li>
						<a href="http://github.com/mhoegh/mygeocloud">GitHub project</a>
					</li>
					<li>
						<a href="/about.html">About</a>
					</li>
					<li>
						Beta
					</li>
				</ul>
			</div>

		</div>
		<div class="container">
			<div class="marketing">
			<div class="row">
				<div class="span4">
					<div>
						<h2 style="padding: 10px 0px;">Add maps to your own apps</h2>
					</div>
				</div>
				<div class="span4">
					<div>
						<h2 style="padding: 10px 0px;">Build on open source software</h2>
					</div>
				</div>
				<div class="span4">
					<div>
						<h2 style="padding: 10px 0px;">Manage data</h2>
						</div>
				</div>
			</div>
			<div class="row">
				<div class="span4">
					<div>
						<p>
							Analyze and visualize your data. Use a powerful API for adding maps to your own apps.
						</p>
					</div>
				</div>
				<div class="span4">
					<div>
						<p>
							The core component of MyGeoCloud is the rock solid PostGIS database with endless possibilities.
						</p>
					</div>
				</div>
				<div class="span4">
					<div>
						<p>
							With a powerful adminstration tool you can manage your data online. 
						</p>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top:50px">
				<div class="span8 offset2">

					<div id="myCarousel" class="carousel slide">
						<div class="carousel-inner">
							<div class="item active">
								<img  src="/theme/images/c1.png" alt="">
								<div class="carousel-caption round_border_bottom">
									<h4>Administration of your geospatial data</h4>
									<p>
										Get full administration of your geospatial database through a web browser. Upload new data by Shape or MapInfo files, alter table
										structures and setup layers and styles for map rendering.
									</p>
								</div>
							</div>
							<div class="item">
								<img  src="/theme/images/c2.png" alt="">
								<div class="carousel-caption round_border_bottom">
									<h4>View, create, update and delete data online</h4>
									<p>
										Use the built-in WFS-T client to view and edit your geospatial data. You can also use desktop GIS software that
										supports the WFS-T protocol. MyGeoCloud is tested with QGIS, MapInfo and ArcGIS (the latter only reading).
									</p>
								</div>
							</div>
							<div class="item">
								<img  src="/theme/images/c3.png" alt="">
								<div class="carousel-caption round_border_bottom">
									<h4>Add maps to your own site</h4>
									<p>
										Is really easy to add maps to your own site. Embed the built-in web map on any page or use the JavaScript API to take full control over
										the functionality and appearance.
									</p>
								</div>
							</div>
						</div>
						<a class="left carousel-control" href="#myCarousel" data-slide="prev">&lsaquo;</a>
						<a class="right carousel-control" href="#myCarousel" data-slide="next">&rsaquo;</a>
					</div>

				</div>
			</div>
		</div>

		<hr/>
		<footer>
			<div style="margin-bottom: 15px">
				<g:plusone size="medium"></g:plusone>
				<script type="text/javascript">
                    (function() {
                        var po = document.createElement('script');
                        po.type = 'text/javascript';
                        po.async = true;
                        po.src = 'https://apis.google.com/js/plusone.js';
                        var s = document.getElementsByTagName('script')[0];
                        s.parentNode.insertBefore(po, s);
                    })();
				</script>
				<a href="https://twitter.com/share" class="twitter-share-button" data-via="mhoegh">Tweet</a>
				<div class="fb-like" data-href="http://beta.mygeocloud.com" data-send="false" data-width="450" data-show-faces="false" data-font="verdana"></div>
				<script>
                    ! function(d, s, id) {
                        var js, fjs = d.getElementsByTagName(s)[0];
                        if (!d.getElementById(id)) {
                            js = d.createElement(s);
                            js.id = id;
                            js.src = "//platform.twitter.com/widgets.js";
                            fjs.parentNode.insertBefore(js, fjs);
                        }
                    }(document, "script", "twitter-wjs");
				</script>

			</div>
			<p>
				All Rights Reserved, MyGeoCloud.com, 2012. <a href="mailto:mygeocloud@gmail.com">mygeocloud@gmail.com</a>
			</p>
		</footer>

		</div>
		<script src="/js/bootstrap/js/jquery.js"></script>
		<script src="/js/bootstrap/js/bootstrap.min.js"></script>
		<script src="/js/bootstrap/js/bootstrap-carousel.js"></script>
		<script src="/js/bootstrap/js/bootstrap-alert.js"></script>
		<script type="text/javascript">
            $('.carousel').carousel({
                interval : 10000
            })
		</script>
	</body>
</html>