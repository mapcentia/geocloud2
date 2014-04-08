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
Ext.namespace("Heron.widgets.LayerNodeMenuItem");

/** api: (define)
 *  module = Heron.widgets
 *  class = LayerNodeMenuItem
 *  base_link = `Ext.menu.Item <http://docs.sencha.com/extjs/3.4.0/#!/api/Ext.menu.Item>`_
 */

/** api: constructor
 *  .. class:: LayerNodeMenuItem()
 *
 *  The Base Class for specific layer context menu items.
 */
Heron.widgets.LayerNodeMenuItem = Ext.extend(Ext.menu.Item, {

    /** Is this menu item applicable for this node/layer? */
    isApplicable: function (node) {
        return true;
    }
});


/** api: (define)
 *  module = Heron.widgets
 *  class = LayerNodeMenuItem.Style
 *  base_link = `Ext.menu.Item <http://docs.sencha.com/extjs/3.4.0/#!/api/Ext.menu.Item>`_
 */

/** api: example
 *  Sample code showing how to include a default LayerNodeMenuItem.Style. Optionally pass your own menu items.
 *
 *  .. code-block:: javascript
 *
 *         .
 *         .
 *         {
 *          xtype: 'hr_layertreepanel',
 *		    FOR NOW: TODO: something smart with ExtJS plugins..
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
 *  .. class:: LayerNodeMenuItem.Style(items)
 *
 *  A context menu item to style a Layer.
 */
Heron.widgets.LayerNodeMenuItem.Style = Ext.extend(Heron.widgets.LayerNodeMenuItem, {

    text: __('Edit Layer Style'),
    iconCls: "icon-palette",
    disabled: false,
    listeners: {
        'activate': function (menuItem, event) {
            var node = menuItem.ownerCt.contextNode;
            if (!node || !node.layer) {
            }
            //                                var c = node.getOwnerTree().contextMenu;
            //                                c.items.get(0).setDisabled(true);
            //                                c.items.get(1).setDisabled(true);
            //
            //                                if (node.layer.CLASS_NAME == 'OpenLayers.Layer.Vector') {
            //                                    c.items.get(0).setDisabled(false);
            //                                }
            //                                if (node.layer.getDataExtent()) {
            //                                    c.items.get(1).setDisabled(false);
            //                                }

        },
        scope: this

    },

    initComponent: function () {
        Heron.widgets.LayerNodeMenuItem.Style.superclass.initComponent.call(this);
    },

    handler: function (menuItem, event) {
        var node = menuItem.ownerCt.contextNode;
        if (!node || !node.layer) {
            return;
        }
        if (node.layer.CLASS_NAME != 'OpenLayers.Layer.Vector') {
            // TODO: find an elegant way to disable menu
            Ext.Msg.alert(__('Warning'), __('Sorry, Layer style editing is only available for Vector Layers'));
            return;
        }

        if (!gxp.VectorStylesDialog) {
            Ext.Msg.alert(__('Warning'), __('Vector Layer style editing requires GXP with VectorStylesDialog'));
            return;
        }
        var layerRecord = Heron.App.getMapPanel().layers.getByLayer(node.layer);

        new Ext.Window({
            layout: 'auto',
            resizable: false,
            autoHeight: true,
            pageX: 100,
            pageY: 200,
            width: 400,
            // height: options.formHeight,
            closeAction: 'hide',
            title: __('Style Editor (Vector)'),
            items: [
                gxp.VectorStylesDialog.createVectorStylerConfig(layerRecord)
            ]
        }).show();
    },

    /** Is this menu item applicable for this node/layer? Only for Vector layers.*/
    isApplicable: function (node) {
        return node.layer.CLASS_NAME == 'OpenLayers.Layer.Vector';
    }
});

/** api: xtype = hr_layernodemenustyle */
Ext.reg('hr_layernodemenustyle', Heron.widgets.LayerNodeMenuItem.Style);

/** api: (define)
 *  module = Heron.widgets
 *  class = LayerNodeMenuItem.ZoomExtent
 *  base_link = `Ext.menu.Item <http://docs.sencha.com/extjs/3.4.0/#!/api/Ext.menu.Item>`_
 */

