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
 *  module = Heron.tree
 *  class = MultiLayerNode
 *  base_link = `GeoExt.tree.LayerNode <http://geoext.org/lib/GeoExt/widgets/tree/LayerNode.html>`_
 */

/** api: constructor
 *  .. class:: MultiLayerNode(config)
 *
 *	  A subclass of ``GeoExt.tree.LayerNode`` that is connected to multiple
 *	  ``OpenLayers.Layer`` objects by setting the node's ``layers`` property with multiple
 *	  comma-separated layer names.
 *
 *	  Checking or
 *	  unchecking the checkbox of this node will directly affect all layers and
 *	  vice versa. The default iconCls for this node's icon is
 *	  "gx-tree-layer-icon", unless it has children.
 *
 *    All methods will delegate to the superclass ``GeoExt.tree.LayerNode``.
 *
 *	  To use this node type in a ``TreePanel`` config, set ``nodeType`` to
 *	  "hr_multilayer".
 */
Heron.widgets.MultiLayerNode = Ext.extend(GeoExt.tree.LayerNode, {
	/** api: config[layerNames]
	 *  ``OpenLayers.Layer``
	 *  The layerNames that this node will
	 *  be bound to.
	 */

	/** api: property[layerNames]
	 *  ``String``
	 *  The array of layerNames's that this Node manages.
	 */
	layerNames: [],

	/** api: config[layers]
	 *  ``OpenLayers.Layer``
	 *  The layers that this node will
	 *  be bound to.
	 */

	/** api: property[layers]
	 *  ``OpenLayers.Layer``
	 *  The array of LayerNode's to delegate to.
	 */
	layers: [],

	/** private: method[constructor]
	 *  Private constructor override.
	 */
	constructor: function(config) {
		if (config.layers) {
			this.layerNames = config.layers.split(",");

			if (this.layerNames[0]) {
				arguments[0].layer = this.layerNames[0];
			}
		}

		// Transform layernames once to OL Layer object array.
		for (var i = 0; i < this.layerNames.length; i++) {
			// guess the store if not provided
			if (!this.layerStore || this.layerStore == "auto") {
				this.layerStore = GeoExt.MapPanel.guess().layers;
			}
			// now we try to find the layer by its name in the layer store
			var j = this.layerStore.findBy(function(o) {
				return o.get("title") == this.layerNames[i];
			}, this);
			if (j != -1) {
				// if we found the layer, we can assign it and everything
				// will be fine
				this.layers[i] = this.layerStore.getAt(j).getLayer();
			}
		}

		Heron.widgets.MultiLayerNode.superclass.constructor.apply(this, arguments);
	},

	/** private: method[render]
	 *  :param bulkRender: ``Boolean``
	 */
	render: function(bulkRender) {
		// One-time rendering needed only
		this.layer = this.layers[0];
		Heron.widgets.MultiLayerNode.superclass.render.apply(this, arguments);
	},

	/** private: method[onLayerVisiilityChanged
	 *  handler for visibilitychanged events on the layer
	 */
	onLayerVisibilityChanged: function() {
		// One-time rendering needed only
		this.layer = this.layers[0];
		Heron.widgets.MultiLayerNode.superclass.onLayerVisibilityChanged.apply(this, arguments);
	},

	/** private: method[onCheckChange]
	 *  :param node: ``Heron.widgets.MultiLayerNode``
	 *  :param checked: ``Boolean``
	 *
	 *  handler for checkchange events
	 */
	onCheckChange: function(node, checked) {

		// Toggles visibility for all layers
		for (var i = 0; i < this.layers.length; i++) {
			this.layer = this.layers[i];
			Heron.widgets.MultiLayerNode.superclass.onCheckChange.apply(this, arguments);
		}
	},

	/** private: method[onStoreAdd]
	 *  :param store: ``Ext.data.Store``
	 *  :param records: ``Array(Ext.data.Record)``
	 *  :param index: ``Number``
	 *
	 *  handler for add events on the store
	 */
	onStoreAdd: function(store, records, index) {
		// TODO: check if we really need to do this for all layers
		for (var i = 0; i < this.layers.length; i++) {
			this.layer = this.layers[i];
			Heron.widgets.MultiLayerNode.superclass.onStoreAdd.apply(this, arguments);
		}
	},

	/** private: method[onStoreRemove]
	 *  :param store: ``Ext.data.Store``
	 *  :param record: ``Ext.data.Record``
	 *  :param index: ``Number``
	 *
	 *  handler for remove events on the store
	 */
	onStoreRemove: function(store, record, index) {
		// TODO: check if we really need to do this for all layers
		for (var i = 0; i < this.layers.length; i++) {
			this.layer = this.layers[i];
			Heron.widgets.MultiLayerNode.superclass.onStoreRemove.apply(this, arguments);
		}
	},

	/** private: method[onStoreUpdate]
	 *  :param store: ``Ext.data.Store``
	 *  :param record: ``Ext.data.Record``
	 *  :param operation: ``String``
	 *
	 *  Listener for the store's update event.
	 */
	onStoreUpdate: function(store, record, operation) {
		// TODO: check if we really need to do this for all layers
		for (var i = 0; i < this.layers.length; i++) {
			this.layer = this.layers[i];
			Heron.widgets.MultiLayerNode.superclass.onStoreUpdate.apply(this, arguments);
		}
	},

	/** private: method[destroy]
	 */
	destroy: function() {
		// TODO: check if we really need to do this for all layers
		for (var i = 0; i < this.layers.length; i++) {
			this.layer = this.layers[i];
			Heron.widgets.MultiLayerNode.superclass.destroy.apply(this, arguments);
		}
	}
});

/**
 * NodeType: hr_multilayer
 */
Ext.tree.TreePanel.nodeTypes.hr_multilayer = Heron.widgets.MultiLayerNode;
