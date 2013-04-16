var popUpVectors,modalVectors;
var popupTemplate = '<div style="position:relative"><div></div><div id="queryResult"></div><button onclick="popup.destroy()" style="position:absolute; top:5px; right: 5px" type="button" class="close" aria-hidden="true">×</button></div>';
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
                var anchor = new OpenLayers.LonLat(coords.lon, coords.lat);
                popup = new OpenLayers.Popup.Anchored("result", anchor, new OpenLayers.Size(200, 200), popupTemplate, null, false, null);
                popup.panMapIfOutOfView = true;
                cloud.map.addPopup(popup);
                if (response.html !== false) {
                    document.getElementById("queryResult").innerHTML = response.html;
                    //popup.relativePosition="tr";
                    popUpVectors.removeAllFeatures();
                    cloud.map.raiseLayer(popUpVectors, 10);
                    for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                        popUpVectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));
                    }
                } else {
                    document.getElementById("queryResult").innerHTML = "Found nothing";
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
                if (response.html !== false) {
                    $("#modal-info .modal-body").html(response.html);
                    $('#modal-info').modal('show');
                    modalVectors.removeAllFeatures();
                    cloud.map.raiseLayer(modalVectors, 10);
                    for (var i = 0; i < response.renderGeometryArray.length; ++i) {
                        modalVectors.addFeatures(deserialize(response.renderGeometryArray[i][0]));
                    }
                } else {
                    document.getElementById("queryResult").innerHTML = "Found nothing";
                }
            }
        });
    }
});
var deserialize = function (element) {
    // console.log(element);
    var type = "wkt";
    var format = new OpenLayers.Format.WKT;
    return format.read(element);
};