/** api: example
 *  Sample code showing how to include LayerNodeMenuItems. Optionally pass your own menu items.
 *
 *  .. code-block:: javascript
 *
 *         .
 *         .
 *         {
 *          xtype: 'hr_layertreepanel',
 *		    FOR NOW: TODO: something smart with ExtJS plugins..
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
 *  .. class:: LayerNodeMenuItem.ZoomExtent(items)
 *
 *  A context menu item to zoom to data or max extent of Layer.  For Vector Layers the extent
 *  is the data extent. For raster layers the 'maxExtent' Layer property needs to be explicitly set.
 *
 */
Heron.widgets.LayerNodeMenuItem.ZoomExtent = Ext.extend(Heron.widgets.LayerNodeMenuItem, {

    text: __('Zoom to Layer Extent'),
    iconCls: "icon-zoom-visible",

    initComponent: function () {
        Heron.widgets.LayerNodeMenuItem.ZoomExtent.superclass.initComponent.call(this);
    },

    handler: function (menuItem, event) {
        var node = menuItem.ownerCt.contextNode;
        if (!node || !node.layer) {
            return;
        }
        var layer = node.layer;
        var zoomExtent;

        // If the Layer has a set maxExtent, this prevails, otherwise
        // try to get data extent (Vector Layers mostly).
        if (this.hasMaxExtent) {
            zoomExtent = layer.maxExtent;
        } else {
            zoomExtent = layer.getDataExtent();
        }

        if (!zoomExtent) {
            // TODO: find an elegant way to disable menu
            Ext.Msg.alert(__('Warning'), __('Sorry, no data-extent is available for this Layer'));
            return;
        }

        layer.map.zoomToExtent(zoomExtent);
    },

    /** Is this menu item applicable for this node/layer? */
    isApplicable: function (node) {
        // Layer: assume fixed maxExtent when set AND different from Map maxExtent
        this.hasMaxExtent = node.layer.maxExtent && !node.layer.maxExtent.equals(node.layer.map.maxExtent);
        return node.layer.getDataExtent() || this.hasMaxExtent;
    }
});

/** api: xtype = hr_layernodemenuzoomextent */
Ext.reg('hr_layernodemenuzoomextent', Heron.widgets.LayerNodeMenuItem.ZoomExtent);

/** api: (define)
 *  module = Heron.widgets
 *  class = LayerNodeMenuItem.LayerInfo
 *  base_link = `Ext.menu.Item <http://docs.sencha.com/extjs/3.4.0/#!/api/Ext.menu.Item>`_
 */

/** api: example
 *  Sample code showing how to include LayerNodeMenuItems. Optionally pass your own menu items.
 *
 *  .. code-block:: javascript
 *
 *         .
 *         .
 *         {
 *          xtype: 'hr_layertreepanel',
 *		    FOR NOW: TODO: something smart with ExtJS plugins..
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
 *  .. class:: LayerNodeMenuItem.LayerInfo(options)
 *
 *  A context menu item to show info and metadata of a Layer.
 */
Heron.widgets.LayerNodeMenuItem.LayerInfo = Ext.extend(Heron.widgets.LayerNodeMenuItem, {

    text: __('Get Layer information'),
    iconCls: "icon-information",

    initComponent: function () {
        Heron.widgets.LayerNodeMenuItem.LayerInfo.superclass.initComponent.call(this);
    },

    handler: function (menuItem, event) {
        var node = menuItem.ownerCt.contextNode;
        if (!node || !node.layer) {
            return;
        }
        var layer = node.layer;
        var layerType = layer.CLASS_NAME.split(".").pop();
        var isVector = layerType == 'Vector';
        var isWFS = layer.protocol && layer.protocol.CLASS_NAME.indexOf('WFS') > 0;

        layerType = isWFS ? 'Vector (WFS)' : layerType;
        var tiled = layer.singleTile || isVector ? 'No' : 'Yes';
        var hasWFS = layer.metadata.wfs || isWFS ? 'Yes' : 'No';
        var hasFeatureInfo = isVector || layer.featureInfoFormat ? 'Yes' : 'No';

        Ext.MessageBox.show({
            title: String.format('Info for Layer "{0}"', layer.name),
            msg: String.format('Placeholder: should become more extensive with infos, metadata, etc.!<br>' +
                "<br>Name: {0}" +
                "<br>Type: {1}" +
                "<br>Tiled: {2}" +
                "<br>Has feature info: {3}" +
                "<br>Has WFS: {4}"
                , layer.name, layerType, tiled, hasFeatureInfo, hasWFS),
            buttons: Ext.Msg.OK,
            fn: function (btn) {
                if (btn == 'ok') {
                }
            },
            icon: Ext.MessageBox.INFO,
            maxWidth: 300
        });
    }
});

