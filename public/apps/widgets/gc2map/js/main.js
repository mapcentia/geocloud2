/*global geocloud:false */
/*global geocloud_host:false */
/*global $:false */
/*global jQuery:false */
/*global OpenLayers:false */
/*global ol:false */
/*global L:false */
/*global jRespond:false */
/*global Base64:false */
/*global array_unique:false */
/*global google:false */
/*global GeoExt:false */
/*global mygeocloud_ol:false */
/*global schema:false */
/*global document:false */
/*global window:false */
/*global screen:false */
var MapCentia;
MapCentia = function (globalId) {
    "use strict";
    var id = globalId, init, switchLayer, setBaseLayer, addLegend, autocomplete, hostname, cloud, db, osm, showInfoModal, qstore = [], share, permaLink, shareTwitter, shareFacebook, shareLinkedIn, shareGooglePlus, shareTumblr, shareStumbleupon, openMapWin,
        eWidth = $("#" + id).width(),
        eHeight = $("#" + id).height();
    hostname = geocloud_host;
    db = "mydb";
    switchLayer = function (name, visible) {
        if (visible) {
            cloud.showLayer(name);
        } else {
            cloud.hideLayer(name);
        }
        //addLegend();
    };
    setBaseLayer = function (str) {
        cloud.setBaseLayer(str);
    };
    addLegend = function () {
        var param = 'l=' + cloud.getVisibleLayers();
        $.ajax({
            url: hostname + '/api/v1/legend/json/' + db + '/?' + param,
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                var table = $("<table/>", {border: '0'}), i, tr, td;
                $.each(response, function (i, v) {
                    if (typeof(v) === "object" && v.id !== 'public.komgr') {
                        i = v.id;
                        tr = $("<tr/>");
                        tr.append("<td><div class='layer-title'><span><input onchange=\"gc2Widget.maps['" + id + "'].switchLayer(this.id, this.checked)\" id='" + i + "' type='checkbox' checked></span></div></td>");
                        td = $("<td/>");
                        for (var u = 0; u < v.classes.length; u++) {
                            td.append("<div class='class-title'><span><img class='legend-img' src='data:image/png;base64, " + v.classes[u].img + "'></span><span class='legend-text'>" + v.classes[u].name + "</span></div>");
                        }
                        tr.append(td);
                    }
                    table.append(tr);
                });
                $('#legend-' + id).html(table);
            }
        });
    };
    share = function () {
        var url = permaLink(), layers, arr = [], layersStr = "", i, p, javascript;
        $("#modal-share-" + id).modal();
        $("#share-url-" + id).val(url);
        $("#share-iframe-" + id).val("<iframe width='100%' height='500px' frameBorder='0' src='" + url + "'></iframe>");
        //var bbox = cloud.getExtent();
        p = geocloud.transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
        $("#share-static-" + id).val(hostname + "/api/v1/staticmap/png/" + db + "?baselayer=" + cloud.getBaseLayerName().toUpperCase() + "&layers=" + cloud.getNamesOfVisibleLayers() + "&size=" + cloud.map.getSize().x + "x" + cloud.map.getSize().y + "&zoom=" + Math.round(cloud.getZoom()).toString() + "&center=" + (Math.round(p.y * 10000) / 10000).toString() + "," + (Math.round(p.x * 10000) / 10000).toString() + "&lifetime=3600");

        layers = cloud.getNamesOfVisibleLayers();
        if (layers.length > 0) {
            for (i = 0; i < layers.split(",").length; i = i + 1) {
                arr.push("'" + layers.split(",")[i] + "'");
            }
            layersStr = arr.join(",");
        }
        javascript = "<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js'></script>\n" +
            "<script src='" + hostname + "/js/leaflet/leaflet.js'></script>\n" +
            "<script src='" + hostname + "/api/v3/js/geocloud.js'></script>\n" +
            "<div id='map' style='width: 100%; height: 500px'></div>\n" +
            "<script>\n" +
            "(function () {\n" +
            "      var map = new geocloud.map({\n" +
            "      el: 'map'\n" +
            "   });\n" +
            "   map.addBaseLayer(geocloud." + cloud.getBaseLayerName().toUpperCase() + ");\n" +
            "   map.setBaseLayer(geocloud." + cloud.getBaseLayerName().toUpperCase() + ");\n" +
            "   map.setView([" + cloud.getCenter().lat.toString() + "," + cloud.getCenter().lon.toString() + "]," + Math.round(cloud.getZoom()).toString() + ");\n" +
            "   map.addTileLayers({\n" +
            "      db: '" + db + "',\n" +
            "      layers: [" + layersStr + "],\n" +
            "   });\n" +
            "}())\n" +
            "</script>";
        $("#share-javascript-" + id).val(javascript);
    };
    shareTwitter = function () {
        var url = permaLink();
        window.open("https://twitter.com/share?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareLinkedIn = function () {
        var url = permaLink();
        window.open("https://www.linkedin.com/cws/share?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareGooglePlus = function () {
        var url = permaLink();
        window.open("https://plus.google.com/share?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareFacebook = function () {
        var url = permaLink();
        window.open("https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareTumblr = function () {
        var url = permaLink();
        window.open("http://www.tumblr.com/share?v=3&t=My%20map&u=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareStumbleupon = function () {
        var url = permaLink();
        window.open("http://www.stumbleupon.com/submit?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    permaLink = function () {
        var p = geocloud.transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
        return "http://mapcentia.github.io/dragoer/simpleclient/index.html#" + cloud.getBaseLayerName() + "/" + Math.round(cloud.getZoom()).toString() + "/" + (Math.round(p.x * 10000) / 10000).toString() + "/" + (Math.round(p.y * 10000) / 10000).toString() + "/" + cloud.getNamesOfVisibleLayers();
    };
    /*autocomplete = new google.maps.places.Autocomplete(document.getElementById('search-input'));
     google.maps.event.addListener(autocomplete, 'place_changed', function () {
     var place = autocomplete.getPlace(),
     center = new geocloud.transformPoint(place.geometry.location.lng(), place.geometry.location.lat(), "EPSG:4326", "EPSG:900913");
     cloud.zoomToPoint(center.x, center.y, 10);
     });*/

    openMapWin = function (page, width, height) {
        var strWinName = "Map",
            popleft = (screen.width - width) / 2,
            poptop = (screen.height - height) / 2,
            openWin = false,
            MapappWin = null,
            strParameters = "width=" + width + ",height=" + height +
                ",resizable=1,scrollbars=0,status=1,left=" +
                popleft + ",top=" + poptop + ",screenX=" + popleft +
                ",screenY=" + poptop + ",toolbar=0";

        if (MapappWin === null) {
            openWin = true;
        } else if (MapappWin.closed) {
            openWin = true;
        } else {
            openWin = false;
        }

        if (openWin) {
            MapappWin = window.open(page, strWinName, strParameters);
            MapappWin.focus();
        } else {
            if (!MapappWin.closed) {
                MapappWin.focus();
            }
        }
    };
    cloud = new geocloud.map({
        el: "map-" + id
    });
    init = function (conf) {
        var metaData, metaDataKeys = [], metaDataKeysTitle = [], layers = {}, modalFlag, extent = null, p, arr, prop,
        defaults = {
            baseLayers: null
        };
        if (conf) {
            for (prop in conf) {
                defaults[prop] = conf[prop];
            }
        }
        $("[data-toggle=tooltip]").tooltip();
        $('.share-text').mouseup(function () {
            return false;
        });
        $(".share-text").focus(function () {
            $(this).select();
        });

        $("#locate-btn-" + id).on("click", function () {
            cloud.locate();
        });
        showInfoModal = function () {
            modalFlag = true;
            $('#modal-info-' + id).modal({"backdrop": false});
        };
        // Media queries
        $("#modal-info-body-" + id).css({"height": (eHeight - 100) + "px"});
        $("#legend-popover-li-" + id).show();
        $("#legend-popover-" + id).popover({offset: 10, html: true, content: $("#legend-" + id)}).popover('show');
        $("#legend-popover-" + id).on('click', function () {
            addLegend();
        });
        $("#legend-popover-" + id).popover('hide');
        $("#locate-btn-" + id).css({"margin-left": "10px"});
        $("#legend-" + id).css({"max-height": (eHeight - 65) + "px"});

        if (eWidth < 400) {
            $("#group-javascript-" + id).hide();
            $("#modal-info-" + id + " .modal-dialog").css({"width": "auto", "margin": "10px"});
            $("#modal-share-" + id + " .modal-dialog").css({"width": "auto", "margin": "10px"});
            $("#modal-share-body-" + id).css({"height": (eHeight - 100) + "px"});
        } else {
            $("#modal-info-body-" + id).css({"height": (eHeight < 350) ? (eHeight - 130) : (220) + "px"});
            $("#modal-info-" + id).css({"width": "280px", "right": "10px", "left": "auto"});
            $("#modal-info-" + id + " .modal-dialog").css({"margin": "35px 30px 0 0", "width": "280px"});
            $("#modal-share-body-" + id).css({"max-height": (eHeight - 130) + "px"});
        }
        if (eWidth < 768 && eWidth >= 400) {
            $("#modal-share-" + id + " .modal-dialog").css({"width": "auto", "margin": "35px 10px"});
        }
        p = geocloud.transformPoint(defaults.zoom.split(",")[0], defaults.zoom.split(",")[1], "EPSG:4326", "EPSG:900913");
        cloud.zoomToPoint(p.x, p.y, defaults.zoom.split(",")[2]);

        // If no base layers defaults at all
        if (typeof window.setBaseLayers !== 'object' || defaults.baseLayers === null) {
            defaults.baseLayers = [
                {id: geocloud.MAPQUESTOSM, name: "MapQuset OSM"},
                {id: geocloud.OSM, name: "OSM"}
            ];
        }
        if (defaults.baseLayers === null && typeof window.setBaseLayers === 'object'){
            defaults.baseLayers = window.setBaseLayers;
        }

        cloud.bingApiKey = window.bingApiKey;
        for (var i = 0; i < defaults.baseLayers.length; i++) {
            cloud.addBaseLayer(defaults.baseLayers[i].id);
            $("#base-layer-list-" + id).append(
                "<li><a href=\"#\" onclick=\"gc2Widget.maps['" + id + "'].setBaseLayer('" +defaults.baseLayers[i].id + "')\"><!--<img class=\"img-rounded images-base-map\" src=\"http://apps/viewer/img/mqosm.png\">-->" + defaults.baseLayers[i].name + "</a></li>"
            );
        }
        setBaseLayer(defaults.baseLayers[0].id);
        arr = defaults.layers.split(",");
        for (var i = 0; i < arr.length; i++) {
            layers[arr[i]] = cloud.addTileLayers({
                layers: [arr[i]],
                db: db,
                tileCached: true,
                visibility: false,
                wrapDateLine: false,
                displayInLayerSwitcher: true,
                name: arr[i]
            });
            switchLayer(arr[i], true);
            $.ajax({
                    url: geocloud_host.replace("cdn.", "") + '/api/v1/meta/' + db + '/' + arr[i],
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

                    }
                }
            );
        }
        addLegend();
        var clicktimer;
        cloud.on("dblclick", function () {
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
                    $("#info-tab-" + id).empty();
                    $("#info-pane-" + id).empty();
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
                                    $("#info-tab-" + id).append('<li><a data-toggle="tab" href="#_' + index + '-' + id + '">' + layerTitel + '</a></li>');
                                    $("#info-pane-" + id).append('<div class="tab-pane" id="_' + index + '-' + id + '"><table class="table table-condensed"><thead><tr><th>Egenskab</th><th>V&aelig;rdi</th></tr></thead></table></div>');

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
                                        //var test = [out[0]]
                                        $.each(out, function (name, property) {
                                            $("#_" + index + "-" + id + " table").append('<tr><td>' + property[2] + '</td><td>' + property[3] + '</td></tr>');
                                        });
                                        out = [];
                                        $('#info-tab-' + id + ' a:first').tab('show');
                                    });
                                    hit = true;
                                }
                                count++;
                                if (count === layers.length) {
                                    if (!hit) {
                                        // Do not try to hide a not initiated modal
                                        if (modalFlag) {
                                            $("#modal-info-" + id).modal('hide');
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
    };
    return{
        init: init,
        cloud: cloud,
        switchLayer: switchLayer,
        setBaseLayer: setBaseLayer,
        openMapWin: openMapWin,
        share: share,
        shareTwitter: shareTwitter,
        shareFacebook: shareFacebook,
        shareLinkedIn: shareLinkedIn,
        shareGooglePlus: shareGooglePlus,
        shareTumblr: shareTumblr,
        shareStumbleupon: shareStumbleupon
    };
};
