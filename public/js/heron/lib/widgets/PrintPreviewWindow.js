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
 *  class = PrintPreviewWindow
 *  base_link = `Ext.Window <http://docs.sencha.com/ext-js/3-4/#!/api/Ext.Window>`_
 */

/** api: constructor
 *  .. class:: PrintPreviewWindow(config)
 *
 *  An ExtJS Window that contains a GeoExt.ux PrintPreview Container.
 *  PrintPreview is synced from https://github.com/GeoNode/PrintPreview.
 *
 * The Window can be opened through the Toolbar (see example) or directly.
 *
 *  .. code-block:: javascript
 *
 *             // Via Toolbar printer icon.
 *			  {type: "printdialog",
 *			   options: {url: 'http://kademo.nl/print/pdf28992'}
 *			  }
 *
 *			  // Create PrintPreviewWindow directly.
 *			  var printWindow = new Heron.widgets.PrintPreviewWindow({
 *					title: 'My Title',
 *					modal: true,
 *					border: false,
 *					resizable: false,
 *					width: 360,
 *					autoHeight: true,
 *
 *					hropts: {
 *						mapPanel: mapPanel,
 *						method: 'POST',
 *						url: 'http://kademo.nl/print/pdf28992',
 *						legendDefaults: {
 *							useScaleParameter : false,
 *							baseParams: {FORMAT: "image/png"}
 *						},
 *						showTitle: true,				// Flag for rendering the title field
 *						mapTitle: 'My Map Title',		// Title string or null
 *						mapTitleYAML: 'mapTitle',		// MapFish - field name in config.yaml - default is: 'mapTitle'
 *						showComment: true,				// Flag for rendering the comment field
 *						mapComment: 'My Comment text',	// Comment string or null
 *						mapCommentYAML: 'mapComment',	// MapFish - field name in config.yaml - default is: 'mapComment'
 *						showFooter: false,				// Flag for rendering the footer field
 *						mapFooter: null,				// Footer string or null
 *						mapFooterYAML: 'mapFooter',		// MapFish - field name in config.yaml - default is: 'mapFooter'
 *						printAttribution: true,         // Flag for printing the attribution
 *						mapAttribution: null,           // Attribution text or null = visible layer attributions
 *						mapAttributionYAML: 'mapAttribution', // MapFish - field name in config.yaml - default is: 'mapAttribution'
 *						showRotation: true,				// Flag for rendering the rotation field
 *						showOutputFormats: true,		// Flag for rendering the print output formats - default is: false
 *						showLegend: true,				// Flag for rendering the legend checkbox
 *						showLegendChecked: false,		// Status of the legend checkbox
 *						mapLimitScales: true			// Limit scales to those that can be previewed
 *						mapPreviewAutoHeight: false     // Behavior of the preview map height adjustment
 *						mapPreviewHeight: 600          // Static height of the preview map if no automatic height adjustment
 *					}
 *
 *				});
 *
 *
 *	Remarks
 *
 *  "showTitle: true" and "mapTitle: 'string'" or "mapTitle: null" :
 *   - the title edit field will be rendered and but the field will be printed, if it is not empty.
 *
 *  "showTitle: false" and "mapTitle: 'string'" :
 *   - the title edit field will not be rendered, but the string will be printed.
 *
 *  "showTitle: false" and "mapTitle: null" :
 *   - the title edit field will not be rendered and the string will NOT be printed.
 *
 *  Same behavior for the 'Comment' and the 'Footer' entries.
 *
 */
