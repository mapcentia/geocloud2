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
 *  class = LayerLegendPanel
 *  base_link = `GeoExt.LegendPanel <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=GeoExt.LegendPanel>`_
 */

/** api: constructor
 *  .. class:: LayerLegendPanel(config)
 *
 *  A panel designed to hold legends for Map Layers (WMS GetLegendGraphic results).
 *  Optionally enable prefetching Legends before Layer becomes visible.
 *
 *  .. code-block:: javascript
 *
 *              items: [
 *                 {
 *					 xtype: 'hr_layerlegendpanel',
 *					 defaults: {
 *					 	 useScaleParameter : false,
 *						 baseParams: {
 *						     // Override default image/gif in WMS GetLegendGraphic
 *							 FORMAT: 'image/png'
 *						 }
 *					 },
 *					 hropts: {
 *						 // Preload Legends on initial startup
 *						 // Will fire WMS GetLegendGraphic's for WMS Legends
 *						 // Otherwise Legends will be loaded only when Layer
 *						 // becomes visible. Default: false
 *						 prefetchLegends: false
 *					 }
 *				 }
 *             ]
 *
 */
Heron.widgets.LayerLegendPanel = Ext.extend(GeoExt.LegendPanel, {
    title: __('Legend'),
    bodyStyle: 'padding:5px',
    autoScroll: true,

    /** api: config[defaults]
     *  Optional parameters to add to the legend url, this can e.g. be used to
     *  support vendor-specific parameters in a SLD WMS GetLegendGraphic
     *  request. To override the default MIME type of image/gif use the
     *  FORMAT parameter in defaults.baseParams. See GeoExt WMSLegend.js
     */
    defaults: {
        useScaleParameter: false,
        baseParams: {
            // Empty
        }
    },
    dynamic: true,

    initComponent: function () {
        // Should Legends be prefetched even when not visible ?
        if (this.hropts) {
            // Pass to GeoExt LegendPanel, as GeoExt 1.1+ may fix this
            this.prefetchLegends = this.hropts.prefetchLegends;
        }
        Heron.widgets.LayerLegendPanel.superclass.initComponent.call(this);
    },

    /** private: method[onRender]
     *  Private method called when the legend panel is being rendered.
     */
    onRender: function () {
        Heron.widgets.LayerLegendPanel.superclass.onRender.apply(this, arguments);
        // Delay processing, since the Map and Layers may not be available.
        this.layerStore.addListener("update", this.onUpdateLayerStore, this);
    },

    /** private: method[onUpdateLayerStore]
     *  Private method called when a layer is removed from the store.
     *
     *  :param store: ``Ext.data.Store`` The store from which the record(s) was
     *      removed.
     *  :param record: ``Ext.data.Record`` The record object(s) corresponding
     *      to the removed layers.
     *  :param index: ``Integer`` The index of the removed record.
     */
    onUpdateLayerStore: function (store, record, index) {
        this.addLegend(record, index);
    },

    /** private: method[addLegend]
     *  Add a legend for the layer.
     *
     *  :param record: ``Ext.data.Record`` The record object from the layer
     *      store.
     *  :param index: ``Integer`` The position at which to add the legend.
     */
    addLegend: function (record, index) {
        record.store = this.layerStore;
        var layer = record.getLayer();

        // Try to gather all legend info in metadata object (cleaner)
        // This is also the Heron config convention for Layer
        if (!layer.metadata.legend) {
            layer.metadata.legend = {};
        }

        // Layer legend metadata object
        var layerLegendMD = layer.metadata.legend;

        // Legacy and deprecated: heron option layer.noLegend should become GeoExt layer.hideInLegend
        if (layer.noLegend) {
            layer.hideInLegend = layerLegendMD.hideInLegend = true;
        }

        // GeoExt expects hideInLegend to be set in the record not a layer property
        // so transfer value to record
        if (layerLegendMD.hideInLegend && !record.get('hideInLegend')) {
            record.set('hideInLegend', true);
        }

        if (layer.legendURL) {
            // Cope with old configs
            layerLegendMD.legendURL = layer.legendURL;
        }

        // the same applies to legendURL
        if (layerLegendMD.legendURL && !record.get('legendURL')) {
            record.set('legendURL', layerLegendMD.legendURL);
        }

        var legend = undefined;
        if (this.items) {
            legend = this.getComponent(this.getIdForLayer(layer));
        }

        // Only add the legend if:
        // - its Layer is visible  AND
        // - it should not be hidden (hideInLegend == true) AND
        // - it has not been created
        // Otherwise Legends to be shown even for invisible layers
        // are always prefetched. With many layers this can mean long loading time.
        if ((this.prefetchLegends && !legend) || (((layer.map && layer.visibility) || layer.getVisibility()) && !legend && !layerLegendMD.hideInLegend)) {
            // GeoExt LegendPanel takes care off adding
            Heron.widgets.LayerLegendPanel.superclass.addLegend.apply(this, arguments);

            // Force legends to become visible
            this.doLayout();
        }

        // Force legends update every time to become visible
        this.doLayout();

    },

    onListenerDoLayout: function (node) {
        node.doLayout();
    },

    // method[listeners]
    //  Force legends to become visible ...
    //  ... inside an activated TabPanel or inside an expanded (accordion) panel
    //
    //  ATTENTION:
    //  ----------
    //  This listener only gets the events of the panel in which it is located - if there is a
    //  further panel - higher above in the tree - you must define an additional / other listener
    //  function to support the redraw events!!!
    //
    listeners: {
        activate: function (node) {
            this.onListenerDoLayout(this);
        },
        expand: function (node) {
            this.onListenerDoLayout(this);
        }
    }

});

/** api: xtype = hr_layerlegendpanel */
Ext.reg('hr_layerlegendpanel', Heron.widgets.LayerLegendPanel);
