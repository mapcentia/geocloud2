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
var Viewer;
Viewer = function () {
    "use strict";
    var init, switchLayer, arrMenu, setBaseLayer, addLegend, autocomplete, hostname, cloud, db, schema, uri, hash, osm, showInfoModal, qstore = [], share, permaLink, anchor, shareTwitter, shareFacebook, shareLinkedIn, shareGooglePlus, shareTumblr, shareStumbleupon, linkToSimpleMap, drawOn = false, drawLayer, drawnItems, drawControl, zoomControl, metaDataKeys = [], metaDataKeysTitle = [], awesomeMarker;
    hostname = geocloud_host;
    uri = geocloud.pathName;
    hash = decodeURIComponent(geocloud.urlHash);
    db = uri[3];
    schema = uri[4];
    arrMenu = [
        {
            title: __('Layers'),
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
        addLegend();
        try {
            history.pushState(null, null, permaLink());
        } catch (e) {
        }
    };
    addLegend = function () {
        var param = 'l=' + cloud.getVisibleLayers(true);
        $.ajax({
            url: hostname + '/api/v1/legend/json/' + db + '/?' + param,
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                var list = $("<ul/>"), li, classUl, title;
                $.each(response, function (i, v) {
                    try {
                        title = metaDataKeys[v.id.split(".")[1]].f_table_title;
                    }
                    catch (e) {
                    }
                    var u, showLayer = false;
                    if (typeof v === "object") {
                        for (u = 0; u < v.classes.length; u = u + 1) {
                            if (v.classes[u].name !== "") {
                                showLayer = true;
                            }
                        }
                        if (showLayer) {
                            li = $("<li/>");

                            classUl = $("<ul/>");
                            for (u = 0; u < v.classes.length; u = u + 1) {
                                if (v.classes[u].name !== "") {
                                    classUl.append("<li><img class='legend-img' src='data:image/png;base64, " + v.classes[u].img + "' /><span class='legend-text'>" + v.classes[u].name + "</span></li>");
                                }
                            }
                            // title
                            list.append($("<li>" + title + "</li>"));
                            list.append(li.append(classUl));

                        }

                    }
                });
                $('#legend').html(list);
            }
        });
    };
    share = function () {
        var url = hostname + linkToSimpleMap(), layers, arr = [], layersStr = "", i, p, javascript;
        $("#modal-share").modal();
        $("#share-url").val(url);
        $("#share-iframe").val("<iframe width='100%' height='500px' frameBorder='0' src='" + url + "'></iframe>");
        //var bbox = cloud.getExtent();
        p = geocloud.transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
        $("#share-static").val(window.gc2Options.staticMapHost + "/api/v1/staticmap/png/" + db + "?baselayer=" + cloud.getBaseLayerName().toUpperCase() + "&layers=" + cloud.getNamesOfVisibleLayers() + "&size=" + cloud.map.getSize().x + "x" + cloud.map.getSize().y + "&zoom=" + Math.round(cloud.getZoom()).toString() + "&center=" + (Math.round(p.y * 10000) / 10000).toString() + "," + (Math.round(p.x * 10000) / 10000).toString() + "&lifetime=3600");

        layers = cloud.getNamesOfVisibleLayers();
        if (layers.length > 0) {
            for (i = 0; i < layers.split(",").length; i = i + 1) {
                arr.push("'" + layers.split(",")[i] + "'");
            }
            layersStr = arr.join(",");
        }
        javascript =
            "<script src='" + hostname + "/apps/widgets/gc2map/js/gc2map.js'></script>\n" +
            "<div></div>\n" +
            "<script>\n" +
            "(function () {\n" +
            "gc2map.init({\n" +
            "          db: '" + db + "',\n" +
            "          layers: [" + layersStr + "],\n" +
            "          zoom: [" + cloud.getCenter().lon.toString() + "," + cloud.getCenter().lat.toString() + "," + Math.round(cloud.getZoom()).toString() + "],\n" +
            "          setBaseLayer: '" + cloud.getBaseLayerName() + "',    \n" +
            "          width: '100%',\n" +
            "          height: '400px',\n" +
            "          schema: '" + schema + "'\n" +
            "     });\n" +
            "}())\n" +
            "</script>";
        $("#share-javascript").val(javascript);
        $("#share-javascript-object").val(function () {
            var l = [];
            if (layers) {
                $.each(layers.split(","), function (index, value) {
                    l.push("{\"name\":\"" + value + "\"}");
                });
                return "[" + l.join(",") + "]";
            }
        });
        $("#share-extent").val(cloud.getExtent().left + "," + cloud.getExtent().bottom + "," + cloud.getExtent().right + "," + cloud.getExtent().top);
    };
    shareTwitter = function () {
        var url = hostname + linkToSimpleMap();
        window.open("https://twitter.com/share?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareLinkedIn = function () {
        var url = hostname + linkToSimpleMap();
        window.open("https://www.linkedin.com/cws/share?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareGooglePlus = function () {
        var url = hostname + linkToSimpleMap();
        window.open("https://plus.google.com/share?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareFacebook = function () {
        var url = hostname + linkToSimpleMap();
        window.open("https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareTumblr = function () {
        var url = hostname + linkToSimpleMap();
        window.open("http://www.tumblr.com/share?v=3&t=My%20map&u=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    shareStumbleupon = function () {
        var url = hostname + linkToSimpleMap();
        window.open("http://www.stumbleupon.com/submit?url=" + encodeURIComponent(url), '_blank', 'location=yes,height=300,width=520,scrollbars=yes,status=yes');
    };
    permaLink = function () {
        var p = geocloud.transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
        return "/apps/viewer/" + db + "/" + schema + "/" + anchor();
    };
    linkToSimpleMap = function () {
        return "/apps/widgets/gc2map/" + db + "/" + schema + "/" + anchor();
    };
    anchor = function () {
        var p = geocloud.transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
        return "#" + cloud.getBaseLayerName() + "/" + Math.round(cloud.getZoom()).toString() + "/" + (Math.round(p.x * 10000) / 10000).toString() + "/" + (Math.round(p.y * 10000) / 10000).toString() + "/" + ((cloud.getNamesOfVisibleLayers()) ? cloud.getNamesOfVisibleLayers().split(",").reverse().join(",") : "");
    };
    autocomplete = new google.maps.places.Autocomplete(document.getElementById('search-input'));
    google.maps.event.addListener(autocomplete, 'place_changed', function () {
        var place = autocomplete.getPlace(),
            center = new geocloud.transformPoint(place.geometry.location.lng(), place.geometry.location.lat(), "EPSG:4326", "EPSG:900913");
        cloud.zoomToPoint(center.x, center.y, 18);
        if (awesomeMarker !== undefined) cloud.map.removeLayer(awesomeMarker);
        awesomeMarker = L.marker([place.geometry.location.lat(), place.geometry.location.lng()], {
            icon: L.AwesomeMarkers.icon({
                icon: 'home',
                markerColor: 'blue',
                prefix: 'fa'
            })
        }).addTo(cloud.map);
        setTimeout(function () {
            /*var p = new R.Pulse(
             [place.geometry.location.lat(), place.geometry.location.lng()],
             30,
             {'stroke': 'none', 'fill': 'none'},
             {'stroke': '#30a3ec', 'stroke-width': 3}
             );
             cloud.map.addLayer(p);
             setTimeout(function () {
             cloud.map.removeLayer(p);
             }, 1000);*/
        }, 300);
    });
    cloud = new geocloud.map({
        el: "map",
        zoomControl: false
    });
    zoomControl = L.control.zoom({
        position: 'bottomright'
    });
    cloud.map.addControl(zoomControl);
    // Start of draw
    if (window.gc2Options.leafletDraw) {
        $("#draw-button-li").show();
        cloud.map.on('draw:created', function (e) {
            var type = e.layerType;
            drawLayer = e.layer;

            if (type === 'marker') {
                var text = prompt("Enter a text for the marker or cancel to add without text", "");
                if (text !== null) {
                    drawLayer.bindLabel(text, {noHide: true}).on("click", function () {
                    }).showLabel();
                }
            }
            drawnItems.addLayer(drawLayer);
        });
        $("#draw-button").on("click", function () {
            if (!drawOn) {
                drawnItems = new L.FeatureGroup();
                drawControl = new L.Control.Draw({
                    position: 'bottomright',
                    draw: {
                        polygon: {
                            title: 'Draw a polygon!',
                            allowIntersection: false,
                            drawError: {
                                color: '#b00b00',
                                timeout: 1000
                            },
                            shapeOptions: {
                                color: '#bada55'
                            },
                            showArea: true
                        },
                        polyline: {
                            metric: true
                        },
                        circle: {
                            shapeOptions: {
                                color: '#662d91'
                            }
                        }
                    },
                    edit: {
                        featureGroup: drawnItems
                    }
                });
                cloud.map.addLayer(drawnItems);
                cloud.map.addControl(drawControl);

                drawOn = true;
            } else {
                cloud.map.removeControl(drawControl);
                drawnItems.removeLayer(drawLayer);
                cloud.map.removeLayer(drawnItems);
                drawOn = false;
            }
        });
    }
    // Draw end
    init = function () {
        var metaData, layers = {}, jRes, node, modalFlag, extent = null, i;

        $('.share-text').mouseup(function () {
            return false;
        });
        $(".share-text").focus(function () {
            $(this).select();
        });

        if (window.gc2Options.extraShareFields){
            $("#group-javascript-object").show();
            $("#group-extent").show();
        }

        if (typeof window.setBaseLayers !== 'object') {
            window.setBaseLayers = [
                {"id": "mapQuestOSM", "name": "MapQuset OSM"},
                {"id": "osm", "name": "OSM"},
                {"id": "stamenToner", "name": "Stamen toner"}
            ];
        }
        cloud.bingApiKey = window.bingApiKey;
        cloud.digitalGlobeKey = window.digitalGlobeKey;
        for (i = 0; i < window.setBaseLayers.length; i = i + 1) {
            if (typeof window.setBaseLayers[i].restrictTo === "undefined" || window.setBaseLayers[i].restrictTo.indexOf(schema) > -1) {
                cloud.addBaseLayer(window.setBaseLayers[i].id, window.setBaseLayers[i].db);
                $("#base-layer-list").append(
                    "<li><a href=\"javascript:void(0)\" onclick=\"MapCentia.setBaseLayer('" + window.setBaseLayers[i].id + "')\">" + window.setBaseLayers[i].name + "</a></li>"
                );
            }
        }
        $("#locate-btn").on("click", function () {
            cloud.locate();
        });

        $("#modal-info").on('hidden.bs.modal', function (e) {
            $.each(qstore, function (i, v) {
                qstore[i].reset();
            });
        });
        showInfoModal = function () {
            modalFlag = true;
            $('#modal-info').modal({"backdrop": false});
        };
        $.ajax({
            url: geocloud_host.replace("cdn.", "") + '/api/v1/meta/' + db + '/' + schema,
            dataType: 'jsonp',
            scriptCharset: "utf-8",
            async: false,
            jsonp: 'jsonp_callback',
            success: function (response) {
                var base64name, authIcon, isBaseLayer, arr, groups, metaUrl = "", i, u, l;
                groups = [];
                metaData = response;
                for (i = 0; i < metaData.data.length; i = i + 1) {
                    metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                    if (!metaData.data[i].f_table_title) {
                        metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                    }
                    metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i];
                }
                for (i = 0; i < response.data.length; i = i + 1) {
                    groups[i] = response.data[i].layergroup;

                }
                arr = array_unique(groups);
                for (u = 0; u < response.data.length; u = u + 1) {
                    isBaseLayer = response.data[u].baselayer;
                    layers[[response.data[u].f_table_schema + "." + response.data[u].f_table_name]] = cloud.addTileLayers({
                        layers: [response.data[u].f_table_schema + "." + response.data[u].f_table_name],
                        db: db,
                        isBaseLayer: isBaseLayer,
                        visibility: false,
                        wrapDateLine: false,
                        displayInLayerSwitcher: true,
                        name: response.data[u].f_table_schema + "." + response.data[u].f_table_name
                    });
                }
                for (i = 0; i < arr.length; i = i + 1) {
                    if (arr[i]) {
                        l = [];
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
                                if (response.data[u].baselayer) {
                                    $("#base-layer-list").append(
                                        "<li><a href=\"javascript:void(0)\" onclick=\"MapCentia.setBaseLayer('" + response.data[u].f_table_schema + "." + response.data[u].f_table_name + "')\">" + text + "</a></li>"
                                    );
                                } else {
                                    l.push(
                                        {
                                            text: text,
                                            id: response.data[u].f_table_schema + "." + response.data[u].f_table_name,
                                            leaf: true,
                                            checked: false
                                        }
                                    );
                                    node.items[0].items.push(
                                        {
                                            name: cat,
                                            metaIcon: 'fa fa-info-circle',
                                            link: '#',
                                            metaUrl: response.data[u].meta_url
                                        }
                                    );
                                }
                            }
                        }
                        // Don't add empty group
                        if (node.items[0].items.length > 0) {
                            node.items[0].items.reverse();
                            arrMenu[0].items.push(node);
                        }
                    }
                }
                if (window.gc2Options.reverseLayerOrder) {
                    arrMenu[0].items.reverse();
                }
                $('#menu').multilevelpushmenu({
                    menu: arrMenu
                });
            }
        }); // Ajax call end
        $.ajax({
            url: geocloud_host.replace("cdn.", "") + '/api/v1/setting/' + db,
            async: false,
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                if (typeof response.data.extents === "object") {
                    if (typeof response.data.extents[schema] === "object") {
                        extent = response.data.extents[schema];
                    }
                }
            }
        }); // Ajax call end
        var sub, eWidth, eHeight;
        jRes = jRespond([
            {
                label: 'handheld',
                enter: 0,
                exit: 767
            },
            {
                label: 'desktop',
                enter: 768,
                exit: 10000
            }
        ]);
        jRes.addFunc({
            breakpoint: ['handheld'],
            enter: function () {
                sub = 115;
                eWidth = $("#map").width();
                eHeight = $("#map").height();
                // We activate the modals
                $("#modal-legend .modal-body").append($("#legend"));
                $(".modal-body").css({"height": (eHeight - sub) + "px"});
                $('#legend-modal').on('click', function (e) {
                    $('#modal-legend').modal();
                    addLegend();
                });
            },
            exit: function () {
                $('#modal-legend').modal('hide');
            }
        });
        jRes.addFunc({
            breakpoint: ['desktop'],
            enter: function () {
                sub = 175;
                eWidth = $("#map").width();
                eHeight = $("#map").height();
                $("#legend-popover").popover({
                    offset: 10,
                    html: true,
                    content: $("#legend")
                }).popover('show').popover('hide');
                $('#legend-popover').on('click', function (e) {
                    addLegend();
                    $("#legend").css({"max-height": (eHeight - 100) + "px"});
                });
                $(".modal-body").css({"max-height": (eHeight - sub) + "px"});
                //$("#modal-info-body").css({"max-height": (eHeight < 350) ? (eHeight - sub) : (220) + "px"});
                //$("#modal-share-body").css({"max-height": (eHeight - sub) + "px"});
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
                    setBaseLayer(hashArr[0]);
                    if (hashArr[4]) {
                        arr = hashArr[4].split(",");
                        for (i = 0; i < arr.length; i++) {
                            switchLayer(arr[i], true);
                            $("#" + arr[i].replace(schema + ".", "")).attr('checked', true);
                        }
                    }
                    p = geocloud.transformPoint(hashArr[2], hashArr[3], "EPSG:4326", "EPSG:900913");
                    cloud.zoomToPoint(p.x, p.y, hashArr[1]);
                }
            } else {
                setBaseLayer(window.setBaseLayers[0].id);
                if (extent !== null) {
                    cloud.zoomToExtent(extent);
                } else {
                    cloud.zoomToExtent();
                }
            }
        }());
        var moveEndCallBack = function () {
            try {
                history.pushState(null, null, permaLink());
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
                        if (layers[0] === "") {
                            return false;
                        }
                        var isEmpty = true;
                        var srid = metaDataKeys[value.split(".")[1]].srid;
                        var geoType = metaDataKeys[value.split(".")[1]].type;
                        var layerTitel = (metaDataKeys[value.split(".")[1]].f_table_title !== null && metaDataKeys[value.split(".")[1]].f_table_title !== "") ? metaDataKeys[value.split(".")[1]].f_table_title : metaDataKeys[value.split(".")[1]].f_table_name;
                        var not_querable = metaDataKeys[value.split(".")[1]].not_querable;
                        var versioning = metaDataKeys[value.split(".")[1]].versioning;
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            var res = [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
                                4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
                                76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
                                1.19432856696, 0.597164283478, 0.298582141739, 0.149291];
                            distance = 5 * res[cloud.getZoom()];
                        }
                        qstore[index] = new geocloud.sqlStore({
                            db: db,
                            id: index,
                            onLoad: function () {
                                var layerObj = qstore[this.id], out = [], fieldLabel;
                                isEmpty = layerObj.isEmpty();
                                if (!isEmpty && !not_querable) {
                                    showInfoModal();
                                    var fieldConf = $.parseJSON(metaDataKeys[value.split(".")[1]].fieldconf);
                                    $("#info-tab").append('<li><a data-toggle="tab" href="#_' + index + '">' + layerTitel + '</a></li>');
                                    $("#info-pane").append('<div class="tab-pane" id="_' + index + '"><table class="table table-condensed"><thead><tr><th>' + __("Property") + '</th><th>' + __("Value") + '</th></tr></thead></table></div>');

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
                                                    if (property.link) {
                                                        out.push([name, property.sort_id, fieldLabel, "<a target='_blank' href='" + (property.linkprefix !== null ? property.linkprefix : "") + feature.properties[name] + "'>" + feature.properties[name] + "</a>"]);
                                                    }
                                                    else {
                                                        out.push([name, property.sort_id, fieldLabel, feature.properties[name]]);
                                                    }
                                                }
                                            });
                                        }
                                        out.sort(function (a, b) {
                                            return a[1] - b[1];
                                        });
                                        $.each(out, function (name, property) {
                                            $("#_" + index + " table").append('<tr><td>' + property[2] + '</td><td>' + property[3] + '</td></tr>');
                                        });
                                        out = [];
                                        $('#info-tab a:first').tab('show');
                                    });
                                    hit = true;
                                } else {
                                    layerObj.reset();
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
                        var sql, f_geometry_column = metaDataKeys[value.split(".")[1]].f_geometry_column;
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            sql = "SELECT * FROM " + value + " WHERE round(ST_Distance(ST_Transform(\"" + f_geometry_column + "\",3857), ST_GeomFromText('POINT(" + coords.x + " " + coords.y + ")',3857))) < " + distance;
                            if (versioning) {
                                sql = sql + " AND gc2_version_end_date IS NULL";
                            }
                            sql = sql + " ORDER BY round(ST_Distance(ST_Transform(\"" + f_geometry_column + "\",3857), ST_GeomFromText('POINT(" + coords.x + " " + coords.y + ")',3857)))";
                        } else {
                            sql = "SELECT * FROM " + value + " WHERE ST_Intersects(ST_Transform(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',900913)," + srid + ")," + f_geometry_column + ")";
                            if (versioning) {
                                sql = sql + " AND gc2_version_end_date IS NULL";
                            }
                        }
                        sql = sql + "LIMIT 5";
                        qstore[index].sql = sql;
                        qstore[index].load();
                    });
                }, 250);
            }
        });
    };
    return {
        init: init,
        cloud: cloud,
        switchLayer: switchLayer,
        setBaseLayer: setBaseLayer,
        schema: schema,
        share: share,
        shareTwitter: shareTwitter,
        shareFacebook: shareFacebook,
        shareLinkedIn: shareLinkedIn,
        shareGooglePlus: shareGooglePlus,
        shareTumblr: shareTumblr,
        shareStumbleupon: shareStumbleupon
    };
};




