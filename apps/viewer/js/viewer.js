var MapCentia = (function () {
    var hostname, cloud, db, schema, uri, osm, mapQuestOSM, mapQuestAerial, GNORMAL, GHYBRID, GSATELLITE, GTERRAIN, toner, popUpVectors, modalVectors;
    hostname = mygeocloud_host;
    uri = mygeocloud_ol.pathName;
    db = uri[3];
    schema = uri[4];
    var switchLayer = function (name, visible) {
        (visible) ? cloud.showLayer(name) : cloud.hideLayer(name);
        try {
            popup.destroy();
        } catch (e) {
        }
        addLegend();
    };
    var setBaseLayer = function (str) {
        var id = eval(str); //Evil
        cloud.setBaseLayer(id);
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
    var popUpClickController = OpenLayers.Class(OpenLayers.Control, {
        defaultHandlerOptions: {
            'single': true,
            'double': false,
            'pixelTolerance': 0,
            'stopSingle': false,
            'stopDouble': false
        },
        initialize: function (options) {
            popUpVectors = new OpenLayers.Layer.Vector("Mark", {
                displayInLayerSwitcher: false
            });
            cloud.map.addLayers([popUpVectors]);
            this.handlerOptions = OpenLayers.Util.extend({}, this.defaultHandlerOptions);
            OpenLayers.Control.prototype.initialize.apply(this, arguments);
            this.handler = new OpenLayers.Handler.Click(this, {
                'click': this.trigger
            }, this.handlerOptions);
        },
        trigger: function (e) {
            var coords = this.map.getLonLatFromViewPortPx(e.xy);
            //var waitPopup = new OpenLayers.Popup("wait", coords, new OpenLayers.Size(36, 36), "<div style='z-index:1000;'><img src='assets/spinner/spinner.gif'></div>", null, true);
            //cloud.map.addPopup(waitPopup);
            try {
                popup.destroy();
            } catch (e) {
            }
            var mapBounds = this.map.getExtent();
            var boundsArr = mapBounds.toArray();
            var boundsStr = boundsArr.join(",");
            var mapSize = this.map.getSize();
            var popupTemplate = '<div style="position:relative;"><div></div><div id="queryResult" style="display: table"></div><button onclick="popup.destroy()" style="position:absolute; top: -10px; right: 5px" type="button" class="close" aria-hidden="true">×</button></div>';
            $.ajax({
                dataType: 'jsonp',
                data: 'proj=900913&lon=' + coords.lon + '&lat=' + coords.lat + '&layers=' + cloud.getVisibleLayers() + '&extent=' + boundsStr + '&width=' + mapSize.w + '&height=' + mapSize.h,
                jsonp: 'jsonp_callback',
                url: hostname + '/apps/viewer/servers/query/' + db,
                success: function (response) {
                    //waitPopup.destroy();
                    var anchor = new OpenLayers.LonLat(coords.lon, coords.lat);
                    popup = new OpenLayers.Popup.Anchored("result", anchor, new OpenLayers.Size(100, 150), popupTemplate, null, false, null);
                    popup.panMapIfOutOfView = true;
                    if (response.html !== false && response.html!=="") {
                        // Dirty hack! We have to add the popup measure the width, destroy it and add it again width the right width.
                        cloud.map.addPopup(popup);
                        $("#queryResult").html(response.html);
                        var width = $("#queryResult").width()+35;
                        popup.destroy();
                        popup = new OpenLayers.Popup.Anchored("result", anchor, new OpenLayers.Size(width, 150), popupTemplate, null, false, null);
                        cloud.map.addPopup(popup);
                        $("#queryResult").html(response.html);
                        //popup.relativePosition="tr";
                        popUpVectors.removeAllFeatures();
                        cloud.map.raiseLayer(popUpVectors, 10);
                        for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                            popUpVectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));
                        }
                    } else {
                        $("#alert").fadeIn(400).delay(1000).fadeOut(400);
                    }
                }
            });
        }
    });
    var modalClickController = OpenLayers.Class(OpenLayers.Control, {
        defaultHandlerOptions: {
            'single': true,
            'double': false,
            'pixelTolerance': 0,
            'stopSingle': false,
            'stopDouble': false
        },
        initialize: function (options) {
            modalVectors = new OpenLayers.Layer.Vector("Mark", {
                displayInLayerSwitcher: false
            });
            cloud.map.addLayers([modalVectors]);
            this.handlerOptions = OpenLayers.Util.extend({}, this.defaultHandlerOptions);
            OpenLayers.Control.prototype.initialize.apply(this, arguments);
            this.handler = new OpenLayers.Handler.Click(this, {
                'click': this.trigger
            }, this.handlerOptions);
        },
        trigger: function (e) {
            var coords = this.map.getLonLatFromViewPortPx(e.xy);
            var waitPopup = new OpenLayers.Popup("wait", coords, new OpenLayers.Size(36, 36), "<div style='z-index:1000;'><img src='assets/spinner/spinner.gif'></div>", null, true);
            cloud.map.addPopup(waitPopup);
            try {
                popup.destroy();
            } catch (e) {
            }
            var mapBounds = this.map.getExtent();
            var boundsArr = mapBounds.toArray();
            var boundsStr = boundsArr.join(",");
            var mapSize = this.map.getSize();
            $.ajax({
                dataType: 'jsonp',
                data: 'proj=900913&lon=' + coords.lon + '&lat=' + coords.lat + '&layers=' + cloud.getVisibleLayers() + '&extent=' + boundsStr + '&width=' + mapSize.w + '&height=' + mapSize.h,
                jsonp: 'jsonp_callback',
                url: hostname + '/apps/viewer/servers/query/' + db,
                success: function (response) {
                    waitPopup.destroy();
                    if (response.html !== false && response.html!=="") {
                        $("#modal-info .modal-body").html(response.html);
                        $('#modal-info').modal('show');
                        modalVectors.removeAllFeatures();
                        cloud.map.raiseLayer(modalVectors, 10);
                        for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                            modalVectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));
                        }
                    } else {
                        $("#alert").fadeIn(400).delay(1000).fadeOut(400);
                    }
                }
            });
        }
    });
    var deserialize = function (element) {
        var format = new OpenLayers.Format.WKT;
        return format.read(element);
    };
    var autocomplete = new google.maps.places.Autocomplete(document.getElementById('search-input'),
        {
            //bounds: defaultBounds
            //types: ['establishment']
        }
    );
    google.maps.event.addListener(autocomplete, 'place_changed', function () {
        var place = autocomplete.getPlace();
        var p = transformPoint(place.geometry.location.lng(), place.geometry.location.lat(), "EPSG:4326", "EPSG:900913")
        var point = new OpenLayers.LonLat(p.x, p.y);
        cloud.map.setCenter(point, 15);
        try {
            placeMarkers.destroy()
        } catch (e) {
        }
        var placeMarkers = new OpenLayers.Layer.Markers("Markers");
        cloud.map.addLayer(placeMarkers);
        var size = new OpenLayers.Size(21, 25);
        var offset = new OpenLayers.Pixel(-(size.w / 2), -size.h);
        var icon = new OpenLayers.Icon('http://www.openlayers.org/dev/img/marker.png', size, offset);
        placeMarkers.addMarker(new OpenLayers.Marker(point, icon));
        placeMarkers.addMarker(new OpenLayers.Marker(point, icon.clone()));
    });
    var transformPoint = function (lat, lon, s, d) {
        var source = new Proj4js.Proj(s);    //source coordinates will be in Longitude/Latitude
        var dest = new Proj4js.Proj(d);
        var p = new Proj4js.Point(lat, lon);
        Proj4js.transform(source, dest, p);
        return p;
    };
    $(window).load(function () {
        var clickPopUp, clickModal, metaData, metaDataKeys, metaDataKeysTitle, layers, jRes;
        metaDataKeys = [];
        metaDataKeysTitle = [];
        layers = {};
        cloud = new mygeocloud_ol.map({
            el: "map",
            db: db,
            projection: "EPSG:3857"
        });
        osm = cloud.addOSM();
        mapQuestOSM = cloud.addMapQuestOSM();
        mapQuestAerial = cloud.addMapQuestAerial();
        GNORMAL = cloud.addGoogleStreets();
        GHYBRID = cloud.addGoogleHybrid();
        GSATELLITE = cloud.addGoogleSatellite();
        GTERRAIN = cloud.addGoogleTerrain();
        cloud.map.addLayer(toner = new OpenLayers.Layer.Stamen("toner"));
        cloud.setBaseLayer(osm);

        // we add two click controllers for desktop and handheld
        cloud.map.addControl(clickPopUp = new popUpClickController);
        cloud.map.addControl(clickModal = new modalClickController);

        $("#locate-btn").on("click", function () {
            cloud.locate();
        });
        $.ajax({
            url: hostname + '/controller/geometry_columns/' + db + '/getall/' + schema,
            async: false,
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            success: function (response) {
                var base64name, authIcon, isBaseLayer, arr, groups;
                groups = [];
                metaData = response;
                for (var i = 0; i < metaData.data.length; i++) {
                    metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                    if (!metaData.data[i].f_table_title) {
                        metaData.data[i].f_table_title = metaData.data[i].f_table_name;
                    }
                    metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i];
                }
                for (var i = 0; i < response.data.length; ++i) {
                    groups[i] = response.data[i].layergroup;
                }
                arr = array_unique(groups);
                for (var u = 0; u < response.data.length; ++u) {
                    isBaseLayer = (response.data[u].baselayer) ? true : false;
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
                                $("#collapse" + base64name).append('<div class="accordion-inner"><label class="checkbox">' + text + authIcon + '<input type="checkbox" id="' + response.data[u].f_table_name + '" onchange="MapCentia.switchLayer(this.id,this.checked)"></label></div>');
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
        //Set up the state from the URI
        (function () {
            var name, p, arr, i;
            if (uri[5]) {
                setBaseLayer(uri[5]);
                if (uri[6] && uri[7] && uri[8]) {
                    p = transformPoint(uri[7], uri[8], "EPSG:4326", "EPSG:900913");
                    cloud.zoomToPoint(p.x, p.y, uri[6]);
                    if (uri[9]) {
                        arr = uri[9].split(",");
                        for (i = 0; i < arr.length; i++) {
                            name = cloud.getLayerById(schema + "." + arr[i]).name;
                            switchLayer(name, true);
                            $("#" + name).attr('checked', true);
                        }
                    }
                }
                else {
                    cloud.zoomToExtent()
                }
            }
        })();
        cloud.map.events.register("moveend", null, function () {
            var p;
            p = transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
            history.pushState(null, null, "/apps/viewer/" + db + "/" + schema + "/" + cloud.getBaseLayerName() + "/" + cloud.getZoom() + "/" + (Math.round(p.x * 10000) / 10000).toString() + "/" + (Math.round(p.y * 10000) / 10000).toString() + "/" + cloud.getNamesOfVisibleLayers());
        });
    });
    return{
        switchLayer: switchLayer,
        setBaseLayer: setBaseLayer
    }
})();