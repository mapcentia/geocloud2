<?php
include("html_header.php");
?>

    <div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="/">World Trail Map</a>
          <div class="nav-collapse">
            <ul class="nav">
              <li class="active"><a href="#">Home</a></li>
              <li><a href="about.html">About</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>
    <div class="container">
      <div class="hero-unit" style="height:500px;padding:0px">
        <iframe frameborder="no" width="100%" height="100%" src="http://test.mygeocloud.com/apps/viewer/openlayers/<?php echo $postgisdb;?>?layers=public.ways&usepopup=false"></iframe>
      </div>
      <div class="row">
        <div class="span6">
          <h2>Over 150.000 free trails</h2>
           <p>World Trail Map gives you access to more than 150.000 hiking trails world wide. From the easy hikes to the most difficult alpine adventures.</p>
        </div>
        <div class="span6">
          <h2>No sign up</h2>
           <p>You don't have to sign up to use World Trail Map. Actual you can't sign up. Just start using the map. Send us a mail if you've cool ideas or if you just like the site.</p>
       </div>
      </div>
      <br/><br/>
<?php
include("html_footer.php");
      