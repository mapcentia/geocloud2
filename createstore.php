<?php
// Start HTML doc
include("html_header.php");
?>
<!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <link href="/js/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="/js/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
    <style type="text/css">
      strong {
      	font-size: 100%;
      	font-weight: normal;
      	color: black;
      }
      body {
        padding-top: 60px;
        padding-bottom: 40px;
        background: url(/theme/images/bg.jpg) no-repeat top left;
		}
	 .box {
	 	-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px;
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
	 }
	 .inner {
	 	padding: 10px;
	 }
	 h2 {
		display: block;
		padding: 10px 12px;
		margin-bottom: 12px;
		font-size: 16px;
		font-weight: 300;
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
	.round_border {
		-webkit-border-radius: 4px; -moz-border-radius: 4px; border-radius: 4px;
		}
    </style>
   
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
             <!-- <li class="active"><a href="/">Home</a></li>
              <li><a href="about.html">About</a></li> -->
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>
    <div class="container">
		<div class="row">
			<div class="span4">&nbsp;</div>
			<div class="span4">
				<div class="box">
				<?php 
				if (!$_SESSION['screen_name']) {
					echo "<h2>Need a name!</h2>";
					echo "<div class='inner'><a href='/' class='btn btn-danger'>Go back</a></div>";
				}
				else {
					$name = postgis::toAscii($_SESSION['screen_name'],NULL,"_");
					$db = new databases;
					echo "<div class='inner'>";
					$dbObj = $db -> createdb($name,$databaseTemplate,"UTF8"); // databaseTemplate is set in conf/main.php
					echo "</div>";
					if ($dbObj) {
						
						echo "<h2>Your geocloud \"{$name}\" was created!</h2>";
						echo "<div class='inner'><p> </p>"; 
						echo "<p><button class='btn btn-warning' onclick=\"window.location.href='/store/{$name}'\">Take me to my cloud</button></p></div>";
					} 
					else {
						echo "<h2>Sorry, something went wrong. Try again</h2>";
						echo "<div class='inner'><a href='/user/signup' class='btn btn-danger'>Go back</a></div>";
					}
				}
				?>
				</div>
			</div>
			<div class="span4">&nbsp;</div>
		</div>
	</div>
	</body>
	</html>
