createSearch = function (me) {
    var autocomplete = new google.maps.places.Autocomplete(document.getElementById('custom-search'));
    google.maps.event.addListener(autocomplete, 'place_changed', function () {
        var place = autocomplete.getPlace(),
            json = {"type": "Point", "coordinates": [place.geometry.location.lng(), place.geometry.location.lat()]},
        myLayer = L.geoJson();
        me.clearDrawItems();
        me.clearInfoItems();
        myLayer.addData({
            "type": "Feature",
            "properties": {

            },
            "geometry": json
        });
        me.drawnItems.addLayer(myLayer);
        me.makeConflict({geometry: json}, 0, true, $("#custom-search").val());
    });
};
module.exports = createSearch;


