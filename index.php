<!DOCTYPE html>
<?php
	include 'conf/main.php';
	session_name($sessionName);
	session_set_cookie_params(0, '/', "." . $domain);
?>
<html lang="en">
	<head>
		<title>MyGeoCloud - Analyze and map your data</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta charset="UTF-8" />
		<meta name="description" content="Store geographical data and make online maps" />
		<meta name="description" content="Visualize your data on maps. Just use HTML and JavaScript through a powerful API for adding maps to your own app or web site" />
		<meta name="description" content="The core component of MyGeoCloud is the rock solid PostGIS database with endless possibilities. If you can think the analysis you can do it." />
		<meta name="description" content="With a powerful adminstration tool you can manage your data online. Get full control from every where." />
		<meta name="keywords" content="examples, openlayers, api, postgis, hosting, mapserver, tile cache, gdal, ogr2ogr, spatial, sql, cluster map, buffer map, map, geo, cloud, visualize, analyze, gis, geographical data, maps, web mapping, shape file, GPX, MapInfo, wms, wfs, wfs-t, ogc" />
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
		<div class="navbar navbar-inverse navbar-fixed-top">
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
								<?php if 	(!$_SESSION['auth'] || !$_SESSION['screen_name']) {
								?>
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
		<div class="bs-docs-social">
			<div class="container">
				<ul class="bs-docs-social-buttons">

					<li>
						<iframe class="github-btn" src="http://ghbtns.com/github-btn.html?user=mhoegh&amp;repo=mygeocloud&amp;type=watch&amp;count=true" allowtransparency="true" frameborder="0" scrolling="0" width="100px" height="20px"></iframe>
					</li>
					<li>
						<iframe class="github-btn" src="http://ghbtns.com/github-btn.html?user=mhoegh&amp;repo=mygeocloud&amp;type=fork&amp;count=true" allowtransparency="true" frameborder="0" scrolling="0" width="98px" height="20px"></iframe>
					</li>
					<li class="follow-btn">
						<iframe allowtransparency="true" frameborder="0" scrolling="no" src="http://platform.twitter.com/widgets/follow_button.1352365724.html#_=1352840689739&amp;id=twitter-widget-1&amp;lang=en&amp;screen_name=mhoegh&amp;show_count=true&amp;show_screen_name=true&amp;size=m" class="twitter-follow-button" style="width: 242px; height: 20px;" title="Twitter Follow Button" data-twttr-rendered="true"></iframe>
					</li>
					<li class="tweet-btn">
						<a href="https://twitter.com/share" class="twitter-share-button" data-via="mhoegh">Tweet</a>
					</li>
					<li>
						<g:plusone size="medium"></g:plusone>

					</li>
					<li>
						<div class="fb-like" data-href="http://beta.mygeocloud.com" data-send="false" data-width="0" data-show-faces="false" data-font="verdana"></div>
					</li>
				</ul>
			</div>
		</div>
		<div class="container">
			<div class="marketing">
				<div class="row">
					<div class="span4">
						<div>
							<h3 style="padding: 10px 0px;">Add maps to your own apps</h3>
							<p>
								Visualize your data on maps. Just use HTML and JavaScript through a powerful API for adding maps to your own app or web site. <a href="/developers/mapclientapi/index.html">Learn more</a>.
							</p>
						</div>
					</div>
					<div class="span4">
						<div>
							<h3 style="padding: 10px 0px;">Build on open source software</h3>
							<p>
								The core component of MyGeoCloud is the rock solid PostGIS database with endless possibilities. If you can think the analysis you can do it. <a href="/developers/mapclientapi/advanced1.html">Learn more</a>.
							</p>
						</div>
					</div>
					<div class="span4">
						<div>
							<h3 style="padding: 10px 0px;">Manage data</h3>
							<p>
								With a powerful adminstration tool you can manage your data online. Get full control from every where.
							</p>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="span4">
						<img src="/theme/images/code.png" class="img-rounded img-polaroid">
					</div>
					<div class="span4">
						<img src="/theme/images/map.png" class="img-rounded img-polaroid">
					</div>
					<div class="span4">
						<img src="/theme/images/admin.png" class="img-rounded img-polaroid">
					</div>
				</div>
				<div class="row">
					<div class="span4">
						<h3 style="padding: 10px 0px;">Maintain your data</h3>
						<p>
							You can edit your data sets from any application, which supports the WFS-Tstandard. We recommend the desktop application QGIS, which is OpenSource and runs on Linux, MacOSX and Windows.
						</p>
					</div>
					<div class="span4">
						<h3 style="padding: 10px 0px;">Upload your data</h3>
						<p>
							Upload your existing data to your MyGeoCloud database. At the moment you can upload Shape files and MapInfo Tab files, but soon we will add more like GeoJSON og GML.
						</p>
					</div>
					<div class="span4">
						<h3 style="padding: 10px 0px;">Use spatial SQL</h3>
						<p>
							With the SQL API you can fire any SQL query for accessing your data. You can use the API on web sites, in apps and on servers. And in any programming language. <a href="/developers/sqlapi/index.html">Learn more</a>
						</p>
					</div>
				</div>
				<div class="row">
					<div class="span4">
						<img src="/theme/images/qgis.png" class="img-rounded img-polaroid">
					</div>
					<div class="span4">
						<img src="/theme/images/upload.png" class="img-rounded img-polaroid">
					</div>
					<div class="span4">
						<img src="/theme/images/sql.png" class="img-rounded img-polaroid">
					</div>
				</div>
			</div>
		</div>

		<hr/>
		<footer >
			<div style="margin-bottom: 15px">
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
			<center>
				All Rights Reserved, MyGeoCloud.com, 2012. <a href="mailto:mygeocloud@gmail.com">mygeocloud@gmail.com</a>
			</center>
		</footer>

		<script src="http://twitter.github.com/bootstrap/assets/js/jquery.js"></script>
		<script src="/js/bootstrap/js/bootstrap.js"></script>

	</body>
</html>