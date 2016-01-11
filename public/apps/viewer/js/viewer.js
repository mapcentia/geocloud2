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
    var init, switchLayer, arrMenu, setBaseLayer, addLegend, autocomplete, hostname, cloud, db, schema, uri, urlVars, hash, osm, showInfoModal, qstore = [], share, permaLink, anchor, shareTwitter, shareFacebook, shareLinkedIn, shareGooglePlus, shareTumblr, shareStumbleupon, linkToSimpleMap, drawOn = false, drawLayer, drawnItems, drawControl, zoomControl, metaData, metaDataKeys = [], metaDataKeysTitle = [], awesomeMarker, addSqlFilterForm, sqlFilterStore, indexedLayers = [], mouseOverDisplay, visibleLayers, enablePrint,
        res = [156543.033928, 78271.516964, 39135.758482, 19567.879241, 9783.9396205,
            4891.96981025, 2445.98490513, 1222.99245256, 611.496226281, 305.748113141, 152.87405657,
            76.4370282852, 38.2185141426, 19.1092570713, 9.55462853565, 4.77731426782, 2.38865713391,
            1.19432856696, 0.597164283478, 0.298582141739, 0.149291];
    uri = geocloud.pathName;
    hostname = geocloud_host;
    hash = decodeURIComponent(geocloud.urlHash);
    db = uri[3];
    schema = uri[4];
    urlVars = geocloud.urlVars;
    arrMenu = [
        {
            title: __('Layers'),
            id: 'menuID',
            icon: 'fa fa-reorder',
            items: []
        }
    ];
    enablePrint = (window.gc2Options.enablePrint !== null && typeof window.gc2Options.enablePrint[db] !== "undefined" && window.gc2Options.enablePrint[db] === true) || (window.gc2Options.enablePrint !== null && typeof window.gc2Options.enablePrint["*"] !== "undefined" && window.gc2Options.enablePrint["*"] === true);
    switchLayer = function (name, visible) {
        if (visible) {
            cloud.showLayer(name);
        } else {
            cloud.hideLayer(name);
        }
        try {
            history.pushState(null, null, permaLink());
        } catch (e) {
        }
        addLegend();
        visibleLayers = cloud.getVisibleLayers(true);
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
                var list = $("<ul/>"), li, classUl, title, className;
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
                                if (v.classes[u].name !== "" || v.classes[u].name === "_gc2_wms_legend") {
                                    className = (v.classes[u].name !== "_gc2_wms_legend") ? "<span class='legend-text'>" + v.classes[u].name + "</span>" : "";
                                    classUl.append("<li><img class='legend-img' src='data:image/png;base64, " + v.classes[u].img + "' />" + className + "</li>");
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

    addSqlFilterForm = function () {
        var i, sqlFilterEnabled = false, layerPopup;
        $("#sql-filter-table").append('<option value="">' + __("Choose layer") + '</option>');

        for (i = 0; i < metaData.data.length; i = i + 1) {
            if (metaData.data[i].enablesqlfilter) {
                $("#sql-filter-table").append('<option value="' + metaData.data[i].f_table_name + '">' + metaData.data[i].f_table_name + '</option>');
                sqlFilterEnabled = true;
            }
        }
        if (sqlFilterEnabled) {
            $("#filter-popover-li").show();
            $("#filter-modal-li").show();
        }
        $("#sql-filter-table").on("change",
            function () {
                var fieldConf, formSchema = {}, form = [], table, value = $("#sql-filter-table").val(), arr, v;
                try {
                    cloud.removeGeoJsonStore(sqlFilterStore);
                    sqlFilterStore.reset();
                } catch (e) {
                }
                fieldConf = $.parseJSON(metaDataKeys[value].fieldconf);
                table = schema + "." + value;
                $.each(fieldConf, function (i, v) {
                    if (v.type !== "geometry" && v.filter === true) {
                        formSchema[i] = {
                            sort_id: v.sort_id,
                            type: (v.type === "decimal (3 10)" || v.type === "int") ? "number" : "string",
                            title: v.alias || i
                        };
                        if (v.properties && v.properties !== "") {
                            try {
                                arr = $.parseJSON(v.properties);
                                arr.unshift("");
                                formSchema[i].enum = arr;
                            } catch (e) {
                            }
                        }
                    }
                });

                v = _.pairs(formSchema)
                v.sort(function (a, b) {
                    var keyA = a[1].sort_id,
                        keyB = b[1].sort_id;
                    if (keyA < keyB) {
                        return -1;
                    }
                    if (keyA > keyB) {
                        return 1;
                    }
                    return 0;
                });
                formSchema = _.object(v);

                formSchema._gc2_filter_operator = {
                    "type": "string",
                    "enum": ["and", "or"],
                    "default": "and"
                };
                formSchema._gc2_filter_spatial = {};
                sqlFilterStore = new geocloud.sqlStore({
                    db: db,
                    clickable: false,
                    jsonp: false,
                    error: function (e) {
                        alert(e.responseJSON.message);
                    },
                    styleMap: {
                        "color": "#ff0000",
                        "weight": 5,
                        "opacity": 0.65,
                        "fillOpacity": 0
                    },
                    /*onEachFeature: function (feature, layer) {
                     var html = "";
                     $.each(formSchema, function (i, v) {
                     if (i !== "_gc2_filter_operator" && i !== "_gc2_filter_spatial") {
                     html = html + v.title + " : " + feature.properties[i] + "<br>";
                     }
                     });
                     layer.bindPopup(html);
                     },*/
                    onLoad: function () {
                        $("#filter-submit").prop('disabled', false);
                        $("#filter-submit .spinner").hide();
                        if (sqlFilterStore.geoJSON) {
                            cloud.zoomToExtentOfgeoJsonStore(sqlFilterStore);
                            $("#sql-filter-res").append("<a target='_blank' href='/api/v1/sql/" + db + "?q=" + encodeURIComponent(this.sql).replace(/'/g, "%27") + '&srs=' + this.defaults.projection + '&lifetime=' + this.defaults.lifetime + "&srs=" + this.defaults.projection + '&client_encoding=' + this.defaults.clientEncoding + "'>" + __("Get result as GeoJSON") + "</a>");
                        } else {
                            alert(__("Query did not return any features"));
                        }
                    }
                });
                /*sqlFilterStore.layer.on({
                 mouseover: function (e) {
                 layerPopup = L.popup()
                 .setLatLng(e.latlng)
                 .setContent('Popup for feature #' */
                /*+ e.layer.feature.properties.id*/
                /*)
                 .openOn(cloud.map);
                 },
                 mouseout: function (e) {
                 cloud.map.closePopup(layerPopup);
                 layerPopup = null;

                 }
                 });*/
                cloud.addGeoJsonStore(sqlFilterStore);
                $('#sql-filter-form').empty();
                form.push({
                    "type": "help",
                    "helpvalue": __("Set filter values")
                });
                $.each(formSchema, function (i, v) {
                    if (i !== "_gc2_filter_operator" && i !== "_gc2_filter_spatial") {
                        form.push({
                            "key": i
                        });
                    }
                });
                form.push({
                    "type": "help",
                    "helpvalue": __("Match all or any values")
                });
                form.push({
                    "key": "_gc2_filter_operator",
                    "type": "radios",
                    "titleMap": {
                        "and": __("All"),
                        "or": __("Any")
                    }
                });
                form.push({
                    "type": "help",
                    "helpvalue": __("Only match within view extent")
                });
                form.push({
                    "key": "_gc2_filter_spatial",
                    "type": "checkbox"
                });
                form.push({
                    "type": "button",
                    "title": __("Load features"),
                    "id": "filter-submit",
                    "htmlClass": "btn-primary"
                });

                $('#sql-filter-form').jsonForm({
                    schema: formSchema,
                    form: form,
                    "params": {
                        "fieldHtmlClass": "filter-field"
                    },
                    onSubmit: function (errors, values) {
                        var fields = [], where, sql, extent, spatialFilter;
                        $('#sql-filter-res').empty();
                        $("#filter-submit").prop('disabled', true);
                        $("#filter-submit .spinner").show();
                        if (errors) {
                            $("#filter-submit").prop('disabled', false);
                            $("#filter-submit .spinner").hide();
                            $('#sql-filter-res').html('<p>' + __("Error in query. Please check types.") + '</p>');
                        } else {
                            sqlFilterStore.reset();
                            $.each(formSchema, function (name, property) {
                                if (values[name] !== undefined && name !== "_gc2_filter_operator" && name !== "_gc2_filter_spatial") {
                                    if (property.type === "number") {
                                        fields.push(name + "=" + values[name]);
                                    } else {
                                        fields.push(name + "='" + values[name] + "'");
                                    }
                                }
                            });

                            if (fields.length > 0) {
                                where = fields.join(" " + values._gc2_filter_operator + " ");
                            } else {
                                where = "";
                            }
                            if (values._gc2_filter_spatial) {
                                extent = cloud.getExtent();
                                spatialFilter = metaDataKeys[value].f_geometry_column + " && ST_transform(ST_MakeEnvelope(" + extent.left + ", " + extent.bottom + ", " + extent.right + ", " + extent.top + ", 4326), " + metaDataKeys[value].srid + ")";
                                if (where === "") {
                                    where = spatialFilter;

                                } else {
                                    where = "(" + where + ")" + " AND " + spatialFilter;
                                }
                            }
                            sql = "SELECT * FROM " + table;
                            if (where && where !== "") {
                                sql = sql + " WHERE " + where;
                            }
                            sqlFilterStore.sql = sql;
                            sqlFilterStore.load(true);
                        }
                    }
                });
                $("#filter-submit").append("<img src='http://www.gifstache.com/images/ajax_loader.gif' class='spinner'/>");
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
        return "/apps/viewer/" + db + "/" + schema + "/" + (typeof urlVars.i === "undefined" ? "" : "?i=" + urlVars.i.split("#")[0]) + anchor();
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

    // Create the print provider, subscribing to print events
    if (enablePrint) {
        cloud.map.addControl(L.control.print({
            provider: L.print.provider({
                capabilities: window.printConfig,
                method: 'POST',
                dpi: 127,
                outputFormat: 'pdf',
                proxy: '/cgi/proxy.cgi?url=',
                customParams: window.gc2Options.customPrintParams
            }),
            position: 'bottomright'
        }));
    }

// Start of draw
    if (enablePrint) {
        $("#mapclient-button-li").show();
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

        var layers = {}, jRes, node, modalFlag, extent = null, i, addedBaseLayers = [], searchPanelOpen = false,
            searchShow = function () {
                $("#search-ribbon").animate({
                    right: '0'
                }, 500, function () {
                    $("#custom-search").focus();
                    $("#pane").animate({
                        right: '24%'
                    }, 500);

                    $("#map").animate({
                        width: '88%'
                    }, 500);
                });
                $("#modal-info").animate({
                    right: '24%'
                }, 500);
                searchPanelOpen = true;
            },
            searchHide = function () {
                $("#pane").animate({
                    right: '3%'
                }, 500);
                $("#map").animate({
                    width: '100%'
                }, 500, function () {
                    $("#search-ribbon").animate({
                        right: '-21%'
                    }, 500);
                });
                $("#modal-info").animate({
                    right: '0'
                }, 500);
                searchPanelOpen = false
            };
        $('#search-border').click(function () {
            if (searchPanelOpen) {
                searchHide();
            } else {
                searchShow()
            }
        });

        $('.share-text').mouseup(function () {
            return false;
        });
        $(".share-text").focus(function () {
            $(this).select();
        });

        $("#mapclient-button").on("click", function () {
            var center = cloud.getCenter(), zoom = cloud.getZoom(), req, layers = cloud.getVisibleLayers(true).split(";"),
                layerStrs = [];

            for (i = 0; i < layers.length; i = i + 1) {
                if (layers[i] !== "") {
                    layerStrs.push("map_visibility_" + layers[i] + "=true");
                }
            }
            req = "map_x=" + center.x + "&map_y=" + center.y + "&map_zoom=" + zoom + "&" + layerStrs.join("&");
            window.open(hostname + "/apps/mapclient/" + db + "/" + schema + "?" + req + "&print=1");
        });

        if (window.gc2Options.extraShareFields) {
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
                addedBaseLayers.push(window.setBaseLayers[i]);
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
            url: geocloud_host.replace("cdn.", "") + '/api/v1/meta/' + db + '/' + (window.gc2Options.mergeSchemata === null ? "" : window.gc2Options.mergeSchemata.join(",") + ',') + (typeof urlVars.i === "undefined" ? "" : urlVars.i.split("#")[0] + ',') + schema + "?es=1",
            dataType: 'jsonp',
            scriptCharset: "utf-8",
            async: false,
            jsonp: 'jsonp_callback',
            success: function (response) {
                var base64name, authIcon, isBaseLayer, arr, groups, metaUrl = "", i, u, l, baseLayersLi=[];
                groups = [];
                metaData = response;
                for (i = 0; i < metaData.data.length; i = i + 1) {
                    metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                    if (!metaData.data[i].f_table_title) {
                        metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                    }
                    if (metaData.data[i].indexed_in_es) {
                        indexedLayers.push(metaData.data[i].f_table_schema + "." + metaData.data[i].f_table_name);
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
                        name: response.data[u].f_table_schema + "." + response.data[u].f_table_name,
                        type: "tms"
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
                                    title: arr[i] === "<font color='red'>[Ungrouped]</font>" ? "[Ungrouped]" : arr[i],
                                    icon: 'fa fa-folder',
                                    items: []
                                }
                            ]
                        };
                        for (u = 0; u < response.data.length; ++u) {
                            isBaseLayer = response.data[u].baselayer;
                            if (response.data[u].layergroup === arr[i] && ((response.data[u].layergroup !== "<font color='red'>[Ungrouped]</font>" || window.gc2Options.hideUngroupedLayers !== true) || isBaseLayer === true )) {
                                authIcon = (response.data[u].authentication === "Read/write") ? " <i data-toggle='tooltip' title='first tooltip' class='fa fa-lock'></i>" : "";
                                var text = (response.data[u].f_table_title === null || response.data[u].f_table_title === "") ? response.data[u].f_table_name : response.data[u].f_table_title;
                                var cat = '<div class="checkbox"><label><input type="checkbox" id="' + response.data[u].f_table_name + '" data-gc2-id="' + response.data[u].f_table_schema + "." + response.data[u].f_table_name + '"onchange="MapCentia.switchLayer($(this).data(\'gc2-id\'),this.checked)" value="">' + text + authIcon + metaUrl + '</label></div>';
                                if (response.data[u].baselayer) {
                                        baseLayersLi.push("<li><a href=\"javascript:void(0)\" onclick=\"MapCentia.setBaseLayer('" + response.data[u].f_table_schema + "." + response.data[u].f_table_name + "')\">" + text + "</a></li>");

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
                        // Append baselayers
                        baseLayersLi.reverse();
                        for (u = 0; u < baseLayersLi.length; ++u) {
                            $("#base-layer-list").append(baseLayersLi[u]);
                        }

                        // Don't add empty group
                        if (node.items[0].items.length > 0) {
                            node.items[0].items.reverse();
                            arrMenu[0].items.push(node);
                        }
                    }
                }
                arrMenu[0].items.reverse();
                $('#menu').multilevelpushmenu({
                    menu: arrMenu
                });
                addSqlFilterForm();
            }
        }); // Ajax call end
        $.ajax({
            url: geocloud_host.replace("cdn.", "") + '/api/v1/setting/' + db,
            async: false,
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                var p1, p2, restrictedExtent,
                    firstSchema = schema.split(",").length > 1 ? schema.split(",")[0] : schema;
                if (typeof response.data.extents === "object") {
                    if (typeof response.data.extents[firstSchema] === "object") {
                        extent = response.data.extents[firstSchema];
                    }
                }
                if (typeof response.data.extentrestricts !== "undefined") {
                    if (response.data.extentrestricts[firstSchema] !== undefined && response.data.extentrestricts[firstSchema] !== null) {
                        restrictedExtent = response.data.extentrestricts[firstSchema];
                        p1 = geocloud.transformPoint(restrictedExtent[0], restrictedExtent[1], "EPSG:900913", "EPSG:4326");
                        p2 = geocloud.transformPoint(restrictedExtent[2], restrictedExtent[3], "EPSG:900913", "EPSG:4326");
                        cloud.map.setMaxBounds([[p1.y, p1.x], [p2.y, p2.x]]);
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
                $("#modal-filter .modal-body").append($("#filter"));
                $('#filter-modal').on('click', function (e) {
                    $('#modal-filter').modal();
                });
            },
            exit: function () {
                $('#modal-legend').modal('hide');
                $('#modal-filter').modal('hide');
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
                $("#filter-popover").popover({
                    offset: 10,
                    html: true,
                    content: $("#filter")
                }).popover('show').popover('hide');
                $('#filter-popover').on('click', function (e) {
                    $("#filter").css({"max-height": (eHeight - 100) + "px"});
                });
                $(".modal-body").css({"max-height": (eHeight - sub) + "px"});
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
                            $('*[data-gc2-id="' + arr[i] + '"]').attr('checked', true);
                        }
                    }
                    p = geocloud.transformPoint(hashArr[2], hashArr[3], "EPSG:4326", "EPSG:900913");
                    cloud.zoomToPoint(p.x, p.y, hashArr[1]);
                }
            } else {
                setBaseLayer(addedBaseLayers[0].id);
                if (extent !== null) {
                    cloud.zoomToExtent(extent);
                } else {
                    cloud.zoomToExtent();
                }
            }
            visibleLayers = cloud.getVisibleLayers(true);
            try {
                if (typeof cloud.getActiveBaseLayer().layer.options.attribution !== "undefined") {
                    window.gc2Options.customPrintParams.mapAttribution = cloud.getActiveBaseLayer().layer.options.attribution;
                } else {
                    window.gc2Options.customPrintParams.mapAttribution = "";
                }
            } catch (e) {
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
                        var fieldConf = metaDataKeys[value.split(".")[1]].fieldconf;
                        var data = metaDataKeys[value.split(".")[1]].data || "SELECT * FROM \"" + metaDataKeys[value.split(".")[1]].f_table_schema + "\".\"" + metaDataKeys[value.split(".")[1]].f_table_name + "\"";
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            distance = 5 * res[cloud.getZoom()];
                        };
                        var orderBy;
                        qstore[index] = new geocloud.sqlStore({
                            db: db,
                            id: index,
                            styleMap: {
                                "color": "#0000ff",
                                "weight": 5,
                                "opacity": 0.65,
                                "fillOpacity": 0
                            },
                            clickable: false,
                            onLoad: function () {
                                var layerObj = qstore[this.id], out = [], fieldLabel;
                                isEmpty = layerObj.isEmpty();
                                if (!isEmpty && !not_querable) {
                                    showInfoModal();
                                    var fieldConf = $.parseJSON(metaDataKeys[value.split(".")[1]].fieldconf), imageUrl;
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
                                                    if (feature.properties[name] !== undefined) {
                                                        if (property.link) {
                                                            out.push([name, property.sort_id, fieldLabel, "<a target='_blank' href='" + (property.linkprefix !== null ? property.linkprefix : "") + feature.properties[name] + "'>" + feature.properties[name] + "</a>"]);
                                                        } else if (property.image) {
                                                            imageUrl = (property.type === "bytea" ? atob(feature.properties[name]) : feature.properties[name]);
                                                            out.push([name, property.sort_id, fieldLabel, "<a target='_blank' href='" + imageUrl + "'><img style='width:178px' src='" + imageUrl + "'/></a>"]);
                                                        }
                                                        else {
                                                            out.push([name, property.sort_id, fieldLabel, feature.properties[name]]);
                                                        }
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
                            },
                            error: function(res){
                                alert(res.responseJSON.message);
                            }
                        });
                        cloud.addGeoJsonStore(qstore[index]);
                        var sql, f_geometry_column = metaDataKeys[value.split(".")[1]].f_geometry_column, fields = [], fieldStr;
                        if (fieldConf) {
                            $.each($.parseJSON(fieldConf), function (i, v) {
                                if (v.type === "bytea") {
                                    fields.push("encode(\"" + i + "\",'escape') as \"" + i + "\"");
                                } else if (i !== f_geometry_column) {
                                    fields.push("\"" + i + "\"");
                                }
                            });
                            fieldStr = fields.join(",") + ",\"" + f_geometry_column + "\"";
                        } else {
                            fieldStr = "*";
                        }
                        if (geoType !== "POLYGON" && geoType !== "MULTIPOLYGON") {
                            sql = "SELECT " + fieldStr + " FROM (" + data + ") AS foo WHERE round(ST_Distance(ST_Transform(\"" + f_geometry_column + "\",3857), ST_GeomFromText('POINT(" + coords.x + " " + coords.y + ")',3857))) < " + distance;
                            if (versioning) {
                                sql = sql + " AND gc2_version_end_date IS NULL ";
                            }
                            orderBy = " ORDER BY round(ST_Distance(ST_Transform(\"" + f_geometry_column + "\",3857), ST_GeomFromText('POINT(" + coords.x + " " + coords.y + ")',3857)))";
                            sql = sql + orderBy;
                        } else {
                            sql = "SELECT " + fieldStr + " FROM (" + data + ") AS foo WHERE ST_Intersects(ST_Transform(ST_geomfromtext('POINT(" + coords.x + " " + coords.y + ")',3857)," + srid + ")," + f_geometry_column + ")";
                            if (versioning) {
                                sql = sql + " AND gc2_version_end_date IS NULL ";
                            }
                        }
                        sql = sql + "LIMIT 5";
                        var selectJson = {
                            fields: fieldStr,
                            from: value,
                            order: orderBy,
                            limit: "LIMIT 5"

                        }
                        qstore[index].sql = sql;
                        qstore[index].load();
                    });
                }, 250);
            }
        });

        // Mouse over pop-up begin
        var mouseOverLayer = new L.layerGroup(), mouseOverPopUp, mouseOverStyle = {
            color: '#aaa',
            fillColor: '#aaa',
            fillOpacity: 0.5,
            opacity: 0.5
        };
        mouseOverLayer.addTo(cloud.map);
        mouseOverDisplay = _.debounce(function (e) {
            var visLayers = visibleLayers.split(";"), distance = 3 * res[cloud.getZoom()], qJson, sFilter, mapping, isPoint, metaKeys;
            mouseOverLayer.clearLayers();
            try {
                cloud.map.removeLayer(mouseOverPopUp);
            } catch (e) {
            }
            $.each(indexedLayers, function (i, v) {
                if (visLayers.indexOf(v) !== -1) {
                    metaKeys = metaDataKeys[v.split(".")[1]];
                    mapping = $.parseJSON(metaDataKeys[v.split(".")[1]].es_mapping);
                    if (typeof mapping[db + "_" + metaKeys.f_table_schema].mappings[metaKeys.es_type_name].properties.geometry.type === "undefined") {
                        isPoint = true;
                    } else {
                        isPoint = false;
                    }
                    if (isPoint) {
                        sFilter = {
                            "geo_distance": {
                                "distance": distance + "m",
                                "coordinates": {
                                    "lat": e.latlng.lat,
                                    "lon": e.latlng.lng
                                }
                            }
                        };
                    } else {
                        sFilter = {
                            "bool": {
                                "must": [{
                                    "geo_shape": {
                                        "geometry": {
                                            "shape": {
                                                "type": "circle",
                                                "coordinates": [e.latlng.lng, e.latlng.lat],
                                                "radius": distance + "m"
                                            }
                                        }
                                    }
                                }, {"missing": {"field": "gc2_version_end_date"}}]
                            }
                        };
                    }
                    qJson = {
                        "query": {
                            "filtered": {
                                "query": {"match_all": {}},
                                "filter": sFilter
                            }
                        }
                    };
                    mouseOverLayer.addLayer(new geocloud.elasticStore({
                        db: db,
                        index: schema + "/" + v.split(".")[1],
                        size: 100,
                        clickable: false,
                        styleMap: mouseOverStyle,
                        q: JSON.stringify(qJson),
                        onEachFeature: function (feature, layer) {
                            var html = "<table>", fieldConf = $.parseJSON(metaDataKeys[v.split(".")[1]].fieldconf), show = false;
                            $.each(fieldConf, function (i, v) {
                                if (v.type !== "geometry" && v.mouseover === true) {
                                    show = true
                                    html = html + "<tr><td>" + (v.alias || v.column) + ":</td><td>" + feature.properties[i] + "</td></tr>";
                                }
                            });
                            html = html + "</table>";
                            if (show) {
                                mouseOverPopUp = L.popup({
                                    offset: L.point(0, -25),
                                    className: "custom-popup",
                                    autoPan: false,
                                    closeButton: false
                                }).setLatLng(e.latlng)
                                    .setContent(html)
                                    .openOn(cloud.map);
                            }
                        },
                        pointToLayer: function (feature, latlng) {
                            return L.circleMarker(latlng, {clickable: false});
                        },
                    }).load());
                }
            });
        }, 200);
        cloud.on("mousemove", mouseOverDisplay);
        // Search begin
        var searchLayers = [], searchStyle = {
            color: '#ff0000',
            fillColor: '#ff0000',
            fillOpacity: 0.5,
            opacity: 0.5
        }, highlighter = function (value, item) {
            _($.trim(value).split(' ')).each(
                function (s) {
                    var regex = new RegExp('(\\b' + s + ')', 'gi');
                    item = item.replace(regex, "<i class=\"highlighted\">$1</i>");
                }
            );
            return item;
        }, search = function (query) {
            var num = 0, singleLayer, layerArr, more = [];

            if (query.split(":").length > 1) {
                singleLayer = query.split(":")[0];
                try {
                    singleLayer = metaDataKeysTitle[singleLayer].f_table_schema + "." + metaDataKeysTitle[singleLayer].f_table_name;
                } catch (e) {
                    return;
                }
                query = query.split(":")[1];
            }
            if (!query || query === "") {
                return false;
            }
            // Trim and delete multiple spaces
            query = query.toLowerCase().replace(/\s\s+/g, ' ').trim();

            layerArr = singleLayer ? [singleLayer] : indexedLayers;
            (function iter() {
                var v = layerArr[num], metaKeys = metaDataKeys[v.split(".")[1]], fieldConf = $.parseJSON(metaKeys.fieldconf), fields = [], names = [], q, terms = [], sFilter, queryStr, urlQ = encodeURIComponent(query), qFields = [],
                    mapping = $.parseJSON(metaDataKeys[v.split(".")[1]].es_mapping), isPoint;
                if (typeof mapping[db + "_" + metaKeys.f_table_schema].mappings[metaKeys.es_type_name].properties.geometry.type === "undefined") {
                    isPoint = true;
                } else {
                    isPoint = false;
                }
                if (num === 0) {
                    // Clear all layers
                    for (var key in searchLayers) {
                        if (searchLayers.hasOwnProperty(key)) {
                            searchLayers[key].clearLayers();
                        }
                    }
                    $("#search-list").empty();
                }
                if (1 === 2) {
                    // Pass
                } else {
                    $.each(fieldConf || [], function (i, v) {
                        if (v.type !== "geometry" && v.searchable === true) {
                            fields.push(v.column);
                        }
                    });
                    var qJson = {
                        "query": {
                            "filtered": {
                                "query": {},
                                "filter": {
                                    "bool": {
                                        "must": [{"missing": {"field": "gc2_version_end_date"}}]
                                    }
                                }
                            }
                        }
                    };
                    if (isPoint) {
                        sFilter = {
                            "geo_bounding_box": {
                                "coordinates": {
                                    "top_left": {
                                        "lat": cloud.getExtent().top,
                                        "lon": cloud.getExtent().left
                                    },
                                    "bottom_right": {
                                        "lat": cloud.getExtent().bottom,
                                        "lon": cloud.getExtent().right
                                    }
                                }
                            }
                        };
                    } else {
                        sFilter = {
                            "geo_shape": {
                                "geometry": {
                                    "shape": {
                                        "type": "envelope",
                                        "coordinates": [[cloud.getExtent().left, cloud.getExtent().top], [cloud.getExtent().right, cloud.getExtent().bottom]]
                                    }
                                }
                            }
                        };
                    }

                    // Create terms and fields
                    var med = {"bool": {"must": []}};
                    $.each(query.split(" "), function (x, n) {
                        var type;
                        if (!isNaN(num) && Number(n) && n % 1 === 0) {
                            type = "int";

                        }
                        else if (!isNaN(num) && Number(n) && n % 1 !== 0) {
                            type = "float";
                        }
                        else {
                            type = "str";
                        }
                        $.each(fields, function (i, v) {
                            if ((fieldConf[v].type === "int" && type === "int") || (fieldConf[v].type === "decimal (3 10)" && (type === "float" || type === "int")) || fieldConf[v].type === "string") {
                                var a = v, b = {};
                                b[a] = n;
                                terms.push({
                                    "term": b
                                })
                                qFields.push(v)
                            }

                        });
                        med.bool.must.push({"bool": {"should": terms}});
                        terms = []
                    });

                    // Create query_string
                    queryStr = {
                        "fields": qFields,
                        "query": encodeURIComponent(query),
                        "default_operator": "AND"
                    };

                    if (1 === 1) { // Using terms
                        qJson.query.filtered.query = {"bool": {"should": med}};
                    } else {
                        qJson.query.filtered.query = {"query_string": queryStr};
                    }

                    if ($("#inside-view-input").is(":checked")) {
                        qJson.query.filtered.filter.bool.must.push(sFilter);
                    }
                    q = JSON.stringify(qJson);

                    searchLayers[v].addLayer(new geocloud.elasticStore({
                        db: db,
                        index: schema + "/" + v.split(".")[1],
                        size: med.bool.must[0].bool.should.length === 0 ? 0 : 100,
                        clickable: false,
                        styleMap: searchStyle,
                        jsonp: false,
                        dataType: "json",
                        method: "POST",
                        q: q,
                        onEachFeature: function (feature, layer) {
                        },
                        pointToLayer: function (feature, latlng) {
                            return L.circleMarker(latlng, {clickable: false});
                        },
                        onLoad: function (response) {
                            if (typeof response.responseJSON.error === "undefined") {
                                var count = response.responseJSON.hits.hits.length, title = metaDataKeys[v.split(".")[1]].f_table_title || metaDataKeys[v.split(".")[1]].f_table_name, html = "", fidKey = metaDataKeys[v.split(".")[1]].pkey,
                                    header = "<h4>" + title + " (" + this.total + ")</h4>",
                                    table = metaDataKeys[v.split(".")[1]].f_table_schema + "." + metaDataKeys[v.split(".")[1]].f_table_name;
                                $.each(response.responseJSON.hits.hits, function (i, hit) {
                                    html = html + "<section class='search-list-item'>";
                                    html = html + "<a href='javascript:void(0)' class='list-group-item' data-gc2-sf-title='" + title + "' data-gc2-sf-table='" + table + "' data-gc2-sf-fid='" + hit._source.properties[fidKey] + "'>";
                                    html = html + "<div><table>";
                                    $.each(fieldConf, function (u, v) {
                                        if (v.type !== "geometry" && v.searchable === true) {
                                            html = html + "<tr><td>" + (v.alias || v.column) + ":</td><td>" + highlighter(query, _.unescape(hit._source.properties[v.column])) + "</td></tr>";
                                        }
                                    });
                                    html = html + "</table></div></a></section>";
                                });
                                if (count > 5 && singleLayer === undefined) {
                                    more[table] = true;
                                    html = "<section class='search-list-item more'>";
                                    html = html + "<a href='javascript:void(0)' class='list-group-item' data-gc2-sf-title='" + title + "' data-gc2-sf-table='" + metaDataKeys[v.split(".")[1]].f_table_schema + "." + metaDataKeys[v.split(".")[1]].f_table_name + "'>";
                                    html = html + __("More items from") + " " + title;
                                    html = html + "</table></div></a></section>";
                                } else {
                                    more[table] = false;
                                }
                                if (count > 0) {
                                    $("#search-list").append(header + html);
                                }
                            }
                            num = num + 1
                            if (layerArr.length === num) {
                                $('a.list-group-item').on("click", function (e) {
                                    var clickedTable = $(this).data('gc2-sf-table'), clickedTitle = $(this).data('gc2-sf-title'), newQuery;
                                    $(".list-group-item").addClass("unselected");
                                    $(this).removeClass("unselected");
                                    $(this).addClass("selected");
                                    if (more[clickedTable]) {
                                        newQuery = clickedTitle + ":" + query;
                                        $("input[name=custom-search]").val(newQuery);
                                        search(newQuery)
                                    }
                                    else {
                                        var id = $(this).data('gc2-sf-fid');
                                        $.each(searchLayers[clickedTable].getLayers(), function (i, v) {
                                            v.eachLayer(function (l) {
                                                if (l.feature.properties[fidKey] === id) {
                                                    cloud.map.off("moveend", searchByMove);
                                                    $("#update-search-input").prop("checked", false);
                                                    cloud.map.fitBounds(l.getBounds());
                                                }
                                            })
                                        });
                                    }
                                    e.stopPropagation();
                                });
                                return true;
                            }
                            iter();
                        }
                    }).load());
                }
            })();
        };
        // Hide search ribbon if no layers are indexed
        if (indexedLayers.length === 0) {
            $("#search-ribbon").css("display", "none");
            $("#pane").css("right", "0");
        }
        $.each(indexedLayers, function (i, v) {
            searchLayers[v] = new L.layerGroup();
            searchLayers[v].addTo(cloud.map);
        })

        $("input[name=custom-search]").on('input', _.debounce(function (e) {
            search(e.target.value)
        }, 300));
        var searchByMove = function () {
            search($("input[name=custom-search]").val());
        }
        $("#update-search-input").on("click", function (e) {
            if ($(this).is(":checked")) {
                search($("input[name=custom-search]").val());
                cloud.on("moveend", searchByMove);
            } else {
                cloud.map.off("moveend", searchByMove);
            }
        });
        $("#inside-view-input").click(function () {
            search($("input[name=custom-search]").val());
        })
        cloud.on("moveend", searchByMove);
        $("body").keydown(function (e) {
            if (typeof $(e.target)[0].form === "undefined" && searchPanelOpen === false) {
                //searchShow();
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