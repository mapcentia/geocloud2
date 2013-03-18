<!DOCTYPE html>
<html lang="en">
	<head>
		<title>MyGeoCloud - Analyze and map your data</title>
		<meta charset="UTF-8" />
		<script type="text/javascript" src="/api/v1/js/api.js"></script>
		<script type="text/javascript" src="/js/common.js"></script>
		<link href="/js/bootstrap/css/bootstrap.css" rel="stylesheet">
		<link href="/js/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
		<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyA-DSPlhVi52zBadpyTRa4cOtSr6WKDOgA&amp;sensor=false"></script>
		<style>
			.popover {
				width: 400px;
			}
			.popover-inner{
				overflow: auto;
				max-height: 400px;
			}
		</style>
		<script>
            var cloud;
            var startExt = [982328.16354289, 7693441.9121169, 1110742.3710441, 7757266.8307261];
            var switchLayer = function(id, visible) {
                (visible) ? cloud.showLayer(id) : cloud.hideLayer(id);
            }
            $(window).load(function() {
            	var db = mygeocloud_ol.pathName[2];
                cloud = new mygeocloud_ol.map("map", db);
                //cloud.zoomToExtent(startExt);
                
                var schema = mygeocloud_ol.pathName[3];
                var layers = {};
                $.ajax({
                    url : '/controller/tables/' + db + '/getrecords/settings.geometry_columns_view',
                    async : false,
                    dataType : 'json',
                    type : 'GET',
                    success : function(data, textStatus, http) {
                        var groups = [];
                        if (http.readyState == 4) {
                            if (http.status == 200) {
                                var response = eval('(' + http.responseText + ')');
                                for (var i = 0; i < response.data.length; ++i) {
                                    groups[i] = response.data[i].layergroup;
                                }
                                var arr = array_unique(groups);
                                for (var u = 0; u < response.data.length; ++u) {
                                    if (response.data[u].baselayer) {
                                        var isBaseLayer = true;
                                    } else {
                                        var isBaseLayer = false;
                                    }
                                    layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers([response.data[u].f_table_schema + "." + response.data[u].f_table_name], {
                                        singleTile : false,
                                        isBaseLayer : isBaseLayer,
                                        visibility : false,
                                        wrapDateLine : false,
                                        tileCached : false,
                                        displayInLayerSwitcher : true,
                                        name : response.data[u].f_table_name
                                    });
                                }
                                for (var i = 0; i < arr.length; ++i) {
                                    var l = [];
                                    $("#layers").append('<div id="group-' + arr[i] + '" class="accordion-group"><div class="accordion-heading"><a class="accordion-toggle" data-toggle="collapse" data-parent="#accordion3" href="#collapse' + arr[i] + '"> ' + arr[i] + ' </a></div></div>');
                                    $("#group-" + arr[i]).append('<div id="collapse' + arr[i] + '" class="accordion-body collapse"></div>');
                                    for (var u = 0; u < response.data.length; ++u) {
                                        if (response.data[u].layergroup == arr[i]) {
                                            var text = (response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title;
                                            $("#collapse" + arr[i]).append('<div class="accordion-inner"><label class="checkbox">' + text + '<input type="checkbox" id="' + response.data[u].f_table_name + '" onchange="switchLayer(this.id,this.checked)"></label></div>');
                                            l.push({
                                                text : (response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title,
                                                id : response.data[u].f_table_schema + "." + response.data[u].f_table_name,
                                                leaf : true,
                                                checked : false
                                            });
                                        }
                                    }
                                }
                                $(function() {
                                    $("#blob").popover({
                                        offset : 10,
                                        html : true,
                                        content : $("#layers")
                                    })
                                    $("#blob").popover('show')
                                })
                            }
                        }
                    }
                });
            });
		</script>
	</head>
	<body>
		<div class="navbar navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container">
					<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse"> <span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span> </a>
					<a class="brand" href="/">MyGeoCloud</a>
					<div class="nav-collapse">
						<ul class="nav">
							<li>
								<a href="#" id="blob" rel="popover" data-placement="bottom" title="Popover on bottom"> Popover on bottom </a>
							</li>
							<li>
								<form class="navbar-search" action="">
									<input type="text" class="search-query" placeholder="Search">
								</form>
							</li>

						</ul>
					</div><!--/.nav-collapse -->
				</div>
			</div>
		</div>
		<div id="map" style="width: 100%;height: 100%;position: absolute">
			<div id="layers"></div>

		</div>
	</body>
	<script src="http://twitter.github.com/bootstrap/assets/js/jquery.js"></script>
	<script src="http://twitter.github.com/bootstrap/assets/js/bootstrap-collapse.js"></script>
	<script src="http://twitter.github.com/bootstrap/assets/js/bootstrap-alert.js"></script>
	<script src="http://twitter.github.com/bootstrap/assets/js/bootstrap-tooltip.js"></script>
	<script src="http://twitter.github.com/bootstrap/assets/js/bootstrap-popover.js"></script>
	<script src="/js/bootstrap/js/bootstrap.js"></script>
</html>