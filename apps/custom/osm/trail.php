<?php
include("html_header.php");
include("controller/table_c.php");
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
              <li><a href="/">Home</a></li>
              <li><a href="../about.html">About</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>

    <div class="container">
		<div class="row">
			<div class="span8" style="height: 500px;">
				<iframe frameborder="no"width="100%" height="100%" src="http://test.mygeocloud.com/apps/viewer/openlayers/<?php echo $postgisdb;?>?&usepopup=false&overRideNamedLayerWith=public.ways&layers=public.ways_query;public.ways&sld=%3CStyledLayerDescriptor%20version%3D%271.1.0%27%3E%3CNamedLayer%3E%3CName%3Epublic.ways_query%3C%2FName%3E%3CUserStyle%3E%3CTitle%3Exxx%3C%2FTitle%3E%3CFeatureTypeStyle%3E%3CRule%3E%3CFilter%3E%3CPropertyIsEqualTo%3E%3CPropertyName%3Eid%3C%2FPropertyName%3E%3CLiteral%3E<?php echo $parts[2]?>%3C%2FLiteral%3E%3C%2FPropertyIsEqualTo%3E%3C%2FFilter%3E%3CLineSymbolizer%3E%3CStroke%3E%3CCssParameter%20name%3D'stroke'%3E%23FFFF00%3C%2FCssParameter%3E%3CCssParameter%20name%3D'stroke-width'%3E15%3C%2FCssParameter%3E%3C%2FStroke%3E%3C%2FLineSymbolizer%3E%3C%2FRule%3E%3C%2FFeatureTypeStyle%3E%3C%2FUserStyle%3E%3C%2FNamedLayer%3E%3C%2FStyledLayerDescriptor%3E"></iframe>
			</div>
			<div class="span4">
				<?php 
					foreach (PgHStore::fromPg($row['tagshstore']) as $key => $value) {
						echo "<p><span class='label'>{$key}</span> {$value}</p>";
					}
				?>
				<p><span class='label label-info'>updated</span> <?php echo $row['tstamp'];?></p>
			</div>
		</div>
		<div class="row" style="margin-top: 20px">
			<div class="span8">
				<div id="fb-root"></div><fb:comments href="<?php echo $_SERVER['HTTP_HOST'];?><?php echo $_SERVER['REQUEST_URI'];?>" num_posts="10" width="770"></fb:comments>
			</div>
		</div>
		<!--  
		<pre>
			<?php print_r($row);?>
		</pre>
		-->
		

<?php
include("html_footer.php");