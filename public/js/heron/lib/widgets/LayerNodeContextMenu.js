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
 *  class = LayerNodeContextMenu
 *  base_link = `Ext.menu.Menu <http://docs.sencha.com/extjs/3.4.0/#!/api/Ext.menu.Menu>`_
 */

/** api: example
 *  Sample code showing how to include a default LayerNodeContextMenu. Optionally pass your own menu items.
 *
 *  .. code-block:: javascript
 *
 *         .
 *         .
 *         {
 *          xtype: 'hr_layertreepanel',
 *          border: true,
 *				 .
 *				 .
 *		    FOR NOW: TODO: something smart with ExtJS plugins, for now pass only standard Menu Items.
 *			contextMenu: [{xtype: 'hr_layernodemenuzoomextent'}, {xtype: 'hr_layernodemenustyle'}]);
 *		 },
 *         {
 *			 // The MapPanel
 *			 xtype: 'hr_mappanel',
 *			 id: 'hr-map',
 *			 region: 'center',
 *			 .
 *			 .
 */


/** api: constructor
 *  .. class:: LayerNodeContextMenu(items)
 *
 *  A context menu for (usually right-click) LayerNodes in Layer trees.
 */
Heron.widgets.LayerNodeContextMenu = Ext.extend(Ext.menu.Menu, {
    listeners: {
        beforeshow: function (cm) {
            var node = cm.contextNode;
            cm.items.each(function(item) {
                item.setDisabled(!item.isApplicable(node));
            })
        },
        scope: this
    },

    initComponent: function () {
        /** Default menu items when no menu items passed in options. */
        this.initialConfig = this.items ? this.items : [
            {
                xtype: 'hr_layernodemenulayerinfo'
            },
            {
                xtype: 'hr_layernodemenuzoomextent'
            },
            {
                xtype: 'hr_layernodemenuopacityslider'
            },
            {
                xtype: 'hr_layernodemenustyle'
            }
        ];

        this.items = undefined;


        Heron.widgets.LayerNodeContextMenu.superclass.initComponent.call(this);
    }
});

/** api: xtype = hr_layernodecontextmenu */
Ext.reg('hr_layernodecontextmenu', Heron.widgets.LayerNodeContextMenu);


