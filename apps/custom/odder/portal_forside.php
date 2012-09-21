<?php
$postgisdb="odder";
include '../../../conf/main.php';
include 'libs/functions.php';
include 'model/tables.php';
include "controller/portal_forside_c.php";
//print_r($row);
?>
<body>
<!-- include jQuery library -->
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.5/jquery.min.js"></script>
<!-- include Cycle plugin -->
<script type="text/javascript" src="http://cloud.github.com/downloads/malsup/cycle/jquery.cycle.all.latest.js"></script>
<script type="text/javascript">
$(document).ready(function() {
    $('.slideshow').cycle({
		fx: 'fade' // choose your transition type, ex: fade, scrollUp, shuffle, etc...
	});
});
</script>
<script type="text/javascript">

$(document).ready(function() {		
	
	//Execute the slideShow
	slideShow();

});

function slideShow() {

	//Set the opacity of all images to 0
	$('#gallery a').css({opacity: 0.0});
	
	//Get the first image and display it (set it to full opacity)
	$('#gallery a:first').css({opacity: 1.0});
	
	//Set the caption background to semi-transparent
	$('#gallery .caption').css({opacity: 0.7});

	//Resize the width of the caption according to the image width
	$('#gallery .caption').css({width: $('#gallery a').find('img').css('width')});
	
	//Get the caption of the first image from REL attribute and display it
	$('#gallery .content').html($('#gallery a:first').find('img').attr('rel'))
	.animate({opacity: 0.7}, 400);
	
	//Call the gallery function to run the slideshow, 6000 = change to next image after 6 seconds
	setInterval('gallery()',6000);
	
}

function gallery() {
	
	//if no IMGs have the show class, grab the first image
	var current = ($('#gallery a.show')?  $('#gallery a.show') : $('#gallery a:first'));

	//Get next image, if it reached the end of the slideshow, rotate it back to the first image
	var next = ((current.next().length) ? ((current.next().hasClass('caption'))? $('#gallery a:first') :current.next()) : $('#gallery a:first'));	
	
	//Get next image caption
	var caption = next.find('img').attr('rel');	
	
	//Set the fade in effect for the next image, show class has higher z-index
	next.css({opacity: 0.0})
	.addClass('show')
	.animate({opacity: 1.0}, 1000);

	//Hide the current image
	current.animate({opacity: 0.0}, 1000)
	.removeClass('show');
	
	//Set the opacity to 0 and height to 1px
	$('#gallery .caption').animate({opacity: 0.0}, { queue:false, duration:50 }).animate({height: '1px'}, { queue:true, duration:300 });	
	
	//Animate the caption, opacity to 0.7 and heigth to 100px, a slide up effect
	$('#gallery .caption').animate({opacity: 0.7},100 ).animate({height: '50px'},500 );
	
	//Display the content
	$('#gallery .content').html(caption);
	
	
}
</script>
<style type="text/css">
.clear {
	clear:both
}
#gallery {
	position:relative;
	height:430px
}
	#gallery a {
		float:left;
		position:absolute;
	}
	
	#gallery a img {
		border:none;
	}
	
	#gallery a.show {
		z-index:500
	}

	#gallery .caption {
		z-index:600; 
		background-color:#000; 
		color:#ffffff; 
		height:50px; 
		width:100%; 
		position:absolute;
		bottom:0;
	}

	#gallery .caption .content {
		margin:5px
	}
	
	#gallery .caption .content h3 {
		margin:0;
		padding:0;
		color:#1DCCEF;
	}
	

</style>
<div style="margin-left:75px">
<div id="gallery">
	<?php
	$i=1;
	foreach($row as $value) {
	if ($i==1) $active = "show";
	else $active = "";
	?>
	
	  <a class="<?php echo $active ?>" target='_blank' href='/dk/lokalplan_nr_<?php echo $value['plannr'] ?>'><img alt='' title='' rel='Lokalplan nr. <?php echo $value['plannr'] ?> <?php echo $value['plannavn'] ?>' src="http://odder-lp.cowi.webhouse.dk/<?php echo $value['forsidebillede'] ?>" width='400' height='430' /></a>
	  

	  <?php
		$i++;
	  }
	  ?>
	<div class="caption"><div class="content" style="color:white"></div></div>
</div>
<div>
<p>
Her kan du se de seneste lokalplaner.
</p>
</div>
</div>



</body>
<?php
