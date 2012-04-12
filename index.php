<!DOCTYPE html>
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
    <link href="/css/style.css" rel="stylesheet">
	<script type="text/javascript">

	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', 'UA-28178450-1']);
	  _gaq.push(['_setDomainName', 'mygeocloud.com']);
	  _gaq.push(['_trackPageview']);
	
	  (function() {
	    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();
	
	</script>
</head>
<body>
	<div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="/">MyGeoCloud</a>
          <div class="nav-collapse">
            <ul class="nav">
          <!--    <li class="active"><a href="/">Home</a></li>
              <li><a href="about.html">About</a></li> -->
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>
    <div class="container">

			<div class="row">
				<div class="span8">
				
						<div id="myCarousel" class="carousel slide">
				            <div class="carousel-inner">
				              <div class="item active">
				                <img  src="/theme/images/c1.png" alt="">
				                <div class="carousel-caption round_border_bottom">
				                  <h4>Administration of your geospatial data</h4>
				                  <p>Get full administration of your geospatial database through a web browser. Upload new data by Shape or MapInfo files, alter table 
				                  structures and setup layers and styles for map rendering.
				                  </p>
				                </div>
				              </div>
				              <div class="item">
				                <img  src="/theme/images/c2.png" alt="">
				                <div class="carousel-caption round_border_bottom">
				                  <h4>View, create, update and delete data online</h4>
				                  <p>Use the built-in WFS-T client to view and edit your geospatial data. You can also use desktop GIS software that 
				                  supports the WFS-T protocol. MyGeoCloud is tested with QGIS, MapInfo and ArcGIS (the latter only reading).</p>
				                </div>
				              </div>
				              <div class="item">
				                <img  src="/theme/images/c3.png" alt="">
				                <div class="carousel-caption round_border_bottom">
				                  <h4>Add maps to your own site</h4>
				                  <p>Is really easy to add maps to your own site. Embed the built-in web map on any page or use the JavaScript API to take full control over 
				                  the functionality and appearance.</p>
				                </div>
				              </div>
				            </div>
				            <a class="left carousel-control" href="#myCarousel" data-slide="prev">&lsaquo;</a>
				            <a class="right carousel-control" href="#myCarousel" data-slide="next">&rsaquo;</a>
         				</div>
					
				</div>
	        	<div class="span4">
					<div class="box">
						<h2><strong>Want a cloud?</strong>  sign up</h2>
						<div style="padding: 10px">
							<form class="form-inline" name="db" id="id" method="get" action="/createstore">
								<input type="text" class="spdan3" placeholder="Name of cloud…" name="name" id="name"/>
								<button type="submit" class="btn btn-warning">Create it now</button>
								<p class="help-block">It will take a minute. Please stay on the page.</p>
							</form>
						</div>
					</div>
					<div class="box" style="margin-top: 10px">
	        			<h2>All ready have a cloud?</h2>
						<div style="padding: 10px">
							<?php if ($_GET['db']=="false") {echo "<div class='alert alert-error'><a class='close' data-dismiss='alert'>×</a>Cloud does not exist</div>";}?>
							<input type="text" class="span3" placeholder="Name of cloud…" name="xname" id="xname"/>
							<button onclick="window.location='/store/' + document.getElementById('xname').value" type="submit" class="btn btn-primary">Take me to my cloud</button>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="span4">
					<div>
						<h2 style="padding: 10px 0px;">Get all-in-one solution for your geospatial data</h2>
						<p>We offer geospatial storage, WMS and WFS-T services for accessing data and transactions. Besides that we offer a built-in 
						web mapping client and online editing of data. But that's not all! MyGeoCloud is also a platform on which you can build your own location based web applications
						using our rich JavaScript API.</p>
					</div>
				</div>
				<div class="span4">
					<div>
						<h2 style="padding: 10px 0px;">Build entirely on open source software</h2>
						<p>The core component of MyGeoCloud is the rock solid PostGIS database software, which is used for storage and geospatial operations. 
						 We are using MapServer for map rendering and TileCache for, yes you guessed it right, tile caching. OpenLayers is used for the web map clients.</p>
					</div>
				</div>
				<div class="span4">
					<div>
						<h2 style="padding: 10px 0px;">Pricing and feedback</h2>
						<p>Right now we are in a beta period. So for now you can use this awesome service for free! Don't forget to give us feed back - if some
						thing does not work properly or if you are missing some features.</p>
					</div>
				</div>
			</div>
				
			<hr/>
			<footer>
			<div style="margin-bottom: 15px">
				<a href="https://twitter.com/share" class="twitter-share-button" data-via="mhoegh">Tweet</a>
				<div class="fb-like" data-href="http://beta.mygeocloud.com" data-send="false" data-width="450" data-show-faces="false" data-font="verdana"></div>
				<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
         	</div>	
         		<p>All Rights Reserved, MyGeoCloud.com, 2012. <a href="mailto:mygeocloud@gmail.com">mygeocloud@gmail.com</a></p>
         	</footer>
	
	</div>
	<script src="/js/bootstrap/js/jquery.js"></script>
	<script src="/js/bootstrap/js/bootstrap.min.js"></script>
	<script src="/js/bootstrap/js/bootstrap-carousel.js"></script>
	<script src="/js/bootstrap/js/bootstrap-alert.js"></script>
	<script type="text/javascript">
	$('.carousel').carousel({
		  interval: 10000
	})
    </script>
</body>
</html>