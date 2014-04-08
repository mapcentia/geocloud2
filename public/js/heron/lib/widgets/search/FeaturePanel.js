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
Ext.namespace("Heron.widgets.search");

/** api: (define)
 *  module = Heron.widgets.search
 *  class = FeaturePanel
 *  base_link = `GeoExt.form.FormPanel <http://www.geoext.org/lib/GeoExt/widgets/form/FormPanel.html>`_
 */

/** api: example
 *  Sample code showing how to configure a Heron FeaturePanel. In this case
 *  a popup ExtJS Window is created with a single FeaturePanel (xtype: 'hr_featurepanel').
 *
 *  .. code-block:: javascript
 *
 *      Ext.onReady(function () {
 *          // create a panel and add the map panel and grid panel
 *          // inside it
 *          new Ext.Window({
 *              title: __('Click Map or Grid to Select - Double Click to Zoom to feature'),
 *              layout: "fit",
 *              x: 50,
 *              y: 100,
 *              height: 400,
 *              width: 280,
 *              items: [{
 *                      xtype: 'hr_featurepanel',
 *                      id: 'hr-featurepanel',
 *                      title: __('Parcels'),
 *                      header: false,
 *                      columns: [
 *                          {
 *                              header: "Fid",
 *                              width: 60,
 *                              dataIndex: "id",
 *                              type: 'string'
 *                          },
 *                          {
 *                              header: "ObjectNum",
 *                              width: 180,
 *                              dataIndex: "objectnumm",
 *                              type: 'string'
 *                          }
 *                      ],
 *                      hropts: {
 *                          storeOpts: {
 *                              proxy: new GeoExt.data.ProtocolProxy({
 *                                  protocol: new OpenLayers.Protocol.HTTP({
 *                                      url: 'data/parcels.json',
 *                                      format: new OpenLayers.Format.GeoJSON()
 *                                  })
 *                              }),
 *                              autoLoad: true
 *                          },
 *                          zoomOnRowDoubleClick: true,
 *                          zoomOnFeatureSelect: false,
 *                          zoomLevelPointSelect: 8,
 *                          separateSelectionLayer: true
 *                      }
 *                  }
 *              ]
 *          }).show();
 *      });
 *
 *
 */


/** api: constructor
 *  .. class:: FeaturePanel(config)
 *
 *  Show features both in a grid and on the map and have them selectable.
 */
