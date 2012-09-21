<?php
include("controller/table_c.php");
function fotoUrl($str,$thumb){
	$fotoArr = explode("/",$str);
	$newFotoUrl = "/{$fotoArr[1]}/{$fotoArr[2]}/{$fotoArr[3]}/{$thumb}/{$fotoArr[4]}";
	return $newFotoUrl;
}
?>
<link rel="stylesheet" type="text/css" href="http://beta.mygeocloud.cowi.webhouse.dk/js/ext/resources/css/ext-all.css"/>
<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/api/v1/js/api.js"></script>
<script type="text/javascript">
var styleMapBoringer = new OpenLayers.StyleMap({
	"default": new OpenLayers.Style({
		pointRadius: 5,
		fillColor: "#ffffff",
		fillOpacity: 0.0,
		strokeColor: "#0000aa",
		strokeWidth: 2,
		graphicZIndex: 3
		}
	),
	"select": new OpenLayers.Style({
		pointRadius: 5,
		fillColor: "#0000aa",
		fillOpacity: 0.5,
		strokeColor: "#0000aa",
		strokeWidth: 2,
		graphicZIndex: 3
		}
        )
});
  $(window).load(function() {
		var store = new mygeocloud_ol.geoJsonStore("rebild",{
			projection : "25832"
		});

		store.sql = "SELECT * FROM public.bbox";
		store.load();
		store.onLoad = function(){
			cloud = new mygeocloud_ol.map("map","rebild",{
				numZoomLevels : 15,
				projection : "EPSG:25832",
				maxResolution : "auto",
				minResolution : 0.2,
				maxExtent: store.layer.getDataExtent()

			});

		var b = cloud.addTileLayers(["public.b2762"],{isBaseLayer:true});
		cloud.addTileLayers(["public.dk_ddoland2010_125mm_utm32etrs89"],{isBaseLayer:true});
		cloud.addTileLayers(["vandforsyningsplan._147915","vandforsyningsplan._159382","vandforsyningsplan._159383","vandforsyningsplan._160206","vandforsyningsplan.dkjord_v1","vandforsyningsplan.dkjord_v2","vandforsyningsplan._160095","vandforsyningsplan.boringer"],{singleTile:true,opacity:0.6});
		cloud.map.setBaseLayer(b[0]);
		
		var store2 = new mygeocloud_ol.geoJsonStore("rebild",{
			projection : "25832"
		});
		<?php if (!$row['buffer']) $row['buffer']="500";?>
		<?php if(sizeof($rowBoringer)>0){ ?>
		store2.sql = "SELECT buffer(vandforsyningsplan.anlaeg.the_geom,<?php echo $row['buffer'];?>) as g1,buffer(vandforsyningsplan.boringer.the_geom,<?php echo $row['buffer'];?>) as g2 FROM vandforsyningsplan.anlaeg,vandforsyningsplan.boringer WHERE vandforsyningsplan.anlaeg.id='<?php echo $row['id'];?>' AND plant_id='<?php echo $row['id'];?>'";
		<?php } else {?>
		store2.sql = "SELECT buffer(vandforsyningsplan.anlaeg.the_geom,<?php echo $row['buffer'];?>) as g1 FROM vandforsyningsplan.anlaeg WHERE vandforsyningsplan.anlaeg.id='<?php echo $row['id'];?>'";
		<?php } ?>
		store2.load();
		store2.onLoad = function(){
			cloud.zoomToExtentOfgeoJsonStore(store2);
		};
		

		var storeAnlaeg = new mygeocloud_ol.geoJsonStore("rebild",{
			//styleMap: styleMap,
			projection : "25832"
		});
		
		//cloud.addGeoJsonStore(storeAnlaeg);
		storeAnlaeg.sql = "SELECT * FROM vandforsyningsplan.anlaeg WHERE id='<?php echo $row['id'];?>'";
		storeAnlaeg.load();
		storeAnlaeg.onLoad = function(){
			//cloud.zoomToExtentOfgeoJsonStore(storeAnlaeg);
		};
		
		var storeBoringer = new mygeocloud_ol.geoJsonStore("rebild",{
			styleMap: styleMapBoringer,
			projection : "25832"
		});
		//cloud.addGeoJsonStore(storeBoringer);
		storeBoringer.selectFeatureControl.activate();
		storeBoringer.sql = "SELECT dgu_nr,status,placering,the_geom FROM vandforsyningsplan.boringer WHERE plant_id='<?php echo $row['id'];?>'";
		storeBoringer.load();
		storeBoringer.onLoad = function(){
					 //grid = new mygeocloud_ol.grid("grid",storeBoringer,{height: 200});
		};
	};
});
</script>
<style> 
.datablad{
	width:600px
}
.datablad td{
	border-collapse:collapse;
	border:1px solid silver;
	padding-left:20px;
	padding-right:20px;
	padding-top:3px;
	padding-bottom:3px;
	background-color: #ffffff;
}
.overskrift_datablad{
	border:0px !important;
	width: 190px !important;
	background-color: #DAE5F2 !important;
}
.datablad .image{
	border-collapse:collapse;
	border:1px solid silver;
	padding-left:0px;
	padding-right:0px;
	padding-top:0px;
	padding-bottom:0px;
	display: block;
	width:298px;
}
.datablad .image p{
	margin-bottom: 0px !important;
}
.vurdering1 {
	background-color:#94b2d6 !important;
	position: relative;
	-webkit-print-color-adjust:exact;
}
.vurdering2 {
	background-color:#94d352 !important; 
	position: relative;
	-webkit-print-color-adjust:exact; 
}
.vurdering3 {
	background-color:#d6e3bd !important;
	position: relative;
	-webkit-print-color-adjust:exact;
}
.vurdering4 {
	background-color:#ffc300 !important;
	position: relative;
	-webkit-print-color-adjust:exact;
}
.vurdering5 {
	background-color:#ff3000 !important;
	position: relative;
	-webkit-print-color-adjust:exact;
}
.rotate{
	
	position:relative;
	display:block;
	width:0px;
	left:0px;
	bottom: -20px;
	-webkit-transform: rotate(-90deg); 
    -moz-transform: rotate(-90deg);
	
}
.legendContent tr td{
	 border:0px;
	 padding:0px;
}
</style>
<!--[if IE]>
	<style>
		.rotate {
			filter: progid:DXImageTransform.Microsoft.BasicImage(rotation=3);
			white-space: nowrap;
            position: absolute;
			left: 15px;
			top:10px

		}
	</style>
<![endif]-->

<b><?php echo $row['navn_paa_vandvaerk'];?></b>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="image">
  <p><img width=598 height=451
  src="<?php echo fotoUrl($row['billede_anlaeg'],"thumbs1");?>"></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Generelle data</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Anl&aelig;gsid</p>
  </td>
  <td>
  <p><?php echo $row['id'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Navn</p>
  </td>
  <td>
  <p><?php echo $row['navn_paa_vandvaerk'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Adresse</p>
  </td>
  <td>
  <p><?php echo $row['gade'];?> <?php echo $row['husnr'];?>, <?php echo $row['postnr'];?> <?php echo $row['by'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Kontaktperson</p>
  </td>
  <td>
  <p><?php echo $row['kontaktperson'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Telefon</p>
  </td>
  <td>
  <p><?php if($row['telefon1']!="-") echo $row['telefon1'];?> <?php if($row['telefon2']!="-") echo $row['telefon2'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Dato for besigtigelse</p>
  </td>
  <td>
  <p><?php echo $row['tilsynsdato'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Indvinding og vandforbrug</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Indvindingstilladelse</p>
  </td>
  <td>
  <p><?php echo $row['indvindingstilladelse'];?> m<sup>3</sup>, udl&oslash;ber <?php echo $row['tilladelsen_udloeber'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Indvinding i 2010</p>
  </td>
  <td>
  <p><?php echo $row['indvinding_2010'];?> m<sup>3</sup></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Vand leveret til forbruger</p>
  </td>
  <td>
  <p><?php echo $row['vand_leveret_til_forbruger'];?> m<sup>3</sup></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Vand forbrugt p&aring; Vandv&aelig;rk</p>
  </td>
  <td>
  <p><?php echo $row['vand_forbrugt_paa_vandvaerk'];?> m<sup>3</sup></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Vand k&oslash;bt eller leveret til andet vandv&aelig;rk</p>
  </td>
  <td>
  <p><?php echo $row['vand_koebt_eller_leveret_til_andet_vandvaerk'];?> m<sup>3</sup></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Vandspild</p>
  </td>
  <td>
  <p><?php echo $row['vandspild'];?> %</p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Udvikling i indvinding (m<sup>3</sup>/&aring;r)</b></p>
  </td>
 </tr>
 <tr>
  <td class="image">

  <p><img src="http://chart.apis.google.com/chart?chxr=0,0,<?php echo (ceil(max($amount)/1000))*1000; ?>&chds=0,<?php echo (ceil(max($amount)/1000))*1000;?>&chxt=y,x&chbh=a&chs=598x300&cht=bvg&chco=A2C180,3D7930&chd=t:<?php echo implode(",",$amount)?>&chxl=1:|<?php echo implode("|",$year)?>"></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Forbrugertyper </b></p>
  </td>
  <td class="overskrift_datablad">
  <p><b>Antal</b></p>
  </td>
  <td class="overskrift_datablad">
  <p><b>Forbrug m<sup>3</sup></b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Parcelhuse</p>
  </td>
  <td>
  <p><?php echo $row['parcelhuse_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['parcelhuse_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Etageboliger</p>
  </td>
  <td>
  <p><?php echo $row['etageboliger_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['etageboliger_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Landhuse</p>
  </td>
  <td>
  <p><?php echo $row['landhuse_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['landhuse_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Fritidshuse</p>
  </td>
  <td>
  <p><?php echo $row['fritidshuse_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['fritidshuse_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Landbrugsdrift</p>
  </td>
  <td>
  <p><?php echo $row['landbrugsdrift_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['landbrugsdrift_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Landbrugsdrift m. dyrehold</p>
  </td>
  <td>
  <p><?php echo $row['landbrugsdrift_m_dyrehold_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['landbrugsdrift_m_dyrehold_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Gartneri</p>
  </td>
  <td>
  <p><?php echo $row['gartneri_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['gartneri_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Virksomheder</p>
  </td>
  <td>
  <p><?php echo $row['virksomheder_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['virksomheder_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Institutioner (skoler, b&oslash;rnehaver og lign.)</p>
  </td>
  <td>
  <p><?php echo $row['institutioner_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['institutioner_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Hotel, campingpladser</p>
  </td>
  <td>
  <p><?php echo $row['hotel_campingpladser_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['hotel_campingpladser_forbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Andre erhverv</p>
  </td>
  <td>
  <p><?php echo $row['andre_erhverv_antal'];?></p>
  </td>
  <td>
  <p><?php echo $row['andre_erhverv_forbrug'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Kapacitet</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Indvinding</p>
  </td>
  <td>
  <p><?php echo $row['indvinding'];?> m<sup>3</sup>/t</p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Behandling</p>
  </td>
  <td>
  <p><?php echo $row['behandling'];?> m<sup>3</sup>/t</p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Rentvandsbeholder, kapacitet</p>
  </td>
  <td>
  <p><?php echo $row['beholder_kapacitet'];?> <?php if ($row['beholder_kapacitet']) echo " m<sup>3</sup>"; else echo "&nbsp;"?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Udpumpning</p>
  </td>
  <td>
  <p><?php echo $row['udpumpning'];?> m<sup>3</sup>/t</p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Evnefaktor d&oslash;gn/time </p>
  </td>
  <td>
  <p><?php echo $row['evnefaktor_doegn'];?>/<?php echo $row['evnefaktor_time'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Oversigtskort</b></p>
  </td>
 </tr>
 <tr>
  <td class="image">
  <div id="map" style="width: 598px;height: 500px;p"></div>
  <!--<div id="grid" style="width: 600px;height: 210px"></div>-->
  <div class="legendContent"><table width="1"><tr><td>
  <div class="legendContent"><table><tbody><tr><td><img src="http://drift.kortinfo.net/Wms.aspx?Site=Rebild&amp;Page=Vandforsyningsplan&amp;service=WMS&amp;VERSION=1.1.0&amp;format=image/png&amp;style=&amp;layer=147915&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr><tr><td><img src="http://mygeocloud.cowi.webhouse.dk/wms/rebild/vandforsyningsplan/?LAYER=vandforsyningsplan.boringer&amp;SERVICE=WMS&amp;VERSION=1.1.1&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr><tr><td><img src="http://drift.kortinfo.net/Wms.aspx?Site=Rebild&amp;Page=Vandforsyningsplan&amp;service=WMS&amp;VERSION=1.1.0&amp;format=image/png&amp;style=&amp;layer=160095&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr><tr><td><img src="http://kort.arealinfo.dk/wms?servicename=landsdaekkende_wms&amp;VERSION=1.1.1&amp;SERVICE=wms&amp;REQUEST=GetMap&amp;layer=dkjord-v1&amp;format=image/png&amp;style=&amp;TRANSPARENT=true&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr><tr><td><img src="http://kort.arealinfo.dk/wms?servicename=landsdaekkende_wms&amp;VERSION=1.1.1&amp;SERVICE=wms&amp;REQUEST=GetMap&amp;layer=dkjord-v2&amp;format=image/png&amp;style=&amp;TRANSPARENT=true&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr></tbody></table></div>
  </td>
  <td>
  <div class="legendContent"><table><tbody><tr><td><img src="http://drift.kortinfo.net/Wms.aspx?Site=Rebild&amp;Page=Vandforsyningsplan&amp;service=WMS&amp;VERSION=1.1.0&amp;format=image/png&amp;style=&amp;layer=159382&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr><tr><td><img src="http://drift.kortinfo.net/Wms.aspx?Site=Rebild&amp;Page=Vandforsyningsplan&amp;service=WMS&amp;VERSION=1.1.0&amp;format=image/png&amp;style=&amp;layer=159383&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr><tr><td><img src="http://drift.kortinfo.net/Wms.aspx?Site=Rebild&amp;Page=Vandforsyningsplan&amp;service=WMS&amp;VERSION=1.1.0&amp;format=image/png&amp;style=&amp;layer=160206&amp;REQUEST=getlegendgraphic&amp;FORMAT=image/png"></td></tr></tbody></table></div>
  </td></tr></table></div>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Boringer</b></p>
  </td>
 </tr>
</table>
<?php 
if(sizeof($rowBoringer)>0){
foreach($rowBoringer as $boring) {
	echo "<p></p>";
	echo '<table class="datablad" cellspacing="0" cellpadding="0">';
	echo "<tr><td class='overskrift_datablad'><b>DGU nr.</b></td><td class='overskrift_datablad'><b>{$boring['dgu_nr']}</b></td></tr>";
	echo "<tr><td>Status</td><td>{$boring['status']}</td></tr>";
	echo "<tr><td>Placering</td><td>{$boring['placering']}</td></tr>";
	echo "<tr><td>Udf&oslash;relses&aring;r</td><td>{$boring['udfoerelse']}</td></tr>";
	echo "<tr><td>Koordinater x, y (Utm32E89)</td><td>{$boring['koordinate']}</td></tr>";
	echo "<tr><td>Terr&aelig;nkote (DVR90)</td><td>{$boring['terraenkot']} m</td></tr>";
	echo "<tr><td>Boredybde</td><td>{$boring['boredybde']} m</td></tr>";
	echo "<tr><td>Filterinterval</td><td>{$boring['filterinte']} m.u.t.</td></tr>";
	echo "<tr><td>Diameter forer&oslash;r / filter</td><td>{$boring['diameter']} mm</td></tr>";
	echo "<tr><td>Vandf&oslash;rende lag</td><td>{$boring['lag']}</td></tr>";
	echo "<tr><td>Rovandspejl</td><td>{$boring['rovandspej']} m.u.t.</td></tr>";
	echo "<tr><td>R&aring;vandspumpe</td><td>{$boring['raavandspu']}</td></tr>";
	echo "<tr><td>Pumpeydelse</td><td>{$boring['pumpeydels']} m<sup>3</sup>/t</td></tr>";
	echo "<tr><td>Afslutning af boring</td><td>{$boring['af_boring']}</td></tr>";
	echo "<tr><td>Beskyttelseszone</td><td>{$boring['beskyttels']} m</td></tr>";
	echo "<tr><td>Indvindingsstrategi</td><td>{$boring['indvinding']}</td></tr>";
	echo "<tr><td>Arealanvendelse i n&aelig;romr&aring;de</td><td>{$boring['naeromraad']}</td></tr>";
	echo "<tr><td>Forureningskilder i n&aelig;romr&aring;de</td><td>{$boring['naeromra0']}</td></tr>";
	echo "</table>";
}
}
else {
	echo '<table class="datablad" cellspacing="0" cellpadding="0">';
	echo "<tr><td>Ingen boringer</td></tr>";
	echo "</table>";
}
?>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Foto af kildepladser</b></p>
  </td>
 </tr>
 <tr>
 <td class="image">
 <?php if($row['billede_kildeplads1']) { ?>
  <p><img
  width=298 height=226 src="<?php echo fotoUrl($row['billede_kildeplads1'],"thumbs1");?>"></p>
<?php } ?>
 </td>
  <td class="image">
<?php if($row['billede_kildeplads2']) { ?>
  <p><img
  width=298 height=226 src="<?php echo fotoUrl($row['billede_kildeplads2'],"thumbs1");?>"></p>
<?php } ?>
</td>
</tr>
<tr>
<td class="image">
<?php if($row['billede_kildeplads3']) { ?>
  <p><img
  width=298 height=226 src="<?php echo fotoUrl($row['billede_kildeplads3'],"thumbs1");?>"></p>
<?php } ?>
</td>
<td class="image">
<?php if($row['billede_kildeplads4']) { ?>
  <p><img
  width=298 height=226 src="<?php echo fotoUrl($row['billede_kildeplads4'],"thumbs1");?>"></p>
<?php } ?>
 </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Teknisk anl&aelig;g</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Iltningsmetode</p>
  </td>
  <td>
  <p><?php echo $row['iltningsmetode'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Filtrering</p>
  </td>
  <td>
  <p><?php echo $row['filtrering'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Antal filtre og type</p>
  </td>
  <td>
  <p><?php echo $row['antal_filtre_og_type'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Filterareal/-kapacitet (total)</p>
  </td>
  <td>
  <p><?php echo $row['filterareal_kapacitet_total'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Filterskyl metode / hyppighed</p>
  </td>
  <td>
  <p><?php echo $row['filterskyl_metode_hyppighed'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Skyllevandsm&aelig;ngde/-kapacitet</p>
  </td>
  <td>
  <p><?php echo $row['skyllevandsmaengde_kapacitet'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Skyllevandsafledning</p>
  </td>
  <td>
  <p><?php echo $row['skyllevandsafledning'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Rentvandsbeholder type</p>
  </td>
  <td>
  <p><?php echo $row['rentvandsbeholder_type'];?></p>
  </td>
 </tr>
  <tr>
  <td>
  <p>Rentvandsbeholder placering</p>
  </td>
  <td>
  <p><?php echo $row['rentvandsbeholder_placering'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Senest inspektion af rentvandstank</p>
  </td>
  <td>
  <p><?php echo $row['senest_inspektion_af_rentvandstank'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Tils&aelig;tningsanl&aelig;g</p>
  </td>
  <td>
  <p><?php echo $row['tilsaetningsanlaeg'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Rentvandspumper</p>
  </td>
  <td>
  <p><?php echo $row['rentvandspumper'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Pumpestyring</p>
  </td>
  <td>
  <p><?php echo $row['pumpestyring'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Afgangstryk</p>
  </td>
  <td>
  <p><?php echo $row['afgangstryk_bar'];?> Bar</p>
  </td>
 </tr> 
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Foto af filter / hydrofor</b></p>
  </td>
  <td class="overskrift_datablad">
  <?php
  if ($row['billede_pumpe']) {?>
  <p><b>Foto af rentvandspumper</b></p>
  <?php }?>
  </td>
 </tr>
 <tr>
  <td class="image">
  <?php
  if ($row['billede_filter']) {?>
  <p><img
  width=300 height=226 src="<?php echo fotoUrl($row['billede_filter'],"thumbs1");?>"></p>
  <?php }?>
  </td>
  <td class="image">
  <?php
  if ($row['billede_pumpe']) {?>
  <p><img
  width=300 height=226 src="<?php echo fotoUrl($row['billede_pumpe'],"thumbs1");?>"></p>
  <?php }?>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Flow diagram</b></p>
  </td>
 </tr>
 <tr>
  <td class="image">
  <p><img
  width=598 height=426 src="<?php echo fotoUrl($row['billede_flowdiagram'],"thumbs1");?>"></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Ledningsnet</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>L&aelig;ngde, ca.</p>
  </td>
  <td>
  <p><?php echo $row['laengde_km'];?> km</p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Alder, ca.</p>
  </td>
  <td>
  <p><?php echo $row['alder_ca_aar'];?> &aring;r</p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Materialer</p>
  </td>
  <td>
  <p><?php echo $row['materialer'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Ledningsplaner</p>
  </td>
  <td>
  <p><?php echo $row['ledningsplaner'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Trykfor&oslash;ger</p>
  </td>
  <td>
  <p><?php echo $row['trykforoeger'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Forsyningssikkerhed</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Alarmer</p>
  </td>
  <td>
  <p><?php echo $row['har_vandvaerket_alarmer'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>N&oslash;dstr&oslash;msforsyning</p>
  </td>
  <td>
  <p><?php echo $row['har_vandvaerket_noedstroemsforsyning'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Forbindelsesledning til
  anden vandforsyning</p>
  </td>
  <td>
  <p><?php echo $row['har_vandvaerket_forbindelsesledning_til_anden_vandforsyning'];?></p>
  </td>
 </tr>
  <tr>
  <td>
  <p>Forsyningssikkerhedsm&aelig;ssige overvejelser</p>
  </td>
  <td>
  <p><?php echo $row['vandvaerkets_planer'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Beredskabsplan</p>
  </td>
  <td>
  <p><?php echo $row['har_vandvaerket_en_beredskabsplan'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Parallelle proceslinier,
  s&aring;ledes at driften kan opretholdes under visse reparationer</p>
  </td>
  <td>
  <p><?php echo $row['har_vandvaerket_parallelle_proceslinier'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Sikret mod forurening af
  kildepladsen</p>
  </td>
  <td>
  <p><?php echo $row['er_vandvaerket_sikret_mod_forurening_af_kildepladsen'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>R&aring;vandskvalitet </b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Kemiske hovedbestanddele</p>
  </td>
  <td>
  <p><?php echo $row['raavandskvalitet_kemiske_hovedbestanddele'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Mikrobiologi</p>
  </td>
  <td>
  <p><?php echo $row['raavandskvalitet_mikrobiologi'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Uorganiske spormetaller</p>
  </td>
  <td>
  <p><?php echo $row['raavandskvalitet_metaller'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Milj&oslash;fremmede stoffer</p>
  </td>
  <td>
  <p><?php echo $row['raavandskvalitet_miljoefremmede_stoffer'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Rentvandskvalitet </b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Kemiske hovedbestanddele</p>
  </td>
  <td>
  <p><?php echo $row['rentvandskvalitet_kemiske_hovedbestanddele'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Mikrobiologi</p>
  </td>
  <td>
  <p><?php echo $row['rentvandskvalitet_mikrobiologi'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Uorganiske spormetaller</p>
  </td>
  <td>
  <p><?php echo $row['rentvandskvalitet_metaller'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Milj&oslash;fremmede stoffer</p>
  </td>
  <td>
  <p><?php echo $row['rentvandskvalitet_miljoefremmede_stoffer'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad" width="450px">
  <p><b>Administration og &oslash;konomi</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Takst politik</p>
  </td>
  <td>
  <p><?php echo $row['takst_politik'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Investeringsplan</p>
  </td>
  <td>
  <p><?php echo $row['investeringsplan'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Vedligeholdelsesplan</p>
  </td>
  <td>
  <p><?php echo $row['vedligeholdelsesplan'];?></p>
  </td>
 </tr>
</table>
<p></p>
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Fremtidig udvikling</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Udvikling i vandforbrug</p>
  </td>
  <td>
  <p><?php echo $row['udvikling_i_vandforbrug'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Problemer for den videre drift</p>
  </td>
  <td>
  <p><?php echo $row['problemer_for_den_videre_drift'];?></p>
  </td>
 </tr>
</table>
<p></p>
<!-- Vurdering start-->
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Samlet vurdering</b></p>
  </td>
 </tr>
 <tr style="height:100px;">
  <td style="width:10px">
  <p><b>Emne</b></p>
  </td>
  <td class="vurdering1"><div class="rotate">Særdeles god</div></td>
  <td class="vurdering2"><div class="rotate">God</div></td>
  <td class="vurdering3"><div class="rotate">Acceptabel</div></td>
  <td class="vurdering4"><div class="rotate">Dårlig</div></td>
  <td class="vurdering5"><div class="rotate">Meget dårlig</div></td>
  <td>
  <p><b>Begrundelse</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Kapacitet</p>
  </td>
  <td class="<?php if ($row['vurdering_kapacitet']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kapacitet']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kapacitet']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kapacitet']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kapacitet']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_kapacitet'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Boringer</p>
  </td>
  <td class="<?php if ($row['vurdering_boringer']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_boringer']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_boringer']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_boringer']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_boringer']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_boringer'];?></p>
  </td>
 <tr>
  <td>
  <p>Kildepladsen</p>
  </td>
  <td class="<?php if ($row['vurdering_kildepladsen']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kildepladsen']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kildepladsen']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kildepladsen']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_kildepladsen']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_kildepladsen'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Bygningerne</p>
  </td>
  <td class="<?php if ($row['vurdering_bygningerne']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_bygningerne']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_bygningerne']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_bygningerne']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_bygningerne']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_bygning'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Tekniske installationer</p>
  </td>
  <td class="<?php if ($row['vurdering_tekniske_installationer']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_tekniske_installationer']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_tekniske_installationer']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_tekniske_installationer']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_tekniske_installationer']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
 
  <td>
  <p><?php echo $row['begrundelse_tekniske_installationer'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Ledningsnet</p>
  </td>
  <td class="<?php if ($row['vurdering_ledningsnet']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_ledningsnet']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_ledningsnet']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_ledningsnet']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_ledningsnet']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_ledningsnet'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Hygiejne</p>
  </td>
  <td class="<?php if ($row['vurdering_hygiejne']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_hygiejne']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_hygiejne']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_hygiejne']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_hygiejne']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_hygiejne'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Forsyningssikkerhed</p>
  </td>
  <td class="<?php if ($row['vurdering_forsyningssikkerhed']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_forsyningssikkerhed']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_forsyningssikkerhed']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_forsyningssikkerhed']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_forsyningssikkerhed']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_forsyningssikkerhed'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>R&aring;vandskvalitet</p>
  </td>
  <td class="<?php if ($row['vurdering_raavandskvalitet']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_raavandskvalitet']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_raavandskvalitet']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_raavandskvalitet']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_raavandskvalitet']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_raavandskvalitet'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Rentvandskvalitet</p>
  </td>
  <td class="<?php if ($row['vurdering_rentvandskvalitet']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_rentvandskvalitet']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_rentvandskvalitet']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_rentvandskvalitet']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_rentvandskvalitet']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_rentvandskvalitet'];?></p>
  </td>
 </tr>
 <tr>
  <td>
  <p>Administration og &oslash;konomi</p>
  </td>
  <td class="<?php if ($row['vurdering_administration_og_oekonomi']=="Særdeles god") echo "vurdering1"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_administration_og_oekonomi']=="God") echo "vurdering2"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_administration_og_oekonomi']=="Acceptabel") echo "vurdering3"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_administration_og_oekonomi']=="Dårlig") echo "vurdering4"?>">&nbsp;</td>
  <td class="<?php if ($row['vurdering_administration_og_oekonomi']=="Meget dårlig") echo "vurdering5"?>">&nbsp;</td>
  <td>
  <p><?php echo $row['begrundelse_administation_oekonomi'];?></p>
  </td>
 </tr>
</table>
<!-- Vurdering slut-->
<p></p>
<!--
<table class="datablad" cellspacing="0" cellpadding="0">
 <tr>
  <td class="overskrift_datablad">
  <p><b>Anbefalinger</b></p>
  </td>
 </tr>
 <tr>
  <td>
  <p><?php echo $row['anbefalinger'];?></p>
  </td>
 </tr>
</table>
-->
<br/>
<br/>
<p><b>Andre anlæg tilhørende <?php echo $row['navn_paa_vandvaerk'];?></b></p>
<?php 
if (sizeof($rowOverordnet)>1){
foreach($rowOverordnet as $value) {
	if($value["id"]!=$row["id"]){
		echo "<p><a href='{$value["html"]}'>{$value["navn_paa_vandvaerk"]}</a></p>";
	}
}
}
else {
	echo "<p>Ingen.</p>";
}
?>



