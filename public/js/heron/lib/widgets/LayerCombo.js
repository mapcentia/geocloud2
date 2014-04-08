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
 *  class = LayerCombo
 *  base_link = `Ext.form.ComboBox <http://dev.sencha.com/deploy/ext-3.4.0/docs/?class=Ext.form.ComboBox>`_
 */

/** api: example
 *  Sample code showing how to configure a Heron LayerCombo.
 *  Note the main config layerFilter, a function to select a subset of Layers from the Map.
 *  Default is all Map Layers. When a Layer is selected the 'selectlayer' event is fired.
 *
 *  .. code-block:: javascript

     {
         xtype: "hr_layercombo",
         id: "hr_layercombo",

         layerFilter: function (map) {
             return map.getLayersByClass('OpenLayers.Layer.WMS');
         }
     }

 */

/**
 *
 * A combo box to select a Layer from a Layer set.
 *
 * @constructor
 * @extends Ext.form.ComboBox
 *
 */
Heron.widgets.LayerCombo = Ext.extend(Ext.form.ComboBox, {

    /** api: config[map]
     *  ``OpenLayers.Map or Object``  A configured map or a configuration object
     *  for the map constructor, required only if :attr:`zoom` is set to
     *  value greater than or equal to 0.
     */
    /** private: property[map]
     *  ``OpenLayers.Map``  The map object.
     */
    map: null,

    /** api: config[store]
     *  ``GeoExt.data.LayerStore`` A configured LayerStore
     */
    /** private: property[store]
     *  ``GeoExt.data.LayerStore``  The layer store of the map.
     */
    store: null,

    /** api: config[emptyText]
     *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
     *  default value is "Choose a Base Layer".
     */
    emptyText: __('Choose a Layer'),

    /** api: config[tooltip]
     *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
     *  default value is "Basemaps".
     */
    tooltip: __('Choose a Layer'),

    /** api: config[sortOrder]
     *  ``String``
     *  How should the layer names be sorted in the selector, 'ASC', 'DESC' or null (as Map order)?
     *  default value is 'ASC' (Alphabetically Ascending).
     */
    sortOrder: 'ASC',

    /** api: config[selectFirst]
     *  ``Boolean``
     * Automatically select the first layer? Default false.
     */
    selectFirst: false,

    /** private: property[hideTrigger]
     *  Hide trigger of the combo.
     */
    hideTrigger: false,

    /** private: property[layerFilter]
     *  layerFilter - function that takes subset of all layers, e.g. all visible or baselayers
     */
    layerFilter: function (map) {
        return map.layers;
    },

    /** private: property[displayField]
     *  Display field name
     */
    displayField: 'name',

    /** private: property[forceSelection]
     *  Force selection.
     */
    forceSelection: true,

    /** private: property[triggerAction]
     *  trigger Action
     */
    triggerAction: 'all',

    /** private: property[mode]
     *  mode
     */
    mode: 'local',

    /** private: property[editable]
     *  editable
     */
    editable: false,

    /** private: constructor
     */
    initComponent: function () {

        if (!this.map) {
            this.map = Heron.App.getMap();
        }

        this.store = this.createLayerStore(this.layerFilter(this.map));

        // set the display field
        this.displayField = this.store.fields.keys[1];

        if (this.selectFirst) {
            var record = this.store.getAt(0);
            if (record) {
                this.selectedLayer = record.getLayer();
                this.value = record.get('title');
            }
        }

        if (!this.width) {
            this.width = this.listWidth = 'auto';
        }

        // Nasty hack, but IE does not play nice even when applying resizeToFitContent() below
        if (Ext.isIE && this.listWidth == 'auto') {
            this.listWidth = 160;
        }

        Heron.widgets.LayerCombo.superclass.initComponent.apply(this, arguments);

        // Setup our own events
        this.addEvents({
            'selectlayer': true
        });

        // set an initial value if available (e.g. from subclass
        if (this.initialValue) {
            this.setValue(this.initialValue);
        }

        // The ComboBox select handler, when item  selected
        this.on('select', function (combo, record, idx) {
            //record.getLayer(idx).setVisibility(true);
            this.selectedLayer = record.getLayer(idx);
            this.fireEvent('selectlayer', this.selectedLayer);
        }, this);
    },

    /** method[createLayerStore]
     *  Create and return LayerStore from given Layer array.
     */
    createLayerStore: function (layers) {
        // create layer store with possibly filtered layerset
        return new GeoExt.data.LayerStore({
            layers: layers,
            sortInfo: this.sortOrder ? {
                field: 'title',
                direction: this.sortOrder // or 'DESC' (case sensitive for local sorting)
            } : null
        });
    },

    /** method[setLayers]
     *  Replace all layers in the combo.
     */
    setLayers: function (layers) {
        // create new layer store
        var store = this.createLayerStore(layers);

        // A bit of a hack: call private function to replace store.
        this.bindStore(store, false);
    },

    /** method[resizeToFitContent]
     *
     * Needed to set right innerlist size. Somehow this is not going well, possibly an ExtJS bug.
     * See http://stackoverflow.com/questions/1459221/extjs-ext-combobox-autosize-over-existing-content
     */
    resizeToFitContent: function () {
        if (!this.elMetrics) {
            this.elMetrics = Ext.util.TextMetrics.createInstance(this.getEl());
        }
        var m = this.elMetrics, width = 0, el = this.el, s = this.getSize();
        this.store.each(function (r) {
            var text = r.get(this.displayField);
            width = Math.max(width, m.getWidth(text));
        }, this);
        if (el) {
            width += el.getBorderWidth('lr');
            width += el.getPadding('lr');
        }
        if (this.trigger) {
            width += this.trigger.getWidth();
        }
        s.width = width;
        this.setSize(s);
        this.store.on({
            'datachange': this.resizeToFitContent,
            'add': this.resizeToFitContent,
            'remove': this.resizeToFitContent,
            'load': this.resizeToFitContent,
            'update': this.resizeToFitContent,
            buffer: 10,
            scope: this
        });
    },

    /** method[listeners]
     *  Show qtip
     */
    listeners: {
        render: function (c) {
            c.el.set({qtip: this.tooltip});
            c.trigger.set({qtip: this.tooltip});
            if (this.width == 'auto') {
                c.resizeToFitContent();
            }
        }
    }
});

/** api: xtype = hr_layercombo */
Ext.reg('hr_layercombo', Heron.widgets.LayerCombo);
