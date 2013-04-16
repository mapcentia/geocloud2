var osm, mapQuestOSM, mapQuestAerial, GNORMAL, GHYBRID, GSATELLITE, GTERRAIN, stamenToner;
var cloud, db, clickPopUp, clickModal;
var hostname = mygeocloud_host;
var metaData = null;
var metaDataKeys = [];
var metaDataKeysTitle = [];
//var startExt = [982328.16354289, 7693441.9121169, 1110742.3710441, 7757266.8307261];
var switchLayer = function (id, visible) {
    (visible) ? cloud.showLayer(id) : cloud.hideLayer(id);
    try {
        popup.destroy();
    } catch (e) {
    }
    addLegend();
};
var addLegend = function () {
    var layers = cloud.getVisibleLayers();
    var param = 'layers=' + layers + '&amp;type=text&amp;lan=';
    $.ajax({
        url: hostname + '/apps/viewer/servers/legend/' + db + '?' + param,
        dataType: 'jsonp',
        jsonp: 'jsonp_callback',
        success: function (response) {
            $('#legend').html(response.html);
        }
    });
};
$(window).load(function () {
    db = mygeocloud_ol.pathName[3];
    cloud = new mygeocloud_ol.map({
        el: "map",
        db: db,
        projection: "EPSG:3857",
        eventListeners: {
            //"changelayer" : addLegend
        }
    });
    osm = cloud.addOSM();
    mapQuestOSM = cloud.addMapQuestOSM();
    mapQuestAerial = cloud.addMapQuestAerial();
    GNORMAL = cloud.addGoogleStreets();
    GHYBRID = cloud.addGoogleHybrid();
    GSATELLITE = cloud.addGoogleSatellite();
    GTERRAIN = cloud.addGoogleTerrain();
    cloud.map.addLayer(stamenToner = new OpenLayers.Layer.Stamen("toner"));
    cloud.setBaseLayer(osm);
    cloud.zoomToExtent();
    // we add two click controllers for desktop and handheld
    cloud.map.addControl(clickPopUp = new popUpClickController);
    cloud.map.addControl(clickModal = new modalClickController);

    var schema = mygeocloud_ol.pathName[4];
    var layers = {};
    $.ajax({
        url: hostname + '/controller/geometry_columns/' + db + '/getall/' + schema,
        async: false,
        dataType: 'jsonp',
        jsonp: 'jsonp_callback',
        success: function (response) {
            metaData = response;
            for (var i = 0; i < metaData.data.length; i++) {
                metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                if (!metaData.data[i].f_table_title) {
                    metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                }
                metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i];
            }
            var groups = [];
            for (var i = 0; i < response.data.length; ++i) {
                groups[i] = response.data[i].layergroup;
            }
            var arr = array_unique(groups);
            var isBaseLayer;
            for (var u = 0; u < response.data.length; ++u) {
                if (response.data[u].baselayer) {
                    isBaseLayer = true;
                } else {
                    isBaseLayer = false;
                }
                layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers({
                    layers: [response.data[u].f_table_schema + "." + response.data[u].f_table_name],
                    db: db,
                    isBaseLayer: isBaseLayer,
                    tileCached: false,
                    visibility: false,
                    wrapDateLine: false,
                    displayInLayerSwitcher: true,
                    name: response.data[u].f_table_name
                });
            }
            var base64name;
            var authIcon;
            for (var i = 0; i < arr.length; ++i) {
                if (arr[i]) {
                    var l = [];
                    base64name = Base64.encode(arr[i]).replace(/=/g, "");
                    $("#layers").append('<div id="group-' + base64name + '" class="accordion-group"><div class="accordion-heading"><a class="accordion-toggle" data-toggle="collapse" data-parent="#layers" href="#collapse' + base64name + '"> ' + arr[i] + ' </a></div></div>');
                    $("#group-" + base64name).append('<div id="collapse' + base64name + '" class="accordion-body collapse"></div>');
                    for (var u = 0; u < response.data.length; ++u) {
                        if (response.data[u].layergroup == arr[i]) {
                            authIcon = (response.data[u].authentication === "Read/write") ? " <a href='#' data-toggle='tooltip' title='first tooltip'><i class='icon-lock'></i></a>" : "";
                            var text = (response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title;
                            $("#collapse" + base64name).append('<div class="accordion-inner"><label class="checkbox">' + text + authIcon + '<input type="checkbox" id="' + response.data[u].f_table_name + '" onchange="switchLayer(this.id,this.checked)"></label></div>');
                            l.push({
                                text: text,
                                id: response.data[u].f_table_schema + "." + response.data[u].f_table_name,
                                leaf: true,
                                checked: false
                            });
                        }
                    }
                }
            }
            $(function () {
                var jRes = jRespond([
                    {
                        label: 'handheld',
                        enter: 0,
                        exit: 979
                    },
                    {
                        label: 'tablet',
                        enter: 768,
                        exit: 979
                    },
                    {
                        label: 'desktop',
                        enter: 980,
                        exit: 10000
                    }
                ]);
                jRes.addFunc({
                    breakpoint: ['handheld', 'tablet'],
                    enter: function () {
                        // We activate the modals
                        $("#modal-layers .modal-body").append($('#layers'));
                        $("#modal-base-layers .modal-body").append($("#base-layers"));
                        $("#modal-legend .modal-body").append($("#legend"));
                        clickModal.activate();
                    },
                    exit: function () {
                        $('#modal-layers').modal('hide');
                        $('#modal-base-layers').modal('hide');
                        $('#modal-legend').modal('hide');
                        $('#modal-info').modal('hide');
                        clickModal.deactivate();
                        modalVectors.removeAllFeatures();
                    }
                });
                jRes.addFunc({
                    breakpoint: ['desktop'],
                    enter: function () {
                        $("#layers-popover").popover({
                            offset: 10,
                            html: true,
                            content: $("#layers")
                        }).popover('show');
                        $("#base-layers-popover").popover({
                            offset: 10,
                            html: true,
                            content: $("#base-layers")
                        }).popover('show').popover('hide');
                        $("#legend-popover").popover({
                            offset: 10,
                            html: true,
                            content: $("#legend")
                        }).popover('show').popover('hide');
                        $('#legend-popover').on('click', function (e) {
                            addLegend();
                        });
                        clickPopUp.activate();
                    },
                    exit: function () {
                        // We activate the popovers, so the divs becomes visible once before screen resize.
                        $("#layers-popover").popover('show');
                        $("#base-layers-popover").popover('show');
                        $("#legend-popover").popover('show');
                        addLegend();
                        clickPopUp.deactivate();
                        try {
                            popup.destroy();
                        } catch (e) {
                        }
                        popUpVectors.removeAllFeatures();
                    }
                });
            });
        }
    });
});