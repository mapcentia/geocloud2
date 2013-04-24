var MapCentia;
MapCentia = (function () {
    "use strict";
    var hostname, cloud, db, schema, uri, hash, osm, mapQuestOSM, mapQuestAerial, stamenToner, GNORMAL, GHYBRID, GSATELLITE, GTERRAIN, toner, popUpVectors, modalVectors;
    hostname = mygeocloud_host;
    uri = mygeocloud_ol.pathName;
    hash = mygeocloud_ol.urlHash;
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
        cloud.setBaseLayer(str);
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
    var autocomplete = new google.maps.places.Autocomplete(document.getElementById('search-input'),
        {
            //bounds: defaultBounds
            //types: ['establishment']
        }
    );
    google.maps.event.addListener(autocomplete, 'place_changed', function () {
        var place = autocomplete.getPlace();
        var center = new mygeocloud_ol.transformPoint(place.geometry.location.lng(), place.geometry.location.lat(), "EPSG:4326", "EPSG:900913");
        cloud.zoomToPoint(center.x, center.y, 10);
    });

    $(window).load(function () {
        var clickPopUp, clickModal, metaData, metaDataKeys, metaDataKeysTitle, layers, jRes;
        metaDataKeys = [];
        metaDataKeysTitle = [];
        layers = {};
        cloud = new mygeocloud_ol.map({
            el: "map"
        });
        osm = cloud.addOSM();
        mapQuestOSM = cloud.addMapQuestOSM();
        mapQuestAerial = cloud.addMapQuestAerial();
        stamenToner = cloud.addStamenToner();
        GNORMAL = cloud.addGoogleStreets();
        GHYBRID = cloud.addGoogleHybrid();
        GSATELLITE = cloud.addGoogleSatellite();
        GTERRAIN = cloud.addGoogleTerrain();
        setBaseLayer("osm");

        // we add two click controllers for desktop and handheld
        //cloud.map.addControl(clickPopUp = new popUpClickController);
        //cloud.map.addControl(clickModal = new modalClickController);

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
                        tileCached: true,
                        visibility: false,
                        wrapDateLine: false,
                        displayInLayerSwitcher: true,
                        name: response.data[u].f_table_schema + "." + response.data[u].f_table_name
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
                                $("#collapse" + base64name).append('<div class="accordion-inner"><label class="checkbox">' + text + authIcon + '<input type="checkbox" id="' + response.data[u].f_table_name + '" onchange="MapCentia.switchLayer(MapCentia.schema+\'.\'+this.id,this.checked)"></label></div>');
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
                //clickModal.activate();
            },
            exit: function () {
                $('#modal-layers').modal('hide');
                $('#modal-base-layers').modal('hide');
                $('#modal-legend').modal('hide');
                $('#modal-info').modal('hide');
                //clickModal.deactivate();
                //modalVectors.removeAllFeatures();
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
                //clickPopUp.activate();
            },
            exit: function () {
                // We activate the popovers, so the divs becomes visible once before screen resize.
                $("#layers-popover").popover('show');
                $("#base-layers-popover").popover('show');
                $("#legend-popover").popover('show');
                addLegend();
                //clickPopUp.deactivate();
                try {
                    popup.destroy();
                } catch (e) {
                }
                //popUpVectors.removeAllFeatures();
            }
        });
        //Set up the state from the URI
        (function () {
            var name, p, arr, i, hashArr;
            hashArr = hash.replace("#", "").split("/");
            if (hashArr[0]) {
                $(".base-map-button").removeClass("active");
                $("#" + hashArr[0]).addClass("active");
                if (hashArr[1] && hashArr[2] && hashArr[3]) {
                    p = mygeocloud_ol.transformPoint(hashArr[2], hashArr[3], "EPSG:4326", "EPSG:900913");
                    cloud.zoomToPoint(p.x, p.y, hashArr[1]);
                    setBaseLayer(hashArr[0]);
                    if (hashArr[4]) {
                        arr = hashArr[4].split(",");
                        for (i = 0; i < arr.length; i++) {
                            //name = cloud.getLayersByName(arr[i]).a.id;
                            switchLayer(arr[i], true);
                            $("#" + arr[i].replace(schema + ".", "")).attr('checked', true);
                        }
                    }
                }
            }
            else {
                cloud.zoomToExtent()
            }
        })();
        var moveEndCallBack =function () {
            var p;
            p = mygeocloud_ol.transformPoint(cloud.getCenter().x, cloud.getCenter().y, "EPSG:900913", "EPSG:4326");
            history.pushState(null, null, "/apps/viewer/" + db + "/" + schema + "/?fw=" + mygeocloud_ol.MAPLIB + "#" + cloud.getBaseLayerName() + "/" + Math.round(cloud.getZoom()).toString() + "/" + (Math.round(p.x * 10000) / 10000).toString() + "/" + (Math.round(p.y * 10000) / 10000).toString() + "/" + cloud.getNamesOfVisibleLayers());
        }
        cloud.on("dragend", moveEndCallBack);
        cloud.on("moveend", moveEndCallBack);
        var clicktimer;
        cloud.on("dblclick", function (e) {
            clicktimer = undefined;
        });
        cloud.on("click", function (e) {
            var event = new mygeocloud_ol.clickEvent(e,cloud);
            if (clicktimer) {
                clearTimeout(clicktimer);
            }
            else clicktimer = setTimeout(function (e) {
                clicktimer = undefined;
                var coords = event.getCoordinate();
                try {
                    popup.destroy();
                } catch (e) {
                }
                $.ajax({
                    dataType: 'jsonp',
                    data: 'resproj=4326&proj=900913&lon=' + coords.x + '&lat=' + coords.y + '&layers=' + cloud.getVisibleLayers() + '&extent=' + "1,2,3,4" + '&width=' + '10' + '&height=' + '10',
                    jsonp: 'jsonp_callback',
                    url: hostname + '/apps/viewer/servers/query/' + db,
                    success: function (response) {
                        //waitPopup.destroy();
                        var arr = [];
                        if (response.html !== false && response.html !== "") {
                            $("#modal-info .modal-body").html(response.html);
                            $('#modal-info').modal('show');
                            for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                                arr.push(response.renderGeometryArray[i][0]);
                            }
                            cloud.addLayerFromWkt(arr);

                        } else {
                            $("#alert").fadeIn(400).delay(1000).fadeOut(400);
                            cloud.removeQueryLayers();
                        }
                    }
                });
            }, 250);
        })
    });
    return{
        switchLayer: switchLayer,
        setBaseLayer: setBaseLayer,
        schema: schema
    }
})();