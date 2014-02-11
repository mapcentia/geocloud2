var MapCentia;
MapCentia = (function () {
    "use strict";
    var switchLayer, arrMenu, setBaseLayer, addLegend, autocomplete, hostname, cloud, db, schema, uri, hash, osm, mapQuestOSM, mapQuestAerial, stamenToner, GNORMAL, GHYBRID, GSATELLITE, GTERRAIN, showInfoModal, qstore = [];
    hostname = geocloud_host;
    uri = geocloud.pathName;
    hash = decodeURIComponent(geocloud.urlHash);
    db = uri[3];
    schema = uri[4];
    arrMenu = [
        {
            title: 'Layers',
            id: 'menuID',
            icon: 'fa fa-reorder',
            items: []
        }
    ];
    switchLayer = function (name, visible) {
        if (visible) {
            cloud.showLayer(name);
        } else {
            cloud.hideLayer(name);
        }
        addLegend();
    };
    setBaseLayer = function (str) {
        cloud.setBaseLayer(str);
    };
    addLegend = function () {
        var param = 'l=' + cloud.getVisibleLayers();
        $.ajax({
            url: hostname + '/api/v1/legend/html/' + db + '/' + schema + '?' + param,
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                $('#legend').html(response.html);
            }
        });
    };
    autocomplete = new google.maps.places.Autocomplete(document.getElementById('search-input'));
    google.maps.event.addListener(autocomplete, 'place_changed', function () {
        var place = autocomplete.getPlace(),
            center = new geocloud.transformPoint(place.geometry.location.lng(), place.geometry.location.lat(), "EPSG:4326", "EPSG:900913");
        cloud.zoomToPoint(center.x, center.y, 10);
    });
    $(document).ready(function () {
        var metaData, metaDataKeys, metaDataKeysTitle, layers, jRes, node, modalFlag;
        metaDataKeys = [];
        metaDataKeysTitle = [];
        layers = {};
        cloud = new geocloud.map({
            el: "map"
        });
        osm = cloud.addOSM();
        mapQuestOSM = cloud.addMapQuestOSM();
        mapQuestAerial = cloud.addMapQuestAerial();
        stamenToner = cloud.addStamenToner();
        /*GNORMAL = cloud.addGoogleStreets();
         GHYBRID = cloud.addGoogleHybrid();
         GSATELLITE = cloud.addGoogleSatellite();
         GTERRAIN = cloud.addGoogleTerrain();*/
        setBaseLayer("osm");

        /*cloud.addBaseLayer(geocloud.OSM);
         cloud.setBaseLayer(geocloud.OSM);*/

        // we add two click controllers for desktop and handheld
        //cloud.map.addControl(clickPopUp = new popUpClickController);
        //cloud.map.addControl(clickModal = new modalClickController);

        $("#locate-btn").on("click", function () {
            cloud.locate();
        });
        showInfoModal = function () {
            modalFlag = true;
            $('#modal-info').modal({"backdrop": false});
        }
        $.ajax({
            url: '/api/v1/meta/' + db + '/' + schema,
            async: false,
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                var base64name, authIcon, isBaseLayer, arr, groups, metaUrl = "";
                groups = [];
                metaData = response;
                for (var i = 0; i < metaData.data.length; i++) {
                    metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                    if (!metaData.data[i].f_table_title) {
                        metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                    }
                    metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i];
                }
                for (i = 0; i < response.data.length; ++i) {
                    groups[i] = response.data[i].layergroup;
                }
                arr = array_unique(groups);
                for (var u = 0; u < response.data.length; ++u) {
                    isBaseLayer = (response.data[u].baselayer) ? true : false;
                    layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers({
                        layers: [response.data[u].f_table_schema + "." + response.data[u].f_table_name],
                        db: db,
                        isBaseLayer: isBaseLayer,
                        tileCached: true,
                        visibility: false,
                        wrapDateLine: false,
                        displayInLayerSwitcher: true,
                        name: response.data[u].f_table_schema + "." + response.data[u].f_table_name
                    });
                }
                for (i = 0; i < arr.length; ++i) {
                    if (arr[i]) {
                        var l = [];
                        base64name = Base64.encode(arr[i]).replace(/=/g, "");
                        node = {
                            name: arr[i],
                            id: 'itemID' + base64name,
                            icon: 'fa fa-folder',
                            link: '#',
                            items: [
                                {
                                    title: arr[i],
                                    icon: 'fa fa-folder',
                                    items: []
                                }
                            ]
                        };
                        for (u = 0; u < response.data.length; ++u) {
                            if (response.data[u].layergroup === arr[i]) {
                                authIcon = (response.data[u].authentication === "Read/write") ? " <i data-toggle='tooltip' title='first tooltip' class='fa fa-lock'></i>" : "";
                                var text = (response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title;
                                var cat = '<div class="checkbox"><label><input type="checkbox" id="' + response.data[u].f_table_name + '" onchange="MapCentia.switchLayer(MapCentia.schema+\'.\'+this.id,this.checked)" value="">' + text + authIcon + metaUrl + '</label></div>';
                                l.push({
                                    text: text,
                                    id: response.data[u].f_table_schema + "." + response.data[u].f_table_name,
                                    leaf: true,
                                    checked: false
                                });
                                node.items[0].items.push({
                                    name: cat,
                                    metaIcon: 'fa fa-info-circle',
                                    link: '#',
                                    metaUrl: response.data[u].meta_url
                                });
                            }
                        }
                        arrMenu[0].items.push(node);
                    }
                }
                $('#menu').multilevelpushmenu({
                    menu: arrMenu
                });
            }
        }); // Ajax call end
        jRes = jRespond([
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
                $("#modal-legend .modal-body").append($("#legend"));
            },
            exit: function () {
                $('#modal-legend').modal('hide');
            }
        });
        jRes.addFunc({
            breakpoint: ['desktop'],
            enter: function () {
                $("#legend-popover").popover({
                    offset: 10,
                    html: true,
                    content: $("#legend")
                }).popover('show').popover('hide');
                $('#legend-popover').on('click', function (e) {
                    addLegend();
                });
            },
            exit: function () {
                // We activate the popovers, so the divs becomes visible once before screen resize.
                $("#legend-popover").popover('show');
                addLegend();
            }
        });
        //Set up the state from the URI
        (function () {
            var p, arr, i, hashArr;
            hashArr = hash.replace("#", "").split("/");
            if (hashArr[0]) {
                $(".base-map-button").removeClass("active");
                $("#" + hashArr[0]).addClass("active");
                if (hashArr[1] && hashArr[2] && hashArr[3]) {
                    p = geocloud.transformPoint(hashArr[2], hashArr[3], "EPSG:4326", "EPSG:900913");
                    cloud.zoomToPoint(p.x, p.y, hashArr[1]);
                    setBaseLayer(hashArr[0]);
                    if (hashArr[4]) {
                        arr = hashArr[4].split(",");
                        for (i = 0; i < arr.length; i++) {
                            switchLayer(arr[i], true);
                            $("#" + arr[i].replace(schema + ".", "")).attr('checked', true);
                        }
                    }
                }
            }
            else {
                cloud.zoomToExtent();
            }
        })();
        var moveEndCallBack = function () {
            var p;
            p = geocloud.transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
            try {
                history.pushState(null, null, "/apps/viewer/" + db + "/" + schema + "/?fw=" + geocloud.MAPLIB + "#" + cloud.getBaseLayerName() + "/" + Math.round(cloud.getZoom()).toString() + "/" + (Math.round(p.x * 10000) / 10000).toString() + "/" + (Math.round(p.y * 10000) / 10000).toString() + "/" + cloud.getNamesOfVisibleLayers());
            }
            catch (e) {
            }
        };
        cloud.on("dragend", moveEndCallBack);
        cloud.on("moveend", moveEndCallBack);
        var clicktimer;
        cloud.on("dblclick", function (e) {
            clicktimer = undefined;
        });
        cloud.on("click", function (e) {
            var layers, count = 0, hit = false, event = new geocloud.clickEvent(e, cloud), distance;
            if (clicktimer) {
                clearTimeout(clicktimer);
            }
            else {
                clicktimer = setTimeout(function (e) {
                    clicktimer = undefined;
                    var coords = event.getCoordinate();
                    $.each(qstore, function (index, store) {
                        store.reset();
                        cloud.removeGeoJsonStore(store);
                    });
                    layers = cloud.getVisibleLayers().split(";");
                    $("#info-tab").empty();
                    $("#info-pane").empty();
                    $.each(layers, function (index, value) {
                        var isEmpty = true;
                        var srid = metaDataKeys[value.split(".")[1]].srid;
                        var geoType = metaDataKeys[value.split(".")[1]].type;
                        var layerTitel = (metaDataKeys[value.split(".")[1]].f_table_title !== null && metaDataKeys[value.split(".")[1]].f_table_title !== "") ? metaDataKeys[value.split(".")[1]].f_table_title : metaDataKeys[value.split(".")[1]].f_table_name;
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            var res = [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
                                4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
                                76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
                                1.19432856696, 0.597164283478, 0.298582141739];
                            distance = 5 * res[cloud.getZoom()];
                        }
                        qstore[index] = new geocloud.sqlStore({
                            db: db,
                            id: index,
                            onLoad: function () {
                                var layerObj = qstore[this.id], out = [], fieldLabel;
                                isEmpty = layerObj.isEmpty();
                                if ((!isEmpty)) {
                                    showInfoModal();
                                    var fieldConf = $.parseJSON(metaDataKeys[value.split(".")[1]].fieldconf);

                                    $("#info-tab").append('<li><a data-toggle="tab" href="#_' + index + '">' + layerTitel + '</a></li>');
                                    $("#info-pane").append('<div class="tab-pane" id="_' + index + '"><table class="table table-condensed"><thead><tr><th>Property</th><th>Value</th></tr></thead></table></div>');

                                    $.each(layerObj.geoJSON.features, function (i, feature) {
                                        if (fieldConf === null) {
                                            $.each(feature.properties, function (name, property) {
                                                out.push([name, 0, name, property]);
                                            });
                                        }
                                        else {
                                            $.each(fieldConf, function (name, property) {
                                                if (property.querable) {
                                                    fieldLabel = (property.alias !== null && property.alias !== "") ? property.alias : name;
                                                    out.push([name, property.sort_id, fieldLabel, feature.properties[name]]);
                                                }
                                            });
                                        }
                                        out.sort(function (a, b) {
                                            return a[1] - b[1];
                                        });

                                        $.each(out, function (name, property) {
                                            $("#_" + index + " table").append('<tr><td>' + property[2] + '</td><td>' + property[3] + '</td></tr>');
                                        });
                                        //$("#_" + index + " table").append('<tr><td>&nbsp;</td><td>&nbsp;</td></tr>');
                                        out = [];
                                        $('#info-tab a:first').tab('show');

                                    });
                                    hit = true;
                                }
                                count++;
                                if (count === layers.length) {
                                    if (!hit) {
                                        // Do not try to hide a not initiated modal
                                        if (modalFlag) {
                                            $('#modal-info').modal('hide');
                                        }
                                    }
                                }
                            }

                        });
                        cloud.addGeoJsonStore(qstore[index]);
                        var sql;
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_buffer(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913), " + distance + " )," + srid + "),the_geom)";
                        }
                        else {
                            sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913)," + srid + "),the_geom)";
                        }
                        qstore[index].sql = sql;
                        qstore[index].load();
                    });
                }, 250);
            }
        });
    });
    return{
        cloud: cloud,
        switchLayer: switchLayer,
        setBaseLayer: setBaseLayer,
        schema: schema
    }
})();
