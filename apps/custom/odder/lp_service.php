<?php
include("html_header.php");
include("controller/lp_service_c.php");
echo "<span style='font-size:18px'>{$plannr}</span>";
if ($row['status']=='forslag') {?>
<h1 class="byline"><?php echo $row['status'] ?></h1>
<p><?php echo date('j/n/y',$row['datostart']) ?> - <?php echo  date('d/n/y',$row['datoslut']) ?></p>
<p>Giv din mening til kende! <a href="/dk/lokalplan_<?php echo $plannr;?>/hoering.htm">Klik her</a></p>
<?php } ?>

<?php if ($row['status']=='vedtaget') {?>
<h1 class="byline"><?php echo $row['status'] ?></h1>
<p>Godkendt i Byr&aring;det d. <?php echo date('j/n/y',$row['datovedt']) ?></p>
<?php } ?>