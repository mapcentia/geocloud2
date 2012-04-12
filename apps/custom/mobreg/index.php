<?php
include("html_header.php");
include("controller/table_c.php");
//print_r($row_INI_bnr );
//print_r($row_INI_bygning );
//print_r($rows_INI_aktiviteter );
//print_r($rows_INI_standardbygningsdele);
//print_r($rows_INI_standardgrupper_af_bygningsdele);
?>
<body>
	<div class="navbar navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </a>
          <a class="brand" href="/">Tilstandsvurdering</a>
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
	<div><h3>Bygningstype: <?php echo $row_INI_bygning['bygningstype'];?><h3></div>
	<div><h3>B-nr: <?php echo $row_INI_bygning['bnr_nuuk'];?><h3></div>
	<div><h3>Bygningsnavn: <?php echo $row_INI_bnr['textvalue'];?><h3></div>
	<div class="well"><h3>Oversigtsfoto af bygning</h3></div>
	<div class="well">
		<h3>Bygningsbeskrivelse</h3>
		<p><?php echo $row_INI_bygning['bygningsbeskrivelse'];?></p>
	</div>
	<div class="well">
		<h3>Bo kvalitet og herlighedsværdi</h3>
		<p><?php echo $row_INI_bygning['bo_kvalitet_og_herlighedsvaerdi'];?></p>
	</div>
	<div class="well">
		<h3>Bygningens tilstand</h3>
		<p><?php echo $row_INI_bygning['bygningens_tilstand'];?></p>
	</div>
	<div class="well">
		<h3>Nødvendige tiltag</h3>
		<?php
			$i=1;
			foreach($rows_INI_aktiviteter as $row){
				echo "<p>{$i} {$row['aktiviteter_terraen_adgangsforhold']} {$row['bygningsdel_konstruktioner']}</p>";
				$i++;
			}
		?>
	</div>
	<div class="well">
		<h3>Økonomi</h3>
		<p><?php //echo ;?></p>
	</div>
	<div class="well">
		<h3>Samlet indstilling</h3>
		<p><?php echo $row_INI_bygning['samlet_indstilling'] ;?></p>
	</div>
	<header class="jumbotron subhead" id="overview">
        <h2>Bygningsdele</h2>
        <p class="lead"></p>
        <div class="subnav">
          <ul class="nav nav-pills">
			<?php foreach($rows_INI_standardbygningsdele as $row) {?>
            <li><a href="#<?php echo $row['textvalue'];?>"><?php echo $row['textvalue'];?></a></li>
			<?php } ?>
          </ul>
        </div>
      </header>
	<?php
		foreach($rows_INI_standardbygningsdele as $row) {
			
			
		?>
			<section id="<?php echo $row['textvalue'];?>">
				<div class="well">
					<p>Bygningsdel</p>
					<h3><?php echo $row['textvalue'];?> (<?php echo $row['fieldkey'];?>)</h3>
					<hr/>
					
					<div>
						<div><p><strong>Foto</strong></p></div>
						<?php //print_r($row);?>
						<?php $str= strtolower($row['textvalue']);

							$str = str_replace(" ","_",$str);
							$str = str_replace("æ","ae",$str);
							$str = str_replace("ø","oe",$str);
							$str = str_replace("Ø","oe",$str);
							$str = str_replace("å","aa",$str);
							//echo $str;
						?>
						<p><strong>Bygningsdelsbeskrivelse</strong></p>
						<p><?php echo $row_INI_bygning["opbygning_og_materialer_{$str}"];?></p>
						<p><strong>Tilstand</strong></p>
						<p><?php echo $row_INI_bygning["karakter_{$str}"];?></p>
						<p><strong>Renoveringsforslag</strong></p>
						<p><?php echo $row_INI_bygning["skader_{$str}"];?></p>
						<p>
						<table class="table table-bordered">
							<thead>
								<tr>
								  <th>Betegnelse</th>
								  <th>Mængde</th>
								  <th>Enhed</th>
								  <th>Enhedspris</th>
								  <th>I alt kr.</th>
								  <th>I heraf</th>
								</tr>
							  </thead>
							  <tbody>
								<tr>
								  <td colspan="5"></td>
								  <td>Forbedringer</td>
								  <td>Energi</td>
								</tr>
						<?php foreach($rows_INI_aktiviteter as $aktivitet) {
							if ($aktivitet['bygningsdel_konstruktioner']==$row['fieldkey']) {
								foreach ($rows_INI_standardgrupper_af_bygningsdele as $standardgruppe) {
									if ($standardgruppe['fieldkey']==$aktivitet['bygningsdelsgruppe']){
										//echo $standardgruppe['textvalue'];
									}	
								}
								echo "<tr><td>{$aktivitet['irowid']}</td></tr>";
								//print_r($aktivitet);
							}
						} ?>
							</tbody>
						</table>
						</p>
					</div>
				</div>
			</section>
			
	<?php } ?>
	</div> <!-- container -->
	
	
<?php include("html_footer.php");?>