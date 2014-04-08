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

Ext.namespace("Heron.data");

/** api: (define)
 *  module = Heron.data
 *  class = OpenLS_XLSReader
 *  base_link = `Ext.data.XmlReader <http://dev.sencha.com/deploy/ext-3.3.1/docs?class=Ext.data.XmlReader>`_
 */

Heron.data.OpenLS_XLSReader = function(meta, recordType) {
	meta = meta || {};


	Ext.applyIf(meta, {
				idProperty: meta.idProperty || meta.idPath || meta.id,
				successProperty: meta.successProperty || meta.success
			});

	Heron.data.OpenLS_XLSReader.superclass.constructor.call(this, meta, recordType || meta.fields);
};

Ext.extend(Heron.data.OpenLS_XLSReader, Ext.data.XmlReader, {

	addOptXlsText: function(format, text, node, tagname, sep) {
		var elms = format.getElementsByTagNameNS(node, "http://www.opengis.net/xls", tagname);
		if (elms) {
			Ext.each(elms, function(elm, index) {
				var str = format.getChildValue(elm);
				if (str) {
					text = text + sep + str;
				}
			});
		}

		return text;
	},

	readRecords : function(doc) {

		this.xmlData = doc;

		var root = doc.documentElement || doc;

		var records = this.extractData(root);

		return {
			success : true,
			records : records,
			totalRecords : records.length
		};
	},

	extractData: function(root) {
		var opts = {
			/**
			 * Property: namespaces
			 * {Object} Mapping of namespace aliases to namespace URIs.
			 */
			namespaces: {
				gml: "http://www.opengis.net/gml",
				xls: "http://www.opengis.net/xls"
			}
		};

		var records = [];
		var format = new OpenLayers.Format.XML(opts);
		var addresses = format.getElementsByTagNameNS(root, "http://www.opengis.net/xls", 'GeocodedAddress');

		// Create record for each address
		var recordType = Ext.data.Record.create([
			{name: "lon", type: "number"},
			{name: "lat", type: "number"},
			"text"
		]);
		var reader = this;

		Ext.each(addresses, function(address, index) {
			var pos = format.getElementsByTagNameNS(address, "http://www.opengis.net/gml", 'pos');
			var xy = '';
			if (pos && pos[0]) {
				xy = format.getChildValue(pos[0]);
			}

			var xyArr = xy.split(' ');

			var text = '';

			/**
			 *		 <xls:GeocodedAddress>
			 <gml:Point srsName="EPSG:28992">
			 <gml:pos dimension="2">121684.0 487802.0</gml:pos>
			 </gml:Point>
			 <xls:Address countryCode="NL">
			 <xls:StreetAddress>
			 <xls:Street>Damrak</xls:Street>
			 </xls:StreetAddress>
			 <xls:Place type="MunicipalitySubdivision">AMSTERDAM</xls:Place>
			 <xls:Place type="Municipality">AMSTERDAM</xls:Place>
			 <xls:Place type="CountrySubdivision">NOORD-HOLLAND</xls:Place>
			 </xls:Address>
			 </xls:GeocodedAddress>
			 *
			 */
			text = reader.addOptXlsText(format, text, address, 'Street', '');
			text = reader.addOptXlsText(format, text, address, 'Place', ',');
			var values = {
				lon : parseFloat(xyArr[0]),
				lat : parseFloat(xyArr[1]),
				text : text
			};
			var record = new recordType(values, index);
			records.push(record);
		});
		return records;
	}
});

