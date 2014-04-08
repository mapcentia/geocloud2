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

/** custom layer node UI class  */
var ActiveLayerNodeUI = Ext.extend(
		GeoExt.tree.LayerNodeUI,
		new GeoExt.tree.TreeNodeUIEventMixin()
);

/** Define an overridden LayerNode */
Heron.widgets.ActiveLayerNode = Ext.extend(GeoExt.tree.LayerNode, {

	render: function(bulkRender) {

		var layer = this.layer instanceof OpenLayers.Layer && this.layer;
	    if (layer && this.attributes && this.attributes.component && this.attributes.component.xtype == "gx_opacityslider") {
			// Needed to fix that the LayerOpacitySlider seems to not have a layer in cases...
			// See issue #65
			this.attributes.component.layer = layer;
			// OL
			if (layer.opacity>=1.0) {
				layer.setOpacity(1.0);
			}
			else if (layer.opacity<0.0) {
				layer.setOpacity(0.0);
			}
			// Slider
			this.attributes.component.value = parseInt(layer.opacity * 100);
		}

		// Call modified base class - see 'override-geoext.js' or code below
		Heron.widgets.ActiveLayerNode.superclass.renderX.call(this, bulkRender);

		/*
		// ===================================================================
		// === From GeoExt 1.1 - 'LayerNode.js' - GeoExt.tree.LayerNode.render
		// ===================================================================
		if(!layer) {
			// guess the store if not provided
			if(!this.layerStore || this.layerStore == "auto") {
				this.layerStore = GeoExt.MapPanel.guess().layers;
			}
			// now we try to find the layer by its name in the layer store
			var i = this.layerStore.findBy(function(o) {
				return o.get("title") == this.layer;
			}, this);
			if(i != -1) {
				// if we found the layer, we can assign it and everything
				// will be fine
				layer = this.layerStore.getAt(i).getLayer();
			}
		}
		if (!this.rendered || !layer) {
			var ui = this.getUI();

			if(layer) {
				this.layer = layer;
				// no DD and radio buttons for base layers
				if(layer.isBaseLayer) {
					this.draggable = false;

					// Don't use 'checkedGroup' argument

					// Ext.applyIf(this.attributes, {
					// checkedGroup: "gx_baselayer"
					// });

					// Disabled baselayer checkbox
					this.disabled = true;
				}

				//base layers & alwaysInRange layers should never be auto-disabled
				this.autoDisable = !(this.autoDisable===false || this.layer.isBaseLayer || this.layer.alwaysInRange);

				if(!this.text) {
					this.text = layer.name;
				}

				ui.show();
				this.addVisibilityEventHandlers();
			} else {
				ui.hide();
			}

			if(this.layerStore instanceof GeoExt.data.LayerStore) {
				this.addStoreEventHandlers(layer);
			}
		}
		GeoExt.tree.LayerNode.superclass.render.apply(this, arguments);
		// ===================================================================
		// === End GeoExt 1.1 - 'LayerNode.js' - GeoExt.tree.LayerNode.render
		// ===================================================================
		*/

		if (layer && this.attributes && this.attributes.component && this.attributes.component.xtype == "gx_opacityslider") {
			// Triggers opacity change event in order to force slider to right position
			// See issue #65
			// OL
			if (layer.opacity>=1.0) {
				layer.setOpacity(0.999);
				layer.setOpacity(1.0);
			}
			else if (layer.opacity>=0.001) {
				layer.setOpacity(layer.opacity-0.001);
				layer.setOpacity(layer.opacity+0.001);
			} else {
				layer.setOpacity(0.001);
				layer.setOpacity(0.0);
			}
			// Slider
			this.attributes.component.value = parseInt(layer.opacity * 100);

			// - WW -
			// This doesn't work for a 'hr_activelayerspanel' component located in a NOT
			// EXPANDED ExtJS panel item. After activating/expanding the panel the slider
			// must be updated to the (new) slider value.
			// => listeners method for 'activate' and 'expand' fixes this issue - see below.

		}
	}
});

/**
* NodeType: hr_activelayer
*/
Ext.tree.TreePanel.nodeTypes.hr_activelayer = Heron.widgets.ActiveLayerNode;

/** api: (define)
 *  module = Heron.widgets
 *  class = ActiveLayersPanel
 *  base_link = `Ext.tree.TreePanel <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.tree.TreePanel>`_
 */

