<?php
include("html_header.php");
include("controller/lp_service_c.php");
echo "<div>Lokalplan nr. {$plannr}</div><div stydle='font-size:12px;margidn-top:5px'>";?>
<div>&nbsp;</div>
<?php
if ($row['status']=='kladde' || $row['status']=='') {
	echo "<div>Kladde</div>";
}
elseif ($row['status']=='forslag') {
	if ($time>$row['datostart'] && $time<$row['datoslut']) {?>
		<div>I h&oslash;ring fra<br/><?php echo date('j/n/Y',$row['datostart']) ?> - <?php echo  date('d/n/Y',$row['datoslut']) ?></div>
		<div>&nbsp;</div>
		<div>Giv din mening til kende! <a target="_blank" href="http://ishoej-lp.cowi.webhouse.dk/dk/portalforside/dialog.htm?plannr=<?php echo $plannr ?>">Klik her</div>
<?php }
	else {?>
		<div>Har v&aelig;ret i h&oslash;ring fra <?php echo date('j/n/y',$row['datostart']) ?> - <?php echo  date('j/n/y',$row['datoslut']) ?></div>
<?php }} ?>

<?php if ($row['status']=='vedtaget') {?>
<div>Godkendt i Byr&aring;det d. <?php echo date('j/n/y',$row['datovedt']) ?></div>
<div>&nbsp;</div>
<div>Offentliggjort d. <?php echo date('j/n/y',$row['datoikraft']) ?></div>
<?php } ?>