Heron.widgets.PrintPreviewWindow = Ext.extend(Ext.Window, {
	title: __('Print Preview'),
	printCapabilities: null,
	modal: true,
	border: false,
	resizable: false,
	width: 400,
	autoHeight: true,
	layout: 'fit',
	method : 'POST',
	showTitle: true,
	mapTitle: null,
	mapTitleYAML: "mapTitle",		// MapFish - field name in config.yaml - default is: 'mapTitle'
	showComment: true,
    mapComment: null,
    mapCommentYAML: "mapComment",	// MapFish - field name in config.yaml - default is: 'mapComment'
	showFooter: true,
	mapFooter: null,
	mapFooterYAML: "mapFooter",		// MapFish - field name in config.yaml - default is: 'mapFooter'
	printAttribution: true,
	mapAttribution: null,
	mapAttributionYAML: "mapAttribution", // MapFish - field name in config.yaml - default is: 'mapAttribution'
	showRotation: true,
    showOutputFormats: false,
    showLegend: true,
    mapLegend: null,
    showLegendChecked: false,
    mapLimitScales: true,
    mapPreviewAutoHeight: true,
	mapPreviewHeight: 300,
	excludeLayers: ['OpenLayers.Handler.Polygon', 'OpenLayers.Handler.RegularPolygon', 'OpenLayers.Handler.Path', 'OpenLayers.Handler.Point'], // Layer-names to be excluded from Printing, mostly edit-Layers

	legendDefaults: {
		useScaleParameter : true,
		baseParams: {FORMAT: "image/png"}
	},

	initComponent : function() {
		if (this.hropts) {
			Ext.apply(this, this.hropts);
		}

		if (!this.url) {
			alert(__('No print provider url property passed in hropts.'));
			return;
		}

		// Display loading panel
        var busyMask = new Ext.LoadMask(Ext.getBody(), { msg: __('Loading print data...') });
		busyMask.show();

		// Get the print capabilities from Print provider URL
		var self = this;

		Ext.Ajax.request({
			url : this.url + '/info.json',
			method: 'GET',
			params :null,
			success: function (result, request) {
				self.printCapabilities = Ext.decode(result.responseText);
				// Populate forms etc
				self.addItems();
				// Hide loading panel
				busyMask.hide();
			},
			failure: function (result, request) {
				// Hide loading panel
				busyMask.hide();
				alert(__('Error getting Print options from server: ') + this.url);
			}
		});

		Heron.widgets.PrintPreviewWindow.superclass.initComponent.call(this);
	},

	addItems : function() {

		// Only print the legend entries if:
		// - Layer is visible  AND
		// - it should not be hidden (hideInLegend == true) AND
		// - it has not been created
		//
		// See doc for 'Heron.widgets.LayerLegendPanel'
		// Hidden LegendPanel : needed to fetch active legends
		var legendPanel = new Heron.widgets.LayerLegendPanel({
			renderTo: document.body,
			hidden: true,
			defaults: this.legendDefaults
		});

		var self = this;

		var item = new GeoExt.ux.PrintPreview({
			autoHeight: true,
			printMapPanel: {
				// Limit scales to those that can be previewed
				limitScales: this.mapLimitScales,
				// Zooming on the map
				map: {
					controls: [
					new OpenLayers.Control.Navigation({
						zoomBoxEnabled: false,
						zoomWheelEnabled: (this.showRotation) ? true : false
					}),
					// (this.showRotation) ? new OpenLayers.Control.PanZoomBar() : new OpenLayers.Control.PanPanel()
					new OpenLayers.Control.Zoom()
				]
				/* !!! Did not work - zoom slider is NOT shown in the print dialog !!!

				, items: [
					(this.showRotation) ?
						{
							xtype: "gx_zoomslider",
							vertical: true,
							height: 150,    // css => .olControlZoomPanel .olControlZoomOutItemInactive
							x: 18,
							y: 85,
							plugins: new GeoExt.ZoomSliderTip(
									 { template: __("Scale") + ": 1 : {scale}<br>" +
												 __("Zoom") + ": {zoom}" }
							)
						}
					: {}
				]

				*/

				}
			},
			printProvider: {
				// using get for remote service access without same origin
				// restriction. For async requests, we would set method to "POST".
				method: this.method,
				// method: "POST",
				// capabilities from script tag in Printing.html.
				capabilities: this.printCapabilities,
                outputFormatsEnabled: this.showOutputFormats,
				listeners: {
					"print": function() {
						self.close();
					},
					/** api: event[printexception]
					 *  Triggered when using the ``POST`` method, when the print
					 *  backend returns an exception.
					 *
					 *  Listener arguments:
					 *
					 *  * printProvider - :class:`GeoExt.data.PrintProvider` this PrintProvider
					 *  * response - ``Object`` the response object of the XHR
					 */
					"printexception": function(printProvider, result) {
						alert(__('Error from Print server: ') + result.statusText);
					},
					"beforeencodelayer": function (printProvider, layer) {
						// Exclude Layer from Printing if name matches by returning False
						for (var i = 0; i < self.excludeLayers.length; i++) {
							if (layer.name == self.excludeLayers[i]) {
								return false;
							}
						}
						return true;
					}
				}
			},

			sourceMap: this.mapPanel,

            showTitle: this.showTitle,
			mapTitle: this.mapTitle,
			mapTitleYAML: this. mapTitleYAML,

            showComment: this.showComment,
			mapComment: this.mapComment,
			mapCommentYAML: this.mapCommentYAML,

            showFooter: this.showFooter,
			mapFooter: this.mapFooter,
			mapFooterYAML: this.mapFooterYAML,

            printAttribution: this.printAttribution,
			mapAttribution: this.mapAttribution,
			mapAttributionYAML: this.mapAttributionYAML,

            showRotation: this.showRotation,
            showOutputFormats: this.showOutputFormats,

            showLegend: this.showLegend,
			mapLegend: (this.showLegend) ? legendPanel : null,
			showLegendChecked: this.showLegendChecked,

			mapPreviewAutoHeight: this.mapPreviewAutoHeight,
			mapPreviewHeight: this.mapPreviewHeight
		});

		// Add map zoom controls if in rotation mode
		if (this.showRotation) {
			// var ctrlPanel = new OpenLayers.Control.ZoomPanel();
        	var ctrlPanel = new OpenLayers.Control.Zoom();
			item.printMapPanel.map.addControl(ctrlPanel);
		}

		this.add(item);
		this.doLayout();
		this.show();
		this.center();
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
		show: function(node) {
			//
		}
	}

});

/** api: xtype = hr_printpreviewwindow */
Ext.reg('hr_printpreviewwindow', Heron.widgets.PrintPreviewWindow);