/** api: constructor
 *  .. class:: ActiveLayersPanel(config)
 *
 *  Displays a stack of selected layers from the map.
 *  The main purpose is to enable to change layer stacking (display) order, supported
 *  by standard drag-and-drop, plus manipulating individual layer opacity.
 *
 *  Example config with a per layer opacity-slider.
 *
 *  .. code-block:: javascript
 *
 *      {
 *	 		xtype: 'hr_activelayerspanel',
 *	 		height: 240,
 *	 		flex: 3,
 *	 		hropts: {
 *	 			// Defines the custom component added under the standard layer node.
 *	 			component : {
 *	 				xtype: "gx_opacityslider",
 *	 				showTitle: false,
 *	 				plugins: new GeoExt.LayerOpacitySliderTip(),
 *	 				width: 160,
 *	 				inverse: false,
 *	 				aggressive: false,
 *	 				style: {
 *	 					marginLeft: '18px'
 *	 				}
 *	 			}
 *	 		}
 *	 	}
 *
 *
 */
Heron.widgets.ActiveLayersPanel = Ext.extend(Ext.tree.TreePanel, {

    /** api: config[title]
     *  default value is "Active Layers".
     */
	title : __('Active Layers'),

    /** api: config[contextMenu]
     *  Context menu (right-click) for layer nodes, for now instance of Heron.widgets.LayerNodeContextMenu. Default value is null.
     */
    contextMenu: null,

	applyStandardNodeOpts: function(opts, layer) {
		if (opts.component) {
			opts.component.layer = layer;
		}
		opts.layerId = layer.id;
	},

	initComponent : function() {
		var self = this;
		var options = {
			// id: "hr-activelayers",
			title : this.title,
			// collapseMode: "mini",
			autoScroll: true,
			enableDD: true,
			// apply the tree node component plugin to layer nodes
			plugins: [
				{
					ptype: "gx_treenodecomponent"
				}
			],
			root: {
				nodeType: "gx_layercontainer",
                text: __('Layers'),
				loader: {
					applyLoader: false,
					baseAttrs: {
						uiProvider: ActiveLayerNodeUI,
						iconCls : 'gx-activelayer-drag-icon'
					},
					createNode: function(attr) {
						return self.createNode(self, {layer: attr.layer});
					},
					// Add only visible layers that indicate to be shown in lists/overviews
					filter: function(record) {
                        var layer = record.getLayer();
						return layer.getVisibility() && layer.displayInLayerSwitcher;
					}
				}
			},
			rootVisible: false,
			lines: false,
            listeners: {
                contextmenu: function (node, e) {
                    node.select();
                    var cm = this.contextMenu;
                    if (cm) {
                        cm.contextNode = node;
                        cm.showAt(e.getXY());
                    }
                },
                scope: this
            }
		};

        // Optional (right-click) context menu for LayerNodes
        if (this.contextMenu) {
            var cmArgs = this.contextMenu instanceof Array ? {items: this.contextMenu} : {};
            this.contextMenu = new Heron.widgets.LayerNodeContextMenu(cmArgs);
        }

		Ext.apply(this, options);
		Heron.widgets.ActiveLayersPanel.superclass.initComponent.call(this);

		// Delay processing, since the Map and Layers may not be available.
		this.addListener("afterrender", this.onAfterRender);
		this.addListener("beforedblclick", this.onBeforeDblClick);
		this.addListener("beforenodedrop", this.onBeforeNodeDrop);
	},


	createNode : function(self, attr) {
		if (self.hropts) {
			Ext.apply(attr, self.hropts);
		} else {
			Ext.apply(attr, {} );
		}
		self.applyStandardNodeOpts(attr, attr.layer);
		attr.uiProvider = ActiveLayerNodeUI;
		attr.nodeType = "hr_activelayer";
		attr.iconCls = 'gx-activelayer-drag-icon';
		return GeoExt.tree.LayerLoader.prototype.createNode.call(self, attr);
	},

	onBeforeDblClick : function(node, evt) {
		// @event beforedblclick
		// Fires before double click processing. Return false to cancel the default action.
		// @param {Node} this This node
		// @param {Ext.EventObject} e The event object
		return false;
	},

	onBeforeNodeDrop : function(dropEvt) {
		// @event beforenodedrop
		// @param {dropEvt} drop event
		// dropEvt properties:
		// tree - The TreePanel
		// target - The node being targeted for the drop
		// data - The drag data from the drag source
		// point - The point of the drop - append, above or below
		// source - The drag source
		// rawEvent - Raw mouse event
		// dropNode - Drop node(s) provided by the source OR you can supply node(s) to be inserted by setting them on this object.
		// cancel - Set this to true to cancel the drop.
		// dropStatus - If the default drop action is cancelled but the drop is valid, setting this to true will prevent the animated 'repair' from appearing.
		if (dropEvt) {
			switch(dropEvt.point) {
				case "above":
					return true;
					break;
				case "below":
					// No drop below a baselayer node
					var layer = dropEvt.target.layer;
					if (!layer.isBaseLayer) {
						return true;
					}
					break;
			}
		}
		return false;
	},

	onAfterRender : function() {
		var self = this;
		var map = Heron.App.getMap();

		map.events.register('changelayer', null, function(evt) {

			var layer = evt.layer;
			var rootNode = self.getRootNode();
			var layerNode = rootNode.findChild('layerId', evt.layer.id);

			if (evt.property === "visibility") {

				// Add layer node dependent on visibility and if not in tree
				if (evt.layer.getVisibility() && !layerNode) {

					// Create new layer node
					var newNode = self.createNode(self, {layer: layer});
					var newLayerId = layer.map.getLayerIndex(layer);

 					// Add layer node
					if (layer.isBaseLayer) {
						// baselayer
						// Remember current bottom layer node in stack before adding new node
						var bottomLayer;
						var bottomLayerId;
						if (rootNode.lastChild) {
							bottomLayer = rootNode.lastChild.layer;
							if (bottomLayer) {
	 							bottomLayerId = bottomLayer.map.getLayerIndex(bottomLayer);
							}
						}
						// Always insert as last child, i.e. to bottom of the layer stack
						rootNode.appendChild(newNode);
						// OL - layer index correction - push to bottom of the layer stack
						if (bottomLayer) {
							if (newLayerId > bottomLayerId) {
								layer.map.raiseLayer(layer, bottomLayerId - newLayerId);
							}
						}
					} else {
						// layer
						// Remember current top layer node in stack before adding new node
						var topLayer;
						var topLayerId;
						if (rootNode.firstChild) {
							topLayer = rootNode.firstChild.layer;
							if (topLayer) {
	 							topLayerId = topLayer.map.getLayerIndex(topLayer);
							}
						}
						// Always insert new node as first child, i.e. on top of the layer stack
						rootNode.insertBefore(newNode, rootNode.firstChild);
						// OL - layer index correction - push on top of the layer stack
						if (topLayer) {
							if (topLayerId > newLayerId ) {
								layer.map.raiseLayer(layer, topLayerId - newLayerId);
							}
						}
					}

					// Reload whole layer tree - panel content could be not visible / not active
					rootNode.reload();

				} else if (!evt.layer.getVisibility() && layerNode) {
					layerNode.un("move", self.onChildMove, self);
					layerNode.remove();
				}
			}
		});
	},

	onListenerDoLayout : function (node) {
		if (node && node.hropts && node.hropts.component && node.hropts.component.xtype == "gx_opacityslider") {
			var rootNode = node.getRootNode();
			// Set all LayerOpacitySlider values in this tree
			rootNode.cascade( function(n) {
				if (n.layer) {
					n.component.setValue(parseInt(n.layer.opacity * 100));
					n.component.syncThumb();

					// - WW -
					// If the 'ActiveLayersPanel' widget is located inside an ExtJs panel there
					// is an gui error between GeoExt and ExtJs concerning the slider thumb.
					// Case: if the 'gx_opacityslider' component is rendered, when its above node
					// IS NOT EXPANDED (not visible) the thumb gui position is shifted a little
					// bit to the right, so that the left end position of the slider could not be
					// reached. The related slider values are correct - there is no functional
					// limitation. If the above node IS EXPANDED the thumb of the 'gx_opacityslider'
					// component is displayed at the correct place.
					// Demo for the thumb (#) of the slider component (----) in the 0%/100% position:
					//    - gui expected for 0%:	#----------
					//    - gui display  for 0%:	-#---------
					//    - gui expected for 100%:	----------#
					//    - gui display  for 100%:	-----------#

				}
			} );

			// Reload does fix the wrong thumb position of the slider position
			rootNode.reload();
			node.doLayout();

		}
	},

    // method[listeners]
    //  Force the 'gx_opacityslider' component to become visible ...
    //  ... inside an activated TabPanel or inside an expanded (accordion) panel
    //
    //  ATTENTION
    //  ---------
    //  This listener only gets the events of the panel in which it is located - if there is a
    //  further panel - higher above in the tree - you must define an additional / other listener
    //  function to support the redraw of the slider component!!!
    //
	listeners: {
		activate: function(node) {
			this.onListenerDoLayout(this);
		},
		expand: function(node) {
			this.onListenerDoLayout(this);
		}
	}

});

/** api: xtype = hr_activelayerspanel */
Ext.reg('hr_activelayerspanel', Heron.widgets.ActiveLayersPanel);
