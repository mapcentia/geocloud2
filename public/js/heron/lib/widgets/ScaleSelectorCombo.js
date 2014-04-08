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

Ext.namespace("Heron.widgets");

/** api: (define)
 *  module = Heron.widgets
 *  class = ScaleSelectorCombo
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.form.ComboBox>`_
 */


/** api: example
 *  Sample code showing how to show a scale combobox in your MapPanel toolbar.
 *
 *  .. code-block:: javascript
 *
 *			Heron.layout = {
 *			 	xtype: 'hr_mappanel',
 *
 *			 	hropts: {
 *					 layers: [
 *						 new OpenLayers.Layer.WMS( "World Map",
 *						   "http://tilecache.osgeo.org/wms-c/Basic.py?", {layers: 'basic', format: 'image/png' } )
 *					 ],
 *					toolbar : [
 *						{type: "scale"}, 
 *						{type: "-"},
 *						{type: "pan"},
 *						{type: "zoomin"},
 *						{type: "zoomout"}
 *					]
 *				  }
 *				};
 *
 */
Heron.widgets.ScaleSelectorCombo = Ext.extend(Ext.form.ComboBox, {

    /** api: config[map]
     *  ``OpenLayers.Map or Object``  A configured map or a configuration object
     *  for the map constructor, required only if :attr:`zoom` is set to
     *  value greater than or equal to 0.
     */
    /** private: property[map]
     *  ``OpenLayers.Map``  The map object.
     */
    map: null,

    /** private: property[tpl]
     *  ``Ext.XTemplate``  Template of the combo box content
     */
    // tpl: '<tpl for="."><div class="x-combo-list-item">1 : {[values.formattedScale]} </div></tpl>',
    tpl: '<tpl for="."><div class="x-combo-list-item">1 : {[parseInt(values.scale + 0.5)]}</div></tpl>',

    /** private: property[editable]
     * Default: false
     */
    editable: false,

	/** api: config[width]
	 *  See http://www.dev.sencha.com/deploy/dev/docs/source/BoxComponent.html#cfg-Ext.BoxComponent-width,
	 *  default value is 240.
	 */
    width: 130,

	/** api: config[listWidth]
	 *  See http://www.dev.sencha.com/deploy/dev/docs/source/BoxComponent.html#cfg-Ext.BoxComponent-listWidth,
	 *  default value is 120.
	 */
    listWidth: 120,

	/** api: config[emptyText]
	 *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
	 *  default value is "Search location in Geozet".
	 */
    emptyText: __('Scale'),

	/** api: config[emptyText]
	 *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
	 *  default value is "Search location in Geozet".
	 */
    tooltip: __('Scale'),

    /** private: property[triggerAction]
     * Needed so that the combo box doesn't filter by its current content. Default: all
     */
    triggerAction: 'all',

    /** private: property[mode]
     * keep the combo box from forcing a lot of unneeded data refreshes. Default: local
     */
    mode: 'local',

    /** api: config[thousandSeparator]
     *  The thousand separator string for the scale. Default: '
     */
    /** private: property[thousandSeparator]
     *  Thousand separator
     */
    // thousandSeparator: '\'',

    /** api: config[decimalNumber]
     *  Number of decimal number for the scale. Default: 0
     */
    /** private: property[decimalNumber]
     *  Number of decimal number for the scale. Default: 0
     */
    // decimalNumber: 0,

    /** api: config[fakeScaleValue]
     *  Array of fake scale value
     */
    /** private: property[fakeScaleValue]
     *  Array of fake scale value
     */
    // fakeScaleValue: null,

    /** private: constructor
     */
    initComponent: function() {
    
        Heron.widgets.ScaleSelectorCombo.superclass.initComponent.apply(this, arguments);

        this.store = new GeoExt.data.ScaleStore({map: this.map});

        /*
        if (this.getLocalDecimalSeparator() == this.thousandSeparator) {
          this.thousandSeparator = '\'';
        }
        for (var i = 0; i < this.store.getCount(); i++) {
            if (this.fakeScaleValue) {
                this.store.getAt(i).data.formattedScale = this.addThousandSeparator(this.roundNumber(this.fakeScaleValue[i], this.decimalNumber), this.thousandSeparator);
            } else {
                this.store.getAt(i).data.formattedScale = this.addThousandSeparator(this.roundNumber(this.store.getAt(i).data.scale, this.decimalNumber), this.thousandSeparator);
            }
        }
		*/
		
        for (var i = 0; i < this.store.getCount(); i++) {
          this.store.getAt(i).data.formattedScale = parseInt(this.store.getAt(i).data.scale + 0.5);
        }
		
        this.on('select',
                function(combo, record, index) {
                    this.map.zoomTo(record.data.level);
                },
                this
                );

        this.map.events.register('zoomend', this, this.zoomendUpdate);
        this.map.events.triggerEvent("zoomend");
    },

    /** method[listeners]
     *  Show qtip
     */
    listeners: {
		render: function(c){
        	c.el.set({qtip: this.tooltip});
        	c.trigger.set({qtip: this.tooltip});
    	}
	},

    /** method[zoomendUpdate]
     *  Method reacting to the map zoomend event in order to update the combo
     *  :param record: the selected record
     */
    zoomendUpdate: function(record) {
        var scale = this.store.queryBy(function(record) {
            return this.map.getZoom() == record.data.level;
        });
        if (scale.length > 0) {
            scale = scale.items[0];
            // this.setValue("1 : " + scale.data.formattedScale);
            this.setValue("1 : " + parseInt(scale.data.scale + 0.5));
        } else {
            if (!this.rendered) {
                return;
            }
            this.clearValue();
        }
    },

    /** method[addThousandSeparator]
     *  Add the thousand separator to a string
     *  :param value: ``Number`` or ``String`` input value
     *  :param separator: ``String`` thousand separator
     */
    /* 
    addThousandSeparator: function(value, separator) {
        if (separator === null) {
            return value;
        }
        value = value.toString();
        var sRegExp = new RegExp('(-?[0-9]+)([0-9]{3})');
        while (sRegExp.test(value)) {
            value = value.replace(sRegExp, '$1' + separator + '$2');
        }
        // Remove the thousand separator after decimal separator
        if (this.decimalNumber > 3) {
            var decimalPosition = value.lastIndexOf(this.getLocalDecimalSeparator());
            if (decimalPosition > 0) {
                var postDecimalCharacter = value.substr(decimalPosition);
                value = value.substr(0, decimalPosition) + postDecimalCharacter.replace(separator, '');
            }
        }
        return value;
    },
    */

    /** method[roundNumber]
     *  Round number with decimals
     *  :param value: ``Number`` input number
     *  :param decimals: ``Number`` number of decimals place
     */
    /*
    roundNumber: function(value, decimals) {
        return Math.round(value * Math.pow(10, decimals)) / Math.pow(10, decimals);
    },
    */

    /** method[getLocalDecimalSeparator]
     *  Get the local decimal separator
     */
    /* 
    getLocalDecimalSeparator: function() {
        var n = 1.1;
        return n.toLocaleString().substring(1, 2);
    },
    */

    /** private: method[beforeDestroy]
     *
     */
    beforeDestroy: function() {
        this.map.events.unregister('zoomend', this, this.zoomendUpdate);
        Heron.widgets.ScaleSelectorCombo.superclass.beforeDestroy.apply(this, arguments);
    }
});

/** api: xtype = hr_scaleselectorcombo */
Ext.reg('hr_scaleselectorcombo', Heron.widgets.ScaleSelectorCombo);