Heron.widgets.search.FeaturePanel = Ext.extend(Ext.Panel, {

    /** api: config[downloadable]
     *  ``Boolean``
     *  Should the features in the panel be downloadble?
     *  Download can be effected in 3 ways:
     *  1. via Grid export (CSV and XLS only)
     *  2. downloading the original feature format (GML2)
     *  3. (GeoServer only) requesting the server for a triggered download (all Geoserver WFS formats)
     */
    downloadable: true,

    /** api: config[displayPanels]
     *  ``String Array``
     *
     * String array  of types of Panels to display GFI info in, default value is ['Table'], a grid table.
     * Other value is 'Detail', a propertyPanel showing records one by one in a "Vertical" view.
     * If multiple display values are given buttons in the toolbar will be shown to switch display types.
     * First value is the panel to be opened at the first time info is requested
     * Only more than one displayPanel will be available when showTopToolbar = true.
     * Note: The old implementation with 'Tree' and 'XML' was deprecated from v0.75
     */
    displayPanels: ['Table'],

    /** api: config[exportFormats]
     *  ``String Array``
     *
     * Array of document formats to be used when exporting the content of the FeaturePanel. This requires the server-side CGI script
     * ``heron.cgi`` to be installed. Exporting results in a download of a document with the content of the FeaturePanel.
     * For example when 'XLS' is configured, exporting will result in the Excel (or compatible) program to be
     * started with the data in an Excel worksheet.
     * Standard option values are ``CSV``, ``XLS``, ``GMLv2``, ``GeoJSON``, ``Shapefile``, ``WellKnownText``, default is, ``null``,
     * meaning no export (results in no export menu). These configured values select a formatter-object from
     * the ``exportConfigs`` map.
     * Since v0.77 it is also possible to supply your own formatter-objects within this array. For example for
     * additional OGR-formats and/or -projections. Here is an example of a Shapefile in Dutch projection:
     *
     *  .. code-block:: javascript

                       ['GMLv2', 'GeoJSON',{
                             name: 'Esri Shapefile (WGS84)',
                             formatter: 'OpenLayersFormatter',
                             format: 'OpenLayers.Format.GeoJSON',
                             targetFormat: 'ESRI Shapefile',
                             targetSrs: 'EPSG:4326',
                             fileExt: '.zip',
                             mimeType: 'application/zip'
                         }, 'WellKnownText']

     */
    exportFormats: ['CSV', 'XLS', 'GMLv2', 'GeoJSON', 'Shapefile', 'WellKnownText'],

    /** api: config[columnCapitalize]
     *  ``Boolean``
     *  Should the column names be capitalized when autoconfig is true?
     */
    columnCapitalize: true,

    /** api: config[showTopToolbar]
     *  ``Boolean``
     *  Should a top toolbar with feature count, clear button and download combo be shown? Default ``true``.
     */
    showTopToolbar: true,

    /** api: config[showGeometries]
     *  ``Boolean``
     *  Should the feature geometries be shown? Default ``true``.
     */
    showGeometries: true,

    /** api: config[featureSelection]
     *  ``Boolean``
     *  Should the feature geometries that are shown be selectable in grid and map? Default ``true``.
     */
    featureSelection: true,

    loadMask: true,

//	bbar: new Ext.PagingToolbar({
//		pageSize: 25,
//		store: store,
//		displayInfo: true,
//		displayMsg: 'Displaying objects {0} - {1} of {2}',
//		emptyMsg: "No objects to display"
//	}),

    /** api: config[exportConfigs]
     *  ``Object``
     *  The supported configs for formatting and exporting feature data. Actual presented download options
     *  are configured with exportFormats.
     */
    exportConfigs: {
        CSV: {
            name: 'Comma Separated Values (CSV)',
            formatter: 'CSVFormatter',
            fileExt: '.csv',
            mimeType: 'text/csv'
        },
        XLS: {
            name: 'Excel (XLS)',
            formatter: 'ExcelFormatter',
            fileExt: '.xls',
            mimeType: 'application/vnd.ms-excel'
        },
        GMLv2: {
            name: 'GML v2',
            formatter: 'OpenLayersFormatter',
            format: new OpenLayers.Format.GML.v2({featureType: 'heronfeat', featureNS: 'http://heron-mc.org'}),
            fileExt: '.gml',
            mimeType: 'text/xml'
        },
        GeoJSON: {
            name: 'GeoJSON',
            formatter: 'OpenLayersFormatter',
            format: 'OpenLayers.Format.GeoJSON',
            fileExt: '.json',
            mimeType: 'text/plain'
        },
        /** NB relies on server-side conversion, e.g. heron.cgi with ogr2ogr. */
        Shapefile: {
            name: 'Esri Shapefile',
            formatter: 'OpenLayersFormatter',
            format: 'OpenLayers.Format.GeoJSON',
            targetFormat: 'ESRI Shapefile',
            fileExt: '.zip',
            mimeType: 'application/zip'
        },
        WellKnownText: {
            name: 'Well-known Text (WKT)',
            formatter: 'OpenLayersFormatter',
            format: 'OpenLayers.Format.WKT',
            fileExt: '.wkt',
            mimeType: 'text/plain'
        }
    },

    /** api: config[separateSelectionLayer]
     *  ``Boolean``
     *  Should selected features be managed in separate overlay Layer (handy for printing) ?.
     */
    separateSelectionLayer: false,

    /** api: config[zoomOnFeatureSelect]
     *  ``Boolean``
     *  Zoom to feature (extent) when selected ?.
     */
    zoomOnFeatureSelect: false,

    /** api: config[zoomOnRowDoubleClick]
     *  ``Boolean``
     *  Zoom to feature (extent) when row is double clicked ?.
     */
    zoomOnRowDoubleClick: true,

    /** api: config[zoomLevelPointSelect]
     *  ``Integer``
     *  Zoom level for point features when selected, default ``10``.
     */
    zoomLevelPointSelect: 10,

    /** api: config[zoomLevelPoint]
     *  ``Integer``
     *  Zoom level when layer is single point feature, default ``10``.
     */
    zoomLevelPoint: 10,

    /** api: config[zoomToDataExtent]
     *  ``Boolean``
     *  Zoom to layer data extent when loaded ?.
     */
    zoomToDataExtent: false,

    /** api: config[autoConfig]
     *  ``Boolean``
     *  Should the store and grid columns autoconfigure from loaded features?.
     */
    autoConfig: true,

    /** api: config[autoConfigMaxSniff]
     *  ``Integer``
     *  Maximum number of features to 'sniff' for autoconfigured grid columns (as null columns are often not sent by server).
     */
    autoConfigMaxSniff: 40,

    /** api: config[hideColumns]
     *  ``Array``
     *  An array of column names from WFS and WMS GetFeatureInfo results that should be removed and not shown to the user.
     */
    hideColumns: [],

    /** api: config[columnFixedWidth]
     *  ``Integer``
     *  The width of a column in a grid response
     */
    columnFixedWidth: 100,

    /** api: config[autoMaxWidth]
     *  ``Integer``
     *  The maximum width of a auto adjusted column grid response. Setting to 0 will disable auto column width detection
     */
    autoMaxWidth: 300,

    /** api: config[autoMinWidth]
     *  ``Integer``
     *   The minimum width of a auto adjusted column. Requires autoMaxWidth to be > 1 to function.
     */
    autoMinWidth: 45,

    /** api: config[vectorLayerOptions]
     *  ``Object``
     *  Options to be passed on Vector constructors.
     */
    vectorLayerOptions: {noLegend: true, displayInLayerSwitcher: false},

    // Initialize vars
    tableGrid: null,
    propGrid: null,
    mainPanel: null,
    store: null,

    initComponent: function () {

        // Fit components
        Ext.apply(this, {
            layout: "fit"
        });

        // If columns specified we don't do autoconfig (column guessing from features)
        if (this.columns) {
            this.autoConfig = false;
        }

        // Heron-specific config (besides GridPanel config)
        Ext.apply(this, this.hropts);

        // If we have feature selection enabled we must show geometries
        if (this.featureSelection) {
            this.showGeometries = true;
        }

        if (this.showGeometries) {
            // Define OL Vector Layer to display search result features
            var layer = this.layer = new OpenLayers.Layer.Vector(this.title, this.vectorLayerOptions);

            this.map = Heron.App.getMap();
            this.map.addLayer(this.layer);

            var self = this;
            if (this.featureSelection && this.zoomOnFeatureSelect) {
                // See http://www.geoext.org/pipermail/users/2011-March/002052.html
                layer.events.on({
                    "featureselected": function (e) {
                        self.zoomToFeature(self, e.feature.geometry);
                    },
                    "dblclick": function (e) {
                        self.zoomToFeature(self, e.feature.geometry);
                    },
                    "scope": layer
                });
            }

            if (this.separateSelectionLayer) {
                this.selLayer = new OpenLayers.Layer.Vector(this.title + '_Sel', {noLegend: true, displayInLayerSwitcher: false});
                // selLayer.style = layer.styleMap.styles['select'].clone();
                this.selLayer.styleMap.styles['default'] = layer.styleMap.styles['select'];
                this.selLayer.style = this.selLayer.styleMap.styles['default'].defaultStyle;
                // this.selLayer.style = layer.styleMap.styles['select'].clone();
                layer.styleMap.styles['select'] = layer.styleMap.styles['default'].clone();
                layer.styleMap.styles['select'].defaultStyle.fillColor = 'white';
                layer.styleMap.styles['select'].defaultStyle.fillOpacity = 0.0;
                this.map.addLayer(this.selLayer);
                this.map.setLayerIndex(this.selLayer, this.map.layers.length - 1);
                this.layer.events.on({
                    featureselected: this.updateSelectionLayer,
                    featureunselected: this.updateSelectionLayer,
                    scope: this
                });
            }
        }

        this.setupStore(this.features);


        // Will take effort to support paging...
        // http://dev.sencha.com/deploy/ext-3.3.1/examples/grid/paging.html
        /*		this.bbar = new Ext.PagingToolbar({
         pageSize: 25,
         store: this.store,
         displayInfo: true,
         displayMsg: 'Displaying objects {0} - {1} of {2}',
         emptyMsg: "No objects to display"
         });
         */

        // Enables the interaction between features on the Map and Grid
        if (this.featureSelection && !this.sm) {
            this.sm = new GeoExt.grid.FeatureSelectionModel();
        }

        if (this.showTopToolbar) {
            this.tbar = this.createTopToolbar();
        }

        Heron.widgets.search.FeaturePanel.superclass.initComponent.call(this);

        // Always create table grid with the selection model for interaction with the map
        // just do not activate when grid is not in config
        // Add featureSetKey to the id, otherwise cardpanels get mixed up with more tabs

        this.tableGrid = new Ext.grid.GridPanel ({
            id: 'grd_Table'+ '_' + this.featureSetKey,
            store: this.store,
            title: this.title,
            autoScroll: true,
            featureType: this.featureType,
            header: false,
            features: this.features,
            autoConfig: this.autoConfig,
            autoConfigMaxSniff: this.autoConfigMaxSniff,
            //autoHeight: true,
            hideColumns: this.hideColumns,
			columnFixedWidth: this.columnFixedWidth,
			autoMaxWidth: this.autoMaxWidth,
			autoMinWidth: this.autoMinWidth,
            columnCapitalize: this.columnCapitalize,
            showGeometries: this.showGeometries,
            featureSelection: this.featureSelection,
            gridCellRenderers: this.gridCellRenderers,
            columns: this.columns,
            showTopToolbar: this.showTopToolbar,
            exportFormats: this.exportFormats,
            hropts: {
                zoomOnRowDoubleClick: true,
                zoomOnFeatureSelect: false,
                zoomLevelPointSelect: 8
            },
            // Enable the interaction between features on the Map and Grid
            //sm : new GeoExt.grid.FeatureSelectionModel()
            sm: this.sm
        });

        // May zoom to feature when grid row is double-clicked.
        if (this.zoomOnRowDoubleClick) {
            this.tableGrid.on('celldblclick', function (grid, rowIndex, columnIndex, e) {
                var record = grid.getStore().getAt(rowIndex);
                var feature = record.getFeature();
                self.zoomToFeature(self, feature.geometry);
            });
        }
        // Open detail view when click on detail cell
        // Only available when Table and Detail panel are set in config
        if ((this.displayPanels.indexOf('Table')>=0) && (this.displayPanels.indexOf('Detail')>=0)) {
            this.tableGrid.on('cellclick', function (grid, rowIndex, columnIndex, e) {
                if (columnIndex == 0) {
                    self.displayVertical ('goto', rowIndex);
                }
            });
        }

        // Create propertyGrid panel if requested
        if (this.displayPanels.indexOf('Detail')>=0) {
            this.propGrid = new Ext.grid.PropertyGrid ({
                id: 'grd_Detail'+ '_' + this.featureSetKey,
                listeners: { 'beforeedit': function (e) { return false; } },
                title: this.title,
                featureType: this.featureType,
                header: false,
                features: this.features,
                autoConfig: this.autoConfig,
                autoConfigMaxSniff: this.autoConfigMaxSniff,
                autoHeight: false,
                hideColumns: this.hideColumns,
				columnFixedWidth: this.columnFixedWidth,
				autoMaxWidth: this.autoMaxWidth,
				autoMinWidth: this.autoMinWidth,
                columnCapitalize: this.columnCapitalize,
                showGeometries: this.showGeometries,
                featureSelection: this.featureSelection,
                gridCellRenderers: this.gridCellRenderers,
                columns: this.columns,
                showTopToolbar: this.showTopToolbar,
                exportFormats: this.exportFormats,
                curRecordNr: 0,
                hropts: {
                    zoomOnRowDoubleClick: true,
                    zoomOnFeatureSelect: false,
                    zoomLevelPointSelect: 8
                }});
        }

        // Create array with panels to display
        this.cardPanels = [];
        if (this.tableGrid)
            this.cardPanels.push(this.tableGrid);
        if (this.propGrid)
            this.cardPanels.push(this.propGrid);

        // Set active panel/card at startup
        var activeItem = 0;
        if (this.displayPanels.length>0) {
            activeItem = 'grd_' + this.displayPanels[0] + '_' + this.featureSetKey;
        }

        // Create main panel with card layout
        this.mainPanel = new Ext.Panel({
            border: false,
            activeItem: activeItem,
            layout: "card",
            items: this.cardPanels
        });

        // Add main panel
        this.add(this.mainPanel);
        if ((this.showTopToolbar) && (this.displayPanels.indexOf('Table')>=0) && (this.displayPanels.indexOf('Detail')>=0)) {
            this.tableGrid.addListener("activate", this.onActivateTable, this);
            this.propGrid.addListener("activate", this.onActivateDetail, this);
            this.tableGrid.addListener("afterlayout", this.onAfterlayoutTable, this);
            this.propGrid.addListener("afterlayout", this.onAfterlayoutDetail, this);
            this.topToolbar.addListener("afterlayout", this.onAfterlayoutTopToolbar, this);
        }

        // ExtJS licycle events
        this.addListener("afterrender", this.onPanelRendered, this);
        this.addListener("show", this.onPanelShow, this);
        this.addListener("hide", this.onPanelHide, this);
    },
    /** private: activateDisplayPanel (name)
     *  :param name: Detail, Table
     *  activate the displaypanel of choice
     */
    activateDisplayPanel: function (name) {
        // Main panel layout not yet available?
        // At least not available first time called.
        if (!this.mainPanel.getLayout().setActiveItem) {
            return;
        }
        // Show displaypanel.
        this.mainPanel.getLayout().setActiveItem("grd_"+name);
    },

    /** api: method[createTopToolbar]
     * Create the top toolbar.
     */
    createTopToolbar: function () {

        // Top toolbar text, keep var for updating
        var tbarItems = [this.tbarText = new Ext.Toolbar.TextItem({itemId: 'result',text: __(' ')})];
        //var blnArrows = false;
        tbarItems.push('->');

        if (this.downloadable) {

            // Multiple display types configured: add toolbar tabs
            // var downloadMenuItems = ['<b class="menu-title">' + __('Choose an Export Format') + '</b>'];
            var downloadMenuItems = [];
            var item;
            for (var j = 0; j < this.exportFormats.length; j++) {
                var exportFormat = this.exportFormats[j];

                // Get config from preconfigured configs or explicit config object in array
                var exportFormatConfig = exportFormat instanceof Object ? exportFormat : this.exportConfigs[exportFormat];
                if (!exportFormatConfig) {
                    Ext.Msg.alert(__('Warning'), __('Invalid export format configured: ' + exportFormat));
                    continue;
                }

                item = {
                    text: __('as') + ' ' + exportFormatConfig.name,
                    cls: 'x-btn',
                    iconCls: 'icon-table-export',
                    scope: this,
                    exportFormatConfig: exportFormatConfig,
                    handler: function (evt) {
                        this.exportData(evt.exportFormatConfig);
                    }
                };
                downloadMenuItems.push(item);
            }

            if (this.downloadInfo && this.downloadInfo.downloadFormats) {
                var downloadFormats = this.downloadInfo.downloadFormats;
                for (var k = 0; k < downloadFormats.length; k++) {
                    var downloadFormat = downloadFormats[k];
                    item = {
                        text: __('as') + ' ' + downloadFormat.name,
                        cls: 'x-btn',
                        iconCls: 'icon-table-export',
                        downloadFormat: downloadFormat.outputFormat,
                        fileExt: downloadFormat.fileExt,
                        scope: this,
                        handler: function (evt) {
                            this.downloadData(evt.downloadFormat, evt.fileExt);
                        }
                    };
                    downloadMenuItems.push(item);
                }
            }

            if (downloadMenuItems.length > 0) {
                /* Add to toolbar. */
                tbarItems.push({
                    itemId: 'download',
                    text: __('Download'),
                    cls: 'x-btn-text-icon',
                    iconCls: 'icon-table-save',
                    tooltip: __('Choose a Download Format'),
                    menu: new Ext.menu.Menu({
                        style: {
                            overflow: 'visible'	 // For the Combo popup
                        },
                        items: downloadMenuItems
                    })
                });
            }
        }

        // Show displaypanel buttons only with both 'Table' and 'Detail'
        // options are set
        //if ((this.displayPanels.indexOf('Table')>=0) && (this.displayPanels.indexOf('Detail')>=0)) {
        if ((this.showTopToolbar) && (this.displayPanels.indexOf('Table')>=0) && (this.displayPanels.indexOf('Detail')>=0)) {
            // Add 'Table'/'Detail' button
            var blnTable = (this.displayPanels.indexOf('Detail') == 0);
            tbarItems.push('->');
            tbarItems.push({
                itemId: 'table-detail',
                text: (blnTable) ? __('Table') : __('Detail'),
                cls: 'x-btn-text-icon',
                iconCls: (blnTable) ? 'icon-table' : 'icon-detail',
                tooltip: (blnTable) ? __('Show record(s) in a table grid') : __('Show single record'),
                enableToggle: true,
                pressed: false,
                scope: this,
                handler: function (btn) {
                    if (btn.pressed) {

                        if  (btn.iconCls == 'icon-table') {
                            // change view to table
                            this.displayGrid();
                        } else {
                            // change view to detail
                            var selRecord = Ext.data.Record;
                            selRecord = this.tableGrid.selModel.getSelected();
                            if (selRecord){
                                var selIndex = this.tableGrid.store.indexOf(selRecord);
                                this.displayVertical('goto', selIndex);
                            }
                            else {
                                this.displayVertical('first');
                            }
                        }
                    // Changing the button to the other icon etc. is handled in onActivate events
                    // Here we only set the button unpressed
                    btn.toggle(false,false);
                    }
                }
            });

        }

        // ------

        tbarItems.push('->');
        tbarItems.push({
            itemId: 'clear',
            text: __('Clear'),
            cls: 'x-btn-text-icon',
            iconCls: 'icon-table-clear',
            tooltip: __('Remove all results'),
            scope: this,
            handler: function () {
                this.removeFeatures();
            }
        });
        if ((this.showTopToolbar) && (this.displayPanels.indexOf('Detail')>=0)) {
            // insert buttons for paging through Detail records

            tbarItems.push('->');
            tbarItems.push({
                itemId: 'nextrec',
                text: __(''),
                cls: 'x-btn-text-icon',
                iconCls: 'icon-arrow-right',
                tooltip: __('Show next record'),
                scope: this,
                //hidden: true,
                visible: false,
                disabled: true,
                handler: function () {
                    this.displayVertical('next');
                }
            });
            tbarItems.push('->');
            tbarItems.push({
                itemId: 'prevrec',
                text: __(''),
                cls: 'x-btn-text-icon',
                iconCls: 'icon-arrow-left',
                tooltip: __('Show previous record'),
                scope: this,
                hidden: true,
                disabled: true,
                handler: function () {
                    this.displayVertical('previous');
                }
            });
        }
        return new Ext.Toolbar({enableOverflow: true, items: tbarItems});
    },

    /** private: displayGrid ()
     *  display attributes as a table (grid)
     *  uses a Grid for attributes
     */
    displayGrid: function () {
        this.activateDisplayPanel('Table'+ '_' + this.featureSetKey);
        this.updateTbarText ('table');
    },
    /** private: displayVertical (action, intRecNew)
     *  :param action: first, goto, previous, next
     *  :param intRecNew: the recordnumber to goto
     *  display attributes in vertical view (detail)
     *  uses a propertyGrid for attributes
     */
    displayVertical: function (action, intRecNew) {
        var column;
        var objCount = this.tableGrid.store ? this.tableGrid.store.getCount() : 0;


        if (objCount > 0) {

            switch (action) {
                case 'first':
                    this.propGrid.curRecordNr = 0;
                    break;
                case'goto':
                    this.propGrid.curRecordNr = intRecNew;
                    break;
                case 'previous':
                    this.propGrid.curRecordNr--;
                    break;
                case'next':
                    this.propGrid.curRecordNr++;
                    break;
            }

            //var sourceStore = this.tableGrid.store.data.items[this.propGrid.curRecordNr].data.feature.attributes;
            var sourceStore = this.mainPanel.items.items[0].store.data.items[this.propGrid.curRecordNr].data.feature.attributes;

            this.propGrid.store.removeAll();

            for (var c = 0; c < this.columns.length; c++) {
                column = this.columns[c];
                if (column.dataIndex) {
                    var rec = new Ext.grid.PropertyRecord({
                        name: column.header,
                        value: sourceStore[column.dataIndex]
                    });
                    this.propGrid.store.add(rec);
                }
            }
            // Set selected row in the table grid so the selection is updated in the map
            if (action != 'first'){
                // first time when called from loadFeatures, selectRow is not possible
                this.tableGrid.selModel.selectRow(this.propGrid.curRecordNr, false);
            }
        }

        // this does not work in onActivateDetail event because of curRecordNr
        if (objCount > 1) {
            this.topToolbar.items.get('prevrec').show();
            this.topToolbar.items.get('nextrec').show();

            if (this.propGrid.curRecordNr == objCount -1) {
                this.topToolbar.items.get('prevrec').setDisabled (false);
                this.topToolbar.items.get('nextrec').setDisabled (true);
            }
            else if (this.propGrid.curRecordNr == 0) {
                this.topToolbar.items.get('prevrec').setDisabled (true);
                this.topToolbar.items.get('nextrec').setDisabled (false);
            }
            else {
                this.topToolbar.items.get('prevrec').setDisabled (false);
                this.topToolbar.items.get('nextrec').setDisabled (false);
            }
        } else {
            this.topToolbar.items.get('prevrec').hide();
            this.topToolbar.items.get('nextrec').hide();
        }

        this.activateDisplayPanel('Detail'+ '_' + this.featureSetKey);
        this.updateTbarText ('detail');
    },

    /** api: method[loadFeatures]
     * Loads array of feature objects in store and shows them on grid and map.
     */
    loadFeatures: function (features, featureType) {
        this.removeFeatures();
        this.featureType = featureType;

        // Defensive programming
        if (!features || features.length == 0) {
            return;
        }

        this.showLayer();
        this.store.loadData(features);
        //this.updateTbarText(this.displayPanels[0].toLowerCase());

        // Whenever Paging is supported...
        // http://dev.sencha.com/deploy/ext-3.3.1/examples/grid/paging.html
        // this.store.load({params:{start:0, limit:25}});

        if (this.zoomToDataExtent) {
            if (features.length == 1 && features[0].geometry.CLASS_NAME == "OpenLayers.Geometry.Point") {
                var point = features[0].geometry.getCentroid();
                this.map.setCenter(new OpenLayers.LonLat(point.x, point.y), this.zoomLevelPoint);
            } else if (this.layer) {
                this.map.zoomToExtent(this.layer.getDataExtent());
            }
        }

        // Set the display on the first mentioned panel.
        if (this.displayPanels.length>0) {
            if (this.displayPanels[0]=='Table')
                this.displayGrid();
            else if (this.displayPanels[0]=='Detail'){
                this.displayVertical('first');
            }
        }
    },

    /** api: method[hasFeatures]
     * Does this Panel have features?.
     */
    hasFeatures: function () {
        return this.store && this.store.getCount() > 0;
    },

    /** api: method[removeFeatures]
     * Removes all feature objects from store .
     */
    removeFeatures: function () {
        if (this.store) {
            this.store.removeAll(false);
        }
        if ((this.propGrid) && (this.propGrid.store)) {
            this.propGrid.store.removeAll();
        }
        if (this.selLayer) {
            this.selLayer.removeAllFeatures({silent: true});
        }
        this.updateTbarText();
        if ((this.topToolbar) && (this.topToolbar.items.get('prevrec'))){
            this.topToolbar.items.get('prevrec').hide();
            this.topToolbar.items.get('nextrec').hide();
        }

    },

    /** api: method[showLayer]
     * Show the layer with features on the map.
     */
    showLayer: function () {
        // this.removeFeatures();
        if (this.layer) {
            if (this.selLayer) {
                this.map.setLayerIndex(this.selLayer, this.map.layers.length - 1);
                this.map.setLayerIndex(this.layer, this.map.layers.length - 2);
            } else {
                this.map.setLayerIndex(this.layer, this.map.layers.length - 1);
            }
            if (!this.layer.getVisibility()) {
                this.layer.setVisibility(true);
            }
            if (this.selLayer && !this.selLayer.getVisibility()) {
                this.selLayer.setVisibility(true);
            }
        }
    },

    /** api: method[hideLayer]
     * Hide the layer with features on the map.
     */
    hideLayer: function () {
        // this.removeFeatures();
        if (this.layer && this.layer.getVisibility()) {
            this.layer.setVisibility(false);
        }
        if (this.selLayer && this.selLayer.getVisibility()) {
            this.selLayer.setVisibility(false);
        }
    },

    /** api: method[hideLayer]
     * Hide the layer with features on the map.
     */
    zoomToFeature: function (self, geometry) {
        if (!geometry) {
            return;
        }

        // For point features center map otherwise zoom to geometry bounds
        if (geometry.getVertices().length == 1) {
            var point = geometry.getCentroid();
            self.map.setCenter(new OpenLayers.LonLat(point.x, point.y), self.zoomLevelPointSelect);
        } else {
            self.map.zoomToExtent(geometry.getBounds());
        }
    },

    zoomButtonRenderer: function () {
        var id = Ext.id();

        (function () {
            new Ext.Button({
                renderTo: id,
                text: 'Zoom'
            });

        }).defer(25);

        return (String.format('<div id="{0}"></div>', id));
    },

    /** private: method[setupStore]
     *  :param features: ``Array`` optional features.
     */
    setupStore: function (features) {
        if (this.store && !this.autoConfig) {
            return;
        }

        // Prepare fields array for store from columns in Grid config.
        var storeFields = [];
        var column;
        this.columns = this.columns == null ? [] : this.columns;
        var blnBtnExists = false;
        // some way indexOf does not work on this one
        if ((this.columns[0] != null) && (this.columns[0].id == 'btn_detail'))
            blnBtnExists = true;

        if ((this.showTopToolbar) && (this.displayPanels.indexOf('Detail')>=0) &&
            (blnBtnExists == false)) {
            // First add column for details button (+)
            var columnDetail = new Ext.grid.Column ({
                id: 'btn_detail',
                header: '',
                width: 20,
                tooltip: __('Show single record'),
                renderer: function (value, metadata, record, rowindex) {
                    return ('+');
                }
            });
            // Be sure this column is first column so use splice not push
            this.columns.splice(0,0,columnDetail);
        }

        if (this.autoConfig && features) {

            var columnsFound = {};
            var columnsWidth = {};
            var suppressColumns = this.hideColumns.toString().toLowerCase();
			var defaultColumnWidth = this.columnFixedWidth;
			var autoMaxWidth = this.autoMaxWidth;
			var autoMinWidth = this.autoMinWidth;
            var arrLen = features.length <= this.autoConfigMaxSniff ? features.length : this.autoConfigMaxSniff;

			//Hardcoded constant of number of pixels width per character.
			//Widths could be better calculated - http://stackoverflow.com/questions/118241/calculate-text-width-with-javascript
			//In particular using - http://docs.sencha.com/extjs/3.4.0/#!/api/Ext.util.TextMetrics
			var pixelsPerCharacter = 7

            for (var i = 0; i < arrLen; i++) {
                var feature = features[i];
                var fieldName;
                var position = -1;

                for (fieldName in feature.attributes) {

                    // If we find a non-null attribute in any other than the first feature try to place column at right position
                    if (i > 0) {
                        position++;
                    }
                    // If already "sniffed" or if a column we're ignoring, continue
                    if (columnsFound[fieldName] || suppressColumns.indexOf(fieldName.toLowerCase()) !== -1) {
						continue;
                    }

                    // Capitalize header names for table grid
                    column = {
                        header: this.columnCapitalize ? fieldName.substr(0, 1).toUpperCase() + fieldName.substr(1).toLowerCase() : fieldName,
                        width: defaultColumnWidth,
                        dataIndex: fieldName,
                        sortable: true
                    };

                    // Look for custom rendering
                    if (this.gridCellRenderers && this.featureType) {
                        var gridCellRenderer;
                        for (var k = 0; k < this.gridCellRenderers.length; k++) {
                            gridCellRenderer = this.gridCellRenderers[k];
                            if (gridCellRenderer.attrName && fieldName == gridCellRenderer.attrName) {
                                if (gridCellRenderer.featureType && this.featureType == gridCellRenderer.featureType || !gridCellRenderer.featureType) {
                                    column.options = gridCellRenderer.renderer.options;
                                    column.renderer = gridCellRenderer.renderer.fn;
                                }
                            }
                        }
                    }

					//Auto-detect column widths when enabled.
					if(autoMaxWidth > 0){
						//Populate new fields with width of fieldname itself
						if(!(fieldName in columnsWidth)) {
							columnsWidth[fieldName] = fieldName.length * pixelsPerCharacter;

							//Set to minimum if necessary
							if(columnsWidth[fieldName] < autoMinWidth){
								columnsWidth[fieldName] = autoMinWidth;
							}
						}

						// Calculate column width from data value if any, and populate array if necessary.
                        if (feature.attributes[fieldName]) {
                            var columnWidth = feature.attributes[fieldName].length;

                            // Take pretext of gridCellRenderer into account
                            if (typeof(column.options) !== "undefined" && (typeof(column.options.attrPreTxt) !== "undefined") ){
                                columnWidth = columnWidth + column.options.attrPreTxt.length
                            }
                            columnWidth = columnWidth * pixelsPerCharacter;
                            if(columnWidth > columnsWidth[fieldName] && columnWidth <= autoMaxWidth) {
                                columnsWidth[fieldName] = columnWidth;
                            }
                        }
					}

                    if (position >= 0 && position < this.columns.length) {
                        // If we found a non-null attribute in any other than the first feature try to place column at right position
                        this.columns.splice(position, 0, column);
                    } else {
                        this.columns.push(column);
                    }
                    storeFields.push({name: column.dataIndex});
                    columnsFound[fieldName] = fieldName;
                }
            }

			//Set the column width to the Auto Detected width.
			if(autoMaxWidth > 0){
				for(var key in this.columns){
                    if (columnsWidth[this.columns[key].dataIndex])
                        this.columns[key].width = columnsWidth[this.columns[key].dataIndex];
				}
			}
        } else {
            for (var c = 0; c < this.columns.length; c++) {
                column = this.columns[c];
                if (column.dataIndex) {
                    storeFields.push({name: column.dataIndex, type: column.type});
                }
                column.sortable = true;
            }
        }

        // this.columns.push({ header: 'Zoom', width: 60, sortable: false, renderer: self.zoomButtonRenderer });

        // Define the Store
        var storeConfig = {layer: this.layer, fields: storeFields};

        // Optional extra store options in config
        Ext.apply(storeConfig, this.hropts.storeOpts);

        this.store = new GeoExt.data.FeatureStore(storeConfig);
    },

    /** private: method[updateSelectionLayer]
     *  :param evt: ``Object`` An object with a feature property referencing
     *                         the selected or unselected feature.
     */
    updateSelectionLayer: function (evt) {
        if (!this.showGeometries) {
            return;
        }
        this.selLayer.removeAllFeatures({silent: true});
        var features = this.layer.selectedFeatures;
        for (var i = 0; i < features.length; i++) {
            var feature = features[i].clone();
            this.selLayer.addFeatures(feature);
        }
    },

    /** private: method[onActivateTable]
     * Called after our panel is shown.
     */
    onActivateTable: function () {
        this.topToolbar.items.get('prevrec').hide();
        this.topToolbar.items.get('nextrec').hide();
        var btn = this.topToolbar.items.get('table-detail');
        // set button to detail
        btn.setText (__('Detail'));
        btn.setIconClass ('icon-detail');
        btn.setTooltip (__('Show single record'));
    },
    /** private: method[onActivateDetail]
     * Called after our panel is shown.
     */
    onActivateDetail: function () {
        var btn = this.topToolbar.items.get('table-detail');
        // set button to table
        btn.setText (__('Table'))
        btn.setIconClass ('icon-table');
        btn.setTooltip (__('Show record(s) in a table grid'));
        // show in the map
        this.tableGrid.selModel.selectRow(this.propGrid.curRecordNr, false);
    },

    /** api: method[onAfterlayoutTable]
     *  Called when Panel has been rendered.
     */
    onAfterlayoutTable: function () {
        this.activePanel = 'Table';
   },

    /** api: method[onAfterlayoutTable]
     *  Called when Panel has been rendered.
     */
    onAfterlayoutDetail: function () {
        this.activePanel = 'Detail';
    },

    /** api: method[onAfterlayoutTopToolbar]
     *  Called when Panel has been rendered.
     */
    onAfterlayoutTopToolbar: function () {
        // Manage arrow buttons here because AfterlayoutDetail is too early
        // for hiding the buttons for the first time
        var objCount = this.tableGrid.store ? this.tableGrid.store.getCount() : 0;
        if ((this.activePanel == 'Table') || (objCount <= 1)){
            this.topToolbar.items.get('prevrec').hide();
            this.topToolbar.items.get('nextrec').hide();
        } else {
            // 'Detail'
            this.topToolbar.items.get('prevrec').show();
            this.topToolbar.items.get('nextrec').show();
        }
    },

    /** api: method[onPanelRendered]
     *  Called when Panel has been rendered.
     */
    onPanelRendered: function () {
        if (this.ownerCt) {
            this.ownerCt.addListener("parenthide", this.onParentHide, this);
            this.ownerCt.addListener("parentshow", this.onParentShow, this);
        }
    },

    /** private: method[onPanelShow]
     * Called after our panel is shown.
     */
    onPanelShow: function () {
        if (this.selModel && this.selModel.selectControl) {
            this.selModel.selectControl.activate();
        }
    },

    /** private: method[onPanelHide]
     * Called  before our panel is hidden.
     */
    onPanelHide: function () {
        if (this.selModel && this.selModel.selectControl) {
            this.selModel.selectControl.deactivate();
        }
    },

    /** private: method[onParentShow]
     * Called usually before our panel is created.
     */
    onParentShow: function () {
        this.showLayer();
    },

    /** private: method[onParentHide]
     * Cleanup usually before our panel is hidden.
     */
    onParentHide: function () {
        this.removeFeatures();
        this.hideLayer();
    },

    /** private: method[cleanup]
     * Cleanup usually before our panel is destroyed.
     */
    cleanup: function () {
        this.removeFeatures();
        if (this.selModel && this.selModel.selectControl) {
            this.selModel.selectControl.deactivate();
            this.selModel = null;
        }

        if (this.layer) {
            this.map.removeLayer(this.layer);
        }

        if (this.selLayer) {
            this.map.removeLayer(this.selLayer);
        }
        return true;
    },

    /** private: method[updateTbarText]
     * Update text message in top toolbar.
     */
    updateTbarText: function (type) {
        if (!this.tbarText) {
            return;
        }
        var objCount = this.store ? this.store.getCount() : 0;
        if ((type) && (type == 'detail') && (objCount > 0))
            this.tbarText.setText(__('Result') + ' ' + (this.propGrid.curRecordNr + 1) + ' ' + __('of') + ' ' + objCount);
        else
            this.tbarText.setText(objCount + ' ' + (objCount != 1 ? __('Results') : __('Result')));
    },

    /** private: method[exportData]
     * Callback handler function for exporting and downloading the data to specified format.
     */
    exportData: function (config) {

        var store = this.store;

        // Create the filename for download
        var featureType = this.featureType ? this.featureType : 'heron';
        config.fileName = featureType + config.fileExt;

        // Use only the columns from the original data, not the internal feature store columns
        // 'fid', 'state' and the feature object in this, see issue 181. These are the first 3 fields in
        // a GeoExt FeatureStore.
        config.columns = (store.fields && store.fields.items && store.fields.items.length > 3) ? store.fields.items.slice(3) : null;

        if (store.layer && store.layer.projection) {
            config.assignSrs = store.layer.projection.getCode();
        }

        // Format the feature or grid data to chosen format and force user-download
        // Always use Base64 encoding
        config.encoding = 'base64';
        var data = Heron.data.DataExporter.formatStore(store, config);
        Heron.data.DataExporter.download(data, config);
    },

    /** private: method[downloadData]
     * Callback handler function for direct downloading the data in specified format.
     */
    downloadData: function (downloadFormat, fileExt) {

        var downloadInfo = this.downloadInfo;
        downloadInfo.params.outputFormat = downloadFormat;
        downloadInfo.params.filename = downloadInfo.params.typename + fileExt;

        var paramStr = OpenLayers.Util.getParameterString(downloadInfo.params);

        var url = OpenLayers.Util.urlAppend(downloadInfo.url, paramStr);
        if (url.length > 2048) {
            Ext.Msg.alert(__('Warning'), __('Download URL string too long (max 2048 chars): ') + url.length);
            return;
        }

        // Force user-download
        Heron.data.DataExporter.directDownload(url);
    }

});

/** api: xtype = hr_featurepanel */
Ext.reg('hr_featurepanel', Heron.widgets.search.FeaturePanel);

/** Old, compat with pre-1.0.1 name. */
Ext.reg('hr_featuregridpanel', Heron.widgets.search.FeaturePanel);

/** Old, compat with pre-0.72 name. */
Ext.reg('hr_featselgridpanel', Heron.widgets.search.FeaturePanel);