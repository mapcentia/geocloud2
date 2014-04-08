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
 *  class = CapabilitiesTreePanel
 *  base_link = `Ext.tree.TreePanel <http://dev.sencha.com/deploy/ext-3.3.1/docs/?class=Ext.tree.TreePanel>`_
 */

/** api: example
 *  Sample code showing how to include a CapabilitiesTreePanel that automatically configures a layer tree
 *  from a WMS URL by doing a GetCapabilities and using the result to build the layertree.
 *
 *  .. code-block:: javascript
 *
 *		 .
 *		 .
 *		 {
 *			 xtype: 'panel',
 *
 *			 id: 'hr-menu-left-container',
 *				 .
 *				 .
 *			 items: [
 *				 {
 *					 // The TreePanel to be populated from a GetCapabilities request.
 *					 title: 'Layers',
 *					 xtype: 'hr_capabilitiestreepanel',
 *					 autoScroll: true,
 *					 useArrows: true,
 *					 animate: true,
 *					 hropts: {
 *						 text: 'GetCaps Tree Panel',
 *						 preload: true,
 *						 url: 'http://eusoils.jrc.ec.europa.eu/wrb/wms_Landuse.asp?'
 *					 }},
 *
 *				 {
 *					.
 *					.
 *				 }
 *			 ]
 *		 },
 *		 {
 *			 // The MapPanel
 *			 xtype: 'hr_mappanel',
 *			 id: 'hr-map',
 *			 region: 'center',
 *			 .
 *			 .
 */


/** api: constructor
 *  .. class:: CapabilitiesTreePanel(config)
 *
 *  A panel designed to hold trees of Map Layers from a WMS Capabilties.
 */
Heron.widgets.CapabilitiesTreePanel = Ext.extend(Ext.tree.TreePanel, {

	initComponent : function() {
		// Default WMS Layer object parameters, optional override with Heron config
		var layerOptions = Ext.apply({buffer: 0, singleTile: true, ratio: 1}, this.hropts.layerOptions);
		var layerParams = Ext.apply({'TRANSPARENT': 'TRUE'}, this.hropts.layerParams);

		var root = new Ext.tree.AsyncTreeNode({
					text: this.hropts.text,
					expanded: this.hropts.preload,
					loader: new GeoExt.tree.WMSCapabilitiesLoader({
								url: this.hropts.url,
								layerOptions: layerOptions,
								layerParams: layerParams,
								// customize the createNode method to add a checkbox to nodes
								createNode: function(attr) {
									attr.checked = attr.leaf ? false : undefined;
									return GeoExt.tree.WMSCapabilitiesLoader.prototype.createNode.apply(this, [attr]);
								}
							})
				});

		this.options = {
			root: root,
			listeners: {
				// Add layers to the map when ckecked, remove when unchecked.
				// Note that this does not take care of maintaining the layer
				// order on the map.
				'checkchange': function(node, checked) {
					var map = Heron.App.getMap();
					// Safeguard
					if (!map) {
						return;
					}

					var layer = node.attributes.layer;
					if (checked === true) {
						map.addLayer(layer);
					} else {
						map.removeLayer(layer);
					}
				}
			}
		};


		Ext.apply(this, this.options);
		Heron.widgets.CapabilitiesTreePanel.superclass.initComponent.call(this);
	}
});

/** api: xtype = hr_CapabilitiesTreePanel */
Ext.reg('hr_capabilitiestreepanel', Heron.widgets.CapabilitiesTreePanel);
