/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Modified 16.nov.13 by Cesar Basurto (cesarbasurto7@gmail.com) to add zooming to bounding box returned by Nominatim
 */
Ext.namespace("Heron.widgets.search");

/** api: (define)
 *  module = Heron.widgets.search
 *  class = NominatimSearchCombo
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.form.ComboBox>`_
 */


/** api: example
 *  Sample code showing how to include Nominatim search in your MapPanel toolbar, not URL to restrict search in e.g. country.
 *
 *  .. code-block:: javascript
 *
 *            Heron.layout = {
 *			 	xtype: 'hr_mappanel',
 *
 *			 	hropts: {
 *					 layers: [
 *						 new OpenLayers.Layer.WMS( "World Map",
 *						   "http://tilecache.osgeo.org/wms-c/Basic.py?", {layers: 'basic', format: 'image/png' } )
 *					 ],
 *					toolbar : [
 *						{type: "pan"},
 *						{type: "zoomin"},
 *						{type: "zoomout"},
 *						{type: "-"},
 *						{type: "search_nominatim",
 *							options : {
 *							    url: 'http://open.mapquestapi.com/nominatim/v1/search?countrycodes=CO&format=json',
 *							}}
 *					]
 *				  }
 *				};
 *
 */

/** api: constructor
 *  .. class:: NominatimSearchCombo(config)
 *
 *  Create a ComboBox that provides a "search and zoom" function using OpenStreetMap Nominatim search.
 *  To use this class you need to include additional JS files in your page.
 *  See also the example HTML file under examples/namesearch.
 *
 *  #. If your map is not in EPSG:4326 (WGS84) you need to import Proj4JS, e.g.
 *     http://cdnjs.cloudflare.com/ajax/libs/proj4js/1.1.0/proj4js-compressed.js
 *
 *  #. You need a proxy server that should proxy the domain `open.mapquestapi.com`.
 */
