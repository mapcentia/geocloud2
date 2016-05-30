gc2map.createSearch = function (me) {
    $("#custom-search-form").show();
    var autocomplete = new google.maps.places.Autocomplete(document.getElementById('custom-search'));
    google.maps.event.addListener(autocomplete, 'place_changed', function () {
        var place = autocomplete.getPlace(),
            json = {"type": "Point", "coordinates": [place.geometry.location.lng(), place.geometry.location.lat()]},
        myLayer = L.geoJson();
        myLayer.addData({
            "type": "Feature",
            "properties": {

            },
            "geometry": json
        });
        me.drawnItems.addLayer(myLayer);
    });
};


