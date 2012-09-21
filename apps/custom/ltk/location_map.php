<?php
preg_match('/[0-9]+/',$_GET['plannr'],$matches);
$plannr = $matches[0];
?>
<img 
src="http://beta.mygeocloud.cowi.webhouse.dk/wms/ltk/lokalplaner/?LAYERS=public.ltk_grs_solid,lokalplaner.lpplandk2_point&TRANSPARENT=true&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&EXCEPTIONS=application%2Fvnd.ogc.se_inimage&FORMAT=image%2Fpng&SRS=EPSG%3A900913&BBOX=1381293.5378946,7509364.7499601,1402848.7798672,7521671.1115119&WIDTH=209&HEIGHT=119&SLD_BODY=%3CStyledLayerDescriptor%20version%3D'1.1.0'%3E%3CNamedLayer%3E%3CName%3Elokalplaner.lpplandk2_point%3C%2FName%3E%3CUserStyle%3E%3CTitle%3Exxx%3C%2FTitle%3E%3CFeatureTypeStyle%3E%3CRule%3E%20%3CFilter%3E%3CPropertyIsEqualTo%3E%3CPropertyName%3Eplannr%3C%2FPropertyName%3E%3CLiteral%3E<?php echo $plannr ?>%3C%2FLiteral%3E%3C%2FPropertyIsEqualTo%3E%3C%2FFilter%3E%3CPointSymbolizer%3E%3CGraphic%3E%3CMark%3E%3CWellKnownName%3Esquare%3C%2FWellKnownName%3E%3CFill%3E%3CCssParameter%20name%3D%22fill%22%3E%23000000%3C%2FCssParameter%3E%3C%2FFill%3E%3C%2FMark%3E%3CSize%3E12%3C%2FSize%3E%3C%2FGraphic%3E%3C%2FPointSymbolizer%3E%3C%2FRule%3E%3C%2FFeatureTypeStyle%3E%3C%2FUserStyle%3E%3C%2FNamedLayer%3E%3C%2FStyledLayerDescriptor%3E"
/>