Heron.widgets.search.NominatimSearchCombo = Ext.extend(Ext.form.ComboBox, {

    /** api: config[map]
     *  ``OpenLayers.Map or Object``  A configured map or a configuration object
     *  for the map constructor, required only if :attr:`zoom` is set to
     *  value greater than or equal to 0.
     */

    /** private: property[map]
     *  ``OpenLayers.Map``  The map object.
     */
    map: null,

    /** api: config[width]
     *  See http://www.dev.sencha.com/deploy/dev/docs/source/BoxComponent.html#cfg-Ext.BoxComponent-width,
     *  default value is 350.
     */
    width: 240,

    /** api: config[listWidth]
     *  See http://www.dev.sencha.com/deploy/dev/docs/source/Combo.html#cfg-Ext.form.ComboBox-listWidth,
     *  default value is 350.
     */
    listWidth: 400,

    /** api: config[loadingText]
     *  See http://www.dev.sencha.com/deploy/dev/docs/source/Combo.html#cfg-Ext.form.ComboBox-loadingText,
     *  default value is "Search in Nominatim...".
     */
    loadingText: __('Searching...'),

    /** api: config[emptyText]
     *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
     *  default value is "Search location in Nominatim".
     */
    emptyText: __('Search Nominatim'),

    /** api: config[zoom]
     *  ``Number`` Zoom level for recentering the map after search, if set to
     *  a negative number the map isn't recentered, defaults to 8. OBSOLETE, as 'boundingbox' from result is used.
     */
    zoom: 8,

    /** api: config[minChars]
     *  ``Number`` Minimum number of characters to be typed before
     *  search occurs, defaults to 1.
     */
    minChars: 4,

    /** api: config[queryDelay]
     *  ``Number`` Delay before the search occurs, defaults to 50 ms.
     */
    queryDelay: 50,

    /** api: config[maxRows]
     *  `String` The maximum number of rows in the responses, defaults to 20,
     *  maximum allowed value is 1000.
     *  See: http://www.geonames.org/export/geonames-search.html
     */
    maxRows: '10',


    /** config: property[url]
     *  Url of the Nominatim service default: http://open.mapquestapi.com/nominatim/v1/search?format=json
     *  You must have a proxy defined to pass through to the domain like `open.mapquestapi.com`.
     *  Search parameters, see: http://open.mapquestapi.com/nominatim/#search. Has to work in concertwith
     *  storeFields and template (tpl).
     */
    url: 'http://open.mapquestapi.com/nominatim/v1/search?format=json&addressdetails=1',

    storeFields: [
        "place_id"
        ,
        "display_name"
        ,
        {name: "address", type: "Object"},
    /**
     * add in services return the boundingbox
     */
        "boundingbox",
        {name: "lat", type: "number"}
        ,
        {name: "lon", type: "number"}
    ],

    /** api: config[tpl]
     *  ``Ext.XTemplate or String`` Template for presenting the result in the
     *  list (see http://www.dev.sencha.com/deploy/dev/docs/output/Ext.XTemplate.html),
     *  if not set this default value is provided. If null "displayField" is used.
     */
    tpl: '<tpl for="."><tpl for="address"><div class="x-combo-list-item">{road} {postcode} {city} {country}</div></tpl></tpl>',

    /** api: config[displayTpl]
     *  ``Ext.XTemplate or String`` Template for presenting the result in the
     *  field (see http://www.dev.sencha.com/deploy/dev/docs/output/Ext.XTemplate.html),
     *  if not set this default value is provided. If null "displayField" is used.
     */
    displayTpl: '<tpl for="."><tpl for="address">{road} {city} {country}</tpl></tpl>',


    /** api: config[lang]
     *  ``String`` Place name and country name will be returned in the specified
     *  language. Default is English (en). See: http://www.geonames.org/export/geonames-search.html
     */
    /** private: property[lang]
     *  ``String``
     */
    lang: 'en',

    /** api: config[charset]
     *  `String` Defines the encoding used for the document returned by
     *  the web service, defaults to 'UTF8'.
     *  See: http://www.geonames.org/export/geonames-search.html
     */
    /** private: property[charset]
     *  ``String``
     */
    charset: 'UTF8',

    /** private: property[hideTrigger]
     *  Hide trigger of the combo.
     */
    hideTrigger: true,

    /** private: property[displayField]
     *  Display field name. Result field that will be displayed if all templates are not set.
     */
    displayField: 'display_name',

    /** private: property[forceSelection]
     *  Force selection.
     */
    forceSelection: true,

    /** private: property[queryParam]
     *  Query parameter.
     */
    queryParam: 'q',

    /** private: method[constructor]
     *  Construct the component.
     */
    initComponent: function () {
        if (this.displayTpl) {
            this.displayTplObj = new Ext.XTemplate(this.displayTpl);
        }

        Heron.widgets.search.NominatimSearchCombo.superclass.initComponent.apply(this, arguments);
        this.store = new Ext.data.JsonStore({
            proxy: new Ext.data.HttpProxy({
                url: this.url,
                method: 'GET'
            }),
            idProperty: 'place_id',
            successProperty: null,
            totalProperty: null,
            fields: this.storeFields
        });

        // a searchbox for names
        // see http://khaidoan.wikidot.com/extjs-combobox
        if (this.zoom > 0) {
            this.on("select", function (combo, record, index) {
                var result = record.data;

                var value = result[this.displayField];

                if (this.displayTplObj) {
                    value = this.displayTplObj.apply(result);
                }

                this.setValue(value); // put the selected name in the box
                // var position = new OpenLayers.LonLat(result.lon, result.lat);

                /**
                 * Create var for boundingbox
                 */
                var lonlat1 = new OpenLayers.LonLat(result.boundingbox[2], result.boundingbox[0]);
                var lonlat2 = new OpenLayers.LonLat(result.boundingbox[3], result.boundingbox[1]);

                /**
                 *  Reproject (if required)
                 */
                lonlat1.transform(
                        new OpenLayers.Projection("EPSG:4326"),
                        this.map.getProjectionObject()
                );
                lonlat2.transform(
                        new OpenLayers.Projection("EPSG:4326"),
                        this.map.getProjectionObject()
                );
                /**
                 *  Create boundingbox
                 */
                var bounds = new OpenLayers.Bounds();
                bounds.extend(lonlat1);
                bounds.extend(lonlat2);
                /**
                 *  Zoom in Extent of boundingbox
                 */
                this.map.zoomToExtent(bounds);

                // zoom in on the location
                // this.map.setCenter(position, this.zoom);
                // close the drop down list
                this.collapse();
            }, this);
        }
    }
});

/** api: xtype = hr_nominatimsearchcombo */
Ext.reg('hr_nominatimsearchcombo', Heron.widgets.search.NominatimSearchCombo);