/** api: xtype = hr_layernodemenulayerinfo */
Ext.reg('hr_layernodemenulayerinfo', Heron.widgets.LayerNodeMenuItem.LayerInfo);

/** api: (define)
 *  module = Heron.widgets
 *  class = LayerNodeMenuItem.Opacity
 *  base_link = `Ext.menu.Item <http://docs.sencha.com/extjs/3.4.0/#!/api/Ext.menu.Item>`_
 */

/** api: example
 *  Sample code showing how to include LayerNodeMenuItems. Optionally pass your own menu items.
 *
 *  .. code-block:: javascript
 *
 *         .
 *         .
 *         {
 *          xtype: 'hr_layertreepanel',
 *		    FOR NOW: TODO: something smart with ExtJS plugins..
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
 *  .. class:: LayerNodeMenuItem.OpacitySlider(items)
 *
 *  A context menu item to popup opacity slider to change opacity of Layer.
 */
Heron.widgets.LayerNodeMenuItem.OpacitySlider = Ext.extend(Heron.widgets.LayerNodeMenuItem, {

    text: __('Change Layer opacity'),
    iconCls: 'icon-opacity',

    initComponent: function () {
        Heron.widgets.LayerNodeMenuItem.OpacitySlider.superclass.initComponent.call(this);
    },

    handler: function (menuItem, event) {
        var node = menuItem.ownerCt.contextNode;
        if (!node || !node.layer) {
            return;
        }
        var layer = node.layer;
        // Opacity dialog
        var cmp = Ext.getCmp('WinOpacity-' + layer.id);
        var xy = event.getXY();
        xy[0] = xy[0] + 40;
        xy[1] = xy[1] + 0;

        if (!cmp) {

            cmp = new Ext.Window({
                title: __('Opacity'),
                id: 'WinOpacity-' + layer.id,
                x: xy[0],
                y: xy[1],
                width: 200,
                resizable: false,
                constrain: true,
                bodyStyle: 'padding:2px 4px',
                closeAction: 'hide',
                listeners: {
                    hide: function () {
                        cmp.x = xy[0];
                        cmp.y = xy[1];
                    },
                    show: function () {
                        cmp.show();
                        cmp.focus();
                    }
                },
                items: [
                    {
                        xtype: 'label',
                        text: layer.name,
                        height: 20
                    },
                    {
                        xtype: "gx_opacityslider",
                        showTitle: false,
                        plugins: new GeoExt.LayerOpacitySliderTip(),
                        vertical: false,
                        inverse: false,
                        aggressive: false,
                        layer: layer
                    }
                ]
            });
            cmp.show();

        } else {
            if (cmp.isVisible()) {
                cmp.hide();
            } else {
                cmp.setPosition(xy[0], xy[1]);
                cmp.show();
                cmp.focus();
            }
        }
    }
});

/** api: xtype = hr_layernodemenuopacityslider */
Ext.reg('hr_layernodemenuopacityslider', Heron.widgets.LayerNodeMenuItem.OpacitySlider);


/*                    {
 text: "Metadata",
 icon: '../images/grid.png',
 handler: function () {
 if (!winContext) {
 var node = layerTree.getSelectionModel().getSelectedNode();
 var layername = node.text;
 var winContext = new Ext.Window({
 title: '<span style="color:#000000; font-weight:bold;">Metadaten: </span>' + layername,
 layout: 'fit',
 text: layername,
 width: 800,
 height: 500,
 closeAction: 'hide',
 plain: true,
 items: [tabsMetadata],
 buttons: [
 {
 text: 'Schlie&szlig;en',
 handler: function () {
 winContext.hide()
 }
 }
 ]
 })
 }
 winContext.show(this)
 },
 scope: this
 },
 removeLayerAction,
 {
 text: "Zusatzlayer hinzuf&uuml;gen",
 icon: '../images/add.png',
 handler: function () {
 if (!capabiltieswin) {
 var capabiltieswin = new Ext.Window({
 title: "WMS Layer hinzuf&uuml;gen",
 layout: 'fit',
 width: '600',
 height: 'auto',
 border: false,
 closable: true,
 collapsible: true,
 x: 450,
 y: 100,
 resizable: true,
 closeAction: 'hide',
 plain: true,
 tbar: [tabsMetadata]
 })
 }
 capabiltieswin.show(this)
 }
 }*/