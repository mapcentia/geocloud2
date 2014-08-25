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
/*global __:false */
var MapCentia;
MapCentia = function (globalId) {
    "use strict";
    var id = globalId, defaults, db, schema, init, switchLayer, setBaseLayer, addLegend, autocomplete, hostname, cloud, qstore = [], share, permaLink, shareTwitter, shareFacebook, shareLinkedIn, shareGooglePlus, shareTumblr, shareStumbleupon, openMapWin,
        eWidth = $("#" + id).width(),
        eHeight = $("#" + id).height();
    switchLayer = function (name, visible) {
        if (visible) {
            cloud.showLayer(name);
        } else {
            cloud.hideLayer(name);
        }
    };
    setBaseLayer = function (str) {
        cloud.setBaseLayer(str);
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
            MapappWin = window.open('', strWinName, strParameters);
            MapappWin.focus();
            $(MapappWin.document).ready(function () {
                MapappWin.document.write(
                    '<style>body{padding:0;margin:0}</style>' +
                        '<script>window.gc2host = "' + hostname + '"</script>' +
                        '<script src="' + hostname + '/apps/widgets/gc2map/js/gc2map.js"></script>' +
                        '<div style="width: 100%;height: 100%; position: absolute;"></div>'

                );
                // Must bee split in two parts. Yes, its f****** IE9
                defaults.width = "100%";
                defaults.height = "100%";
                MapappWin.document.write(
                    '<script>gc2map.init(' + JSON.stringify(defaults) + ')</script>'
                );
            });
        } else {
            if (!MapappWin.closed) {
                MapappWin.focus();
            }
        }
    };
    cloud = new geocloud.map({
        el: "map-" + id,
        zoomControl: false
    });
    cloud.map.addControl(L.control.zoom({
        position: 'bottomright'
    }));
    init = function (conf) {
        var metaData, metaDataKeys = [], metaDataKeysTitle = [], layers = {}, clicktimer, p, p1, p2, arr, prop, sub, legendDirty = false, i, text;
        defaults = {
            baseLayers: null,
            callBack: function () {
            }
        };
        if (conf) {
            for (prop in conf) {
                defaults[prop] = conf[prop];
            }
        }
        db = defaults.db;
        schema = defaults.schema;
        hostname = defaults.host;
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

        $("#modal-info-" + id).on('hidden.bs.modal', function (e) {
            $.each(qstore, function (i, v) {
                qstore[i].reset();
            });
        });

        // Media queries
        $("#legend-popover-li-" + id).show();
        $("#legend-popover-" + id).popover({offset: 10, html: true, content: $("#legend-" + id)}).popover('show');
        $("#legend-popover-" + id).on('click', function () {
            addLegend();
            legendDirty = true;
        });
        $("#legend-" + id).css({"max-height": (eHeight - 65) + "px"});
        $("#legend-popover-" + id).popover('hide');
        $("#locate-btn-" + id).css({"margin-left": "10px"});

        if (eWidth < 400) {
            sub = 115;
            $("#group-javascript-" + id).hide();
            $("#modal-info-" + id + " .modal-dialog").css({"width": "auto", "margin": "10px"});
            $("#modal-share-" + id + " .modal-dialog").css({"width": "auto", "margin": "10px"});
            $("#modal-share-body-" + id).css({"height": (eHeight - sub) + "px"});
            $("#modal-info-body-" + id).css({"height": (eHeight - sub) + "px"});

        } else {
            sub = 130;
            $("#modal-info-" + id).css({"width": "300px", "height": "370px", "left": "auto", "margin-right": "0px"});
            $("#modal-info-" + id + " .modal-dialog").css({"width": "280px", "margin-top": "30px"});
            //$(".modal-dialog").css({"margin": "30px 30px 0 0"});
            $("#modal-info-body-" + id).css({"height": (eHeight < 350) ? (eHeight - sub) : (220) + "px"});
            $("#modal-share-" + id + " .modal-dialog").css({"margin-top": "30px !important"});
            $("#modal-share-body-" + id).css({"max-height": (eHeight - sub) + "px"});
        }
        if (eWidth < 768 && eWidth >= 400) {
            $("#modal-share-" + id + " .modal-dialog").css({"width": "auto", "margin": "30px 10px"});
        }

        if (typeof defaults.extent !== "undefined") {
            p1 = geocloud.transformPoint(defaults.extent[0], defaults.extent[1], "EPSG:4326", "EPSG:900913");
            p2 = geocloud.transformPoint(defaults.extent[2], defaults.extent[3], "EPSG:4326", "EPSG:900913");
            cloud.zoomToExtent([p1.x, p1.y, p2.x, p2.y]);
        } else {
            p = geocloud.transformPoint(defaults.zoom[0], defaults.zoom[1], "EPSG:4326", "EPSG:900913");
            cloud.zoomToPoint(p.x, p.y, defaults.zoom[2]);
        }
        // If no base layers defaults at all
        if (typeof window.setBaseLayers !== 'object' && defaults.baseLayers === null) {
            defaults.baseLayers = [
                {id: geocloud.MAPQUESTOSM, name: "MapQuset OSM"},
                {id: geocloud.OSM, name: "OSM"}
            ];
        }
        // Base layers from server wide setting
        if (defaults.baseLayers === null && typeof window.setBaseLayers === 'object') {
            defaults.baseLayers = window.setBaseLayers;
        }
        cloud.bingApiKey = window.bingApiKey;
        cloud.digitalGlobeKey = window.digitalGlobeKey;
        for (i = 0; i < defaults.baseLayers.length; i = i + 1) {
            if (typeof defaults.baseLayers[i].restrictTo === "undefined" || defaults.baseLayers[i].restrictTo.indexOf(schema) > -1 || schema === undefined) {
                if (defaults.baseLayers[i].id.split(".").length > 1) {
                    cloud.addTileLayers({
                        host: defaults.host,
                        layers: [defaults.baseLayers[i].id],
                        db: db,
                        wrapDateLine: false,
                        isBaseLayer: true,
                        displayInLayerSwitcher: true,
                        name: defaults.baseLayers[i].name
                    });
                } else {
                    cloud.addBaseLayer(defaults.baseLayers[i].id, defaults.baseLayers[i].db);
                }
                $("#base-layer-list-" + id).append(
                    "<li><a href=\"javascript:void(0)\" onclick=\"gc2map.maps['" + id + "'].setBaseLayer('" + defaults.baseLayers[i].id + "')\"><!--<img class=\"img-rounded images-base-map\" src=\"http://apps/viewer/img/mqosm.png\">-->" + defaults.baseLayers[i].name + "</a></li>"
                );
            }
        }
        if (defaults.setBaseLayer) {
            setBaseLayer(defaults.setBaseLayer);
        } else {
            setBaseLayer(defaults.baseLayers[0].id);
        }

        arr = defaults.layers;
        for (i = 0; i < arr.length; i = i + 1) {
            // If layer is schema, set as base layer
            if (arr[i].split(".").length < 2) {
                layers[arr[i]] = cloud.addTileLayers({
                    host: defaults.host,
                    layers: [arr[i]],
                    isBaseLayer: true,
                    visibility: false,
                    db: db,
                    wrapDateLine: false,
                    name: [arr[i]]
                });
                $("#base-layer-list-" + id).append(
                    "<li><a href=\"javascript:void(0)\" onclick=\"gc2map.maps['" + id + "'].setBaseLayer('" + arr[i] + "')\">" + arr[i] + "</a></li>"
                );
            } else {
                $.ajax(
                    {
                        url: defaults.host.replace("cdn.", "") + '/api/v1/meta/' + db + '/' + arr[i],
                        dataType: 'jsonp',
                        jsonp: 'jsonp_callback',
                        success: function (response) {
                            metaData = response;
                            for (i = 0; i < metaData.data.length; i = i + 1) {
                                metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                                if (!metaData.data[i].f_table_title) {
                                    metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                                }
                                metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i];
                                var layerName = metaData.data[i].f_table_schema + "." + metaData.data[i].f_table_name;
                                layers[layerName] = cloud.addTileLayers({
                                    host: defaults.host,
                                    layers: [layerName],
                                    isBaseLayer: metaData.data[i].baselayer,
                                    visibility: !metaData.data[i].baselayer,
                                    db: db,
                                    wrapDateLine: false,
                                    name: layerName
                                });
                                cloud.setZIndexOfLayer(layers[layerName][0], metaData.data[i].sort_id + 1000);
                                if (metaData.data[i].baselayer) {
                                    text = (metaData.data[i].f_table_title === null || metaData.data[i].f_table_title === "") ? metaData.data[i] : metaData.data[i].f_table_title;
                                    $("#base-layer-list-" + id).append(
                                        "<li><a href=\"javascript:void(0)\" onclick=\"gc2map.maps['" + id + "'].setBaseLayer('" + metaData.data[i].f_table_schema + "." + metaData.data[i].f_table_name + "')\">" + text + "</a></li>"
                                    );
                                }
                            }
                        }
                    }
                );
            }
        }
        addLegend = function () {
            var param = 'l=' + cloud.getVisibleLayers(false);
            if (legendDirty === true) {
                return false;
            }
            $.ajax({
                url: defaults.host + '/api/v1/legend/json/' + db + '/?' + param,
                dataType: 'jsonp',
                jsonp: 'jsonp_callback',
                success: function (response) {
                    var table = $("<table/>", {border: '0'}), tr, td;
                    $.each(response, function (i, v) {
                        var u, showLayer = false;
                        if (typeof v === "object") {
                            for (u = 0; u < v.classes.length; u = u + 1) {
                                if (v.classes[u].name !== "") {
                                    showLayer = true;
                                }
                            }
                            if (showLayer) {
                                tr = $("<tr/>");
                                tr.append("<td><div class='layer-title' style='width:15px;'><span><input onchange=\"gc2map.maps['" + id + "'].switchLayer(this.id, this.checked)\" id='" + v.id + "' type='checkbox' checked></span></div></td>");
                                td = $("<td/>");
                                for (u = 0; u < v.classes.length; u = u + 1) {
                                    if (v.classes[u].name !== "") {
                                        td.append("<div style='margin-top: 0; clear: both'><div class='class-title' style='float: left;margin-top: 2px'><img class='legend-img' src='data:image/png;base64, " + v.classes[u].img + "' /></div><div style='width: 115px; float: right;' class='legend-text'>" + v.classes[u].name + "</div></div>");
                                    }
                                }
                                tr.append(td);
                            }
                            table.append(tr);
                            // Spacer
                            table.append($("<tr style='height: 5px'/>"));
                        }
                    });
                    $('#legend-' + id).html(table);
                }
            });
        };
        addLegend();
        cloud.on("dblclick", function () {
            clicktimer = undefined;
        });
        cloud.on("click", function (e) {
            var layers, count = 0, hit = false, event = new geocloud.clickEvent(e, cloud), distance, sql;
            if (clicktimer) {
                clearTimeout(clicktimer);
            } else {
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
                        var isEmpty = true,
                            srid = metaDataKeys[value.split(".")[1]].srid,
                            geoType = metaDataKeys[value.split(".")[1]].type,
                            layerTitel = (metaDataKeys[value.split(".")[1]].f_table_title !== null && metaDataKeys[value.split(".")[1]].f_table_title !== "") ? metaDataKeys[value.split(".")[1]].f_table_title : metaDataKeys[value.split(".")[1]].f_table_name,
                            not_querable = metaDataKeys[value.split(".")[1]].not_querable,
                            res;
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            res = [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
                                4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
                                76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
                                1.19432856696, 0.597164283478, 0.298582141739];
                            distance = 5 * res[cloud.getZoom()];
                        }
                        qstore[index] = new geocloud.sqlStore({
                            host: defaults.host,
                            db: db,
                            id: index,
                            onLoad: function () {
                                var layerObj = qstore[this.id], out = [], fieldLabel;
                                isEmpty = layerObj.isEmpty();
                                if (!isEmpty && !not_querable) {
                                    $('#modal-info-' + id).modal({"backdrop": false});
                                    var fieldConf = $.parseJSON(metaDataKeys[value.split(".")[1]].fieldconf);
                                    $("#info-tab-" + id).append('<li><a data-toggle="tab" href="#_' + index + '-' + id + '">' + layerTitel + '</a></li>');
                                    $("#info-pane-" + id).append('<div class="tab-pane" id="_' + index + '-' + id + '"><table class="table table-condensed"><thead><tr><th>' + __("Property") + '</th><th>' + __("Value") + '</th></tr></thead></table></div>');

                                    $.each(layerObj.geoJSON.features, function (i, feature) {
                                        if (fieldConf === null) {
                                            $.each(feature.properties, function (name, property) {
                                                out.push([name, 0, name, property]);
                                            });
                                        } else {
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
                                            $("#_" + index + "-" + id + " table").append('<tr><td>' + property[2] + '</td><td>' + property[3] + '</td></tr>');
                                        });
                                        out = [];
                                        $('#info-tab-' + id + ' a:first').tab('show');
                                    });
                                    hit = true;
                                } else {
                                    layerObj.reset();
                                }
                                count = count + 1;
                                if (count === layers.length) {
                                    if (!hit) {
                                        // Do not try to hide a not initiated modal
                                        $("#modal-info-" + id).modal('hide');
                                    }
                                }
                            }
                        });
                        cloud.addGeoJsonStore(qstore[index]);
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_buffer(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913), " + distance + " )," + srid + "),the_geom) LIMIT 5";
                        } else {
                            sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913)," + srid + "),the_geom)";
                        }
                        qstore[index].sql = sql;
                        qstore[index].load();
                    });
                }, 250);
            }
        });
        defaults.callBack();
    };
    return {
        init: init,
        cloud: cloud,
        map: cloud.map,
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