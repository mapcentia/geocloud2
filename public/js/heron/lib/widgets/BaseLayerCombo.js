/**
 * Copyright (c) 2008-2009 The Open Source Geospatial Foundation
 *
 * Published under the BSD license.
 * See http://svn.geoext.org/core/trunk/geoext/license.txt for the full text
 * of the license.
 */

// Note original from
// http://www.webmapcenter.de/geoext-baselayer-combo/GeoExt.ux.BaseLayerCombobox.js
// adapted for Heron with general LayerCombo class, namespacing and I18N
// Ext.ns('GeoExt.ux');
// JvdB: most functions moved to LayerCombo.

Ext.namespace("Heron.widgets");

/** api: (define)
 *  module = Heron.widgets
 *  class = BaseLayerCombo
 *  base_link = `Heron.widgets.LayerCombo <LayerCombo.html>`_
 */

/**
 *
 * Combo box for switching base Layers of a given Map.
 *
 * @constructor
 * @extends Heron.widgets.LayerCombo
 *
 */
Heron.widgets.BaseLayerCombo = Ext.extend(Heron.widgets.LayerCombo, {

	/** api: config[emptyText]
	 *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
	 *  default value is "Choose a Base Layer".
	 */
	emptyText: __('Choose a Base Layer'),

	/** api: config[tooltip]
	 *  See http://www.dev.sencha.com/deploy/dev/docs/source/TextField.html#cfg-Ext.form.TextField-emptyText,
	 *  default value is "Basemaps".
	 */
	tooltip: __('BaseMaps'),

	/** private: property[layerFilter]
	 *  layerFilter - function that takes subset of all layers, e.g. all visible or baselayers
	 */
	layerFilter: function (map) {
		return map.getLayersBy('isBaseLayer', true);
	},

	/** private: constructor
	 */
	initComponent: function () {
		if (this.initialConfig.map !== null && this.initialConfig.map instanceof OpenLayers.Map && this.initialConfig.map.allOverlays === false) {

			this.map = this.initialConfig.map;

			// set the selectlayer (from LayerCombo) event handler
			this.on('selectlayer', function (layer) {
				// record.getLayer(idx).setVisibility(true);
				this.map.setBaseLayer(layer);
			}, this);

			// register event if base layer changes
			this.map.events.register('changebaselayer', this, function (obj) {
				this.setValue(obj.layer.name);
			});

			// Will be set by LayerCombo
			this.initialValue = this.map.baseLayer.name;
		}

		Heron.widgets.BaseLayerCombo.superclass.initComponent.apply(this, arguments);
	}

});

/** api: xtype = hr_baselayer_combobox */
Ext.reg('hr_baselayer_combobox', Heron.widgets.BaseLayerCombo);
