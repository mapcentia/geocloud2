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
 *  class = CoordSearchPanel
 *  base_link = `Ext.form.FormPanel <http://docs.sencha.com/ext-js/3-4/#!/api/Ext.form.FormPanel>`_
 */

/** api: example
 *  Sample code showing how to use the CoordSearchPanel.
 *
 *  .. code-block:: javascript
 *
 *      var panel = new Heron.widgets.search.CoordSearchPanel({
 *                  xtype: 'hr_coordsearchpanel',
 *                  id: 'hr-coordsearchpanelRD',
 *                  title: 'Go to Coordinates (Dutch RD)',
 *                  height: 150,
 *                  border: true,
 *                  collapsible: true,
 *                  collapsed: true,
 *                  onZoomLevel: 8,
 *                  hropts: [ {
 *                              projEpsg: 'EPSG:28992',
 *                              projDesc: 'Amersfoort / RD New',
 *                              localIconFile: 'redpin.png',
 *                              fieldLabelX: 'x (Dutch RD)',
 *                              fieldLabelY: 'y (Dutch RD)'
 *                            }
 *                          ]
 *          }
 *      });
 *
 *  IMPORTANT
 *  If using the zoom option (showZoom: true) the global 'map' must already be
 *  initialized - otherwise the zoom combo box will not contain any scale values!
 *  This happens for example if the 'hr_coordsearchpanel' is defined BEFORE the 
 *  'hr_mappanel'.
 *  The solution is: ALWAYS define the 'hr_mappanel' as the FIRST element in the
 *  layout tree - this ensures that the 'map' is initialized properly. 
 *  See 'coordsearch' demo - here is the right coding scheme:
 *
 *  .. code-block:: javascript
 *
 *		 Heron.layout = {
 *			xtype: 'panel',
 *			id: 'hr-container-main',
 *
 *			items: [ {
 *				xtype: 'panel',
 *				id: 'hr-map-and-info-container',
 *				layout: 'border',
 *				region: 'center',
 *				width: '100%',
 *				collapsible: true,
 *				split: true,
 *				border: false,
 *				items: [ {
 *					xtype: 'hr_mappanel',					// FIRST
 *					id: 'hr-map',
 *					region: 'center',
 *					collapsible : false,
 *					border: false,
 *					hropts: Heron.options.map
 *				} ]
 *				},		
 *				{		
 *				xtype: 'panel',
 *				id: 'hr-menu-left-container',
 *				layout: 'accordion',
 *				region: "west",
 *				width: 270,
 *				collapsible: true,
 *				split: true,
 *				border: false,
 *				items: [ {
 *					xtype: 'hr_coordsearchpanel',			//  SECOND
 *					id: 'hr-coordsearchpanel',
 *					title: 'Go to Coordinates (Lon/Lat)',
 *					height: 150,
 *					border: true,
 *					collapsible: true,
 *					collapsed: false,
 *					fieldLabelWidth: 50,
 *					onZoomLevel: 6,
 *					showZoom: true,
 *					layerName: 'Location Europe - Lon/Lat',
 *					hropts: [ {
 *								fieldLabelX: 'Lon',
 *								fieldLabelY: 'Lat',
 *								fieldEmptyTextX: 'Enter Lon-coordinate...',
 *								fieldEmptyTextY: 'Enter Lat-coordinate...'
 *							} ]
 *				},
 *
 */

/** api: constructor
 *  .. class:: CoordSearchPanel(config)
 *
 *      A specific ``Ext.form.FormPanel`` whose internal form is a
 *      ``Ext.form.BasicForm``.
 *      Use this form to do pan and zoom to a point in the map.
 *      The coordinates are typed in by the user.
 */
Heron.widgets.search.CoordSearchPanel = Ext.extend(Ext.form.FormPanel, {

	/** api: config[title]
	 *  title of the panel
     *  default value is 'Go to coordinates'.
	 */
	title: __('Go to coordinates'),

    /** api: config[titleDescription]
     *  description line under the title line
     *  default value is "null".
     */
	titleDescription: null,

	/** api: config[titleDescriptionStyle]
	 *  title description style (e.g. 'font-size: 11px;') or null
     *  default value is "null".
	 */
	titleDescriptionStyle: null,

	/** api: config[bodyBaseCls]
	 *  body base cls
     *  default value is 'x-panel'.
	 */
	// bodyBaseCls: 'x-plain',
	bodyBaseCls: 'x-panel',

	/** api: config[bodyItemCls]
	 *  item cls for setting the font features 
	 *  (example: 'hr-html-panel-font-size-11') of the form items
     *  default value is "null".
	 */
	bodyItemCls: null,

	/** api: config[bodyCls]
	 *  cls for setting the font features 
	 *  (example: 'hr-html-panel-font-size-11') of the form items
     *  default value is "null".
	 */
	bodyCls: null,

	/** api: config[fieldMaxWidth]
	 *  field max width for the input fields
     *  default value is 200.
	 */
	fieldMaxWidth: 150,

	/** api: config[fieldLabelWidth]
	 *  field label width for the input fields
     *  default value is ''.
	 */
	fieldLabelWidth: '',

	/** api: config[fieldStyle]
	 *  field style (e.g. 'color: green;') or null
     *  default value is "null".
	 */
	fieldStyle: null,

	/** api: config[fieldLabelStyle]
	 *  field label style (e.g. 'color: red;') or null
     *  default value is "null".
	 */
	fieldLabelStyle: null, 

	/** api: config[layerName]
	 *  layer name of the location marker.
	 */
	layerName: __('Location'),

	/** api: config[onProjectionIndex]
	 *  Start index entry of the projection combobox
     *  default value is 0 (first combobox entry).
	 */
	onProjectionIndex: 0,

	/** api: config[onZoomLevel]
	 *  zoomlevel when moving to point.
     *  default value is -1 (no zoom).
	 */
	onZoomLevel: -1,

    /** api: config[showProjection]
     *  ``Boolean`` If set to true, the projection combobox will be shown.
     *  If set to false, an input system selection will not be possible.
     *  Default is false.
     */
	showProjection: false,

    /** api: config[showZoom]
     *  ``Boolean`` If set to true, the zoom combobox will be shown.
     *  If set to false, a zoom selection will not be possible.
     *  Default is false.
     */
	showZoom: false,

    /** api: config[showAddMarkers]
     *  ``Boolean`` If set to true, the AddMarkers checkbox will be shown.
     *  If set to false, no AddMarkers checkbox will be shown.
     *  Default is false.
     */
	showAddMarkers: false,

    /** api: config[checkAddMarkers]
     *  ``Boolean`` If set to true, the AddMarkers option will be set.
     *  If set to false, no AddMarkers option will be set.
     *  Default is false.
     */
	checkAddMarkers: false,

    /** api: config[showHideMarkers]
     *  ``Boolean`` If set to true, the HideMarkers checkbox will be shown.
     *  If set to false, no HideMarkers checkbox will be shown.
     *  Default is false.
     */
	showHideMarkers: false,

    /** api: config[checkHideMarkers]
     *  ``Boolean`` If set to true, the HideMarkers option will be set.
     *  If set to false, no HideMarkers option will be set.
     *  Default is false.
     */
	checkHideMarkers: false,

    /** api: config[showResultMarker]
     *  ``Boolean`` If set to true, the result coordinates will be shown.
     *  If set to false, no result coordinates will be shown.
     *  Default is false.
     */
	showResultMarker: false,
	
	/** api: config[fieldResultMarkerStyle]
	 *  field style (e.g. 'color: green;') or null
     *  default value is "null".
	 */
	fieldResultMarkerStyle: null,
	
	/** api: config[fieldResultMarkerText]
	 *  field text label of the result or null
     *  default value is "Marker position: ".
	 */
	fieldResultMarkerText:  __('Marker position: '),

	/** api: config[fieldResultMarkerSeparator]
	 *  field text coordinates seperator
     *  default value is " , ".
	 */
	fieldResultMarkerSeparator: ' , ',

	/** api: config[fieldResultMarkerPrecision]
	 *  precision of the marker coordinates
     *  default value is 2.
	 */
	fieldResultMarkerPrecision: 2,

    /** api: config[removeMarkersOnClose]
     *  ``Boolean`` If set to true, the markers will be removed from the
     *  layer when the form is closed. If set to false, the markers layer 
     *  will be hidden without removing them.
     *  Default is false.
     */
	removeMarkersOnClose: false,

    /** api: config[showRemoveMarkersBtn]
     *  ``Boolean`` If set to true, the RemoveMarkers button will be shown.
     *  If set to false, no RemoveMarkers button will be shown.
     *  Default is false.
     */
	showRemoveMarkersBtn: false,

	/** api: config[buttonAlign]
	 *  Alignment of the button(s) - 'left', 'center', 'right' - in the form
     *  default value is 'left'.
	 */
	buttonAlign: 'left',

	/** api: config[hropts]
	 *  user defined projection array.
     *  default value is "null" - no transformation will be done.
	 */
	hropts: null,

		/** api: hropts[projEpsg]
		 *  custom projection (EPSG string) to enter coordinates if different from Map projection.
	     *  default value is null.
		 */
		// projEpsg: null,

		/** api: hropts[projDesc]
		 *  custom projection description for the EPSG string shown in the combobox
	     *  default value is null.
		 */
		// projDesc: null,

		/** api: hropts[fieldLabelX]
		 *  label for X-coordinate, default is "X", may use e.g. "lon".
	     *  default value is 'X'.
		 */
		// fieldLabelX: __('X'),

		/** api: hropts[fieldLabelY]
		 *  label for Y-coordinate, default is "Y", may use e.g. "lat".
	     *  default value is 'Y'.
		 */
		// fieldLabelY: __('Y'),

		/** api: hropts[fieldEmptyTextX]
		 *  field empty text for the X-input field or null
	     *  default value is 'Enter X-coordinate...'.
		 */
		// fieldEmptyTextX: __('Enter X-coordinate...'), 

		/** api: hropts[fieldEmptyTextY]
		 *  field empty text for the X-input field or null
	     *  default value is 'Enter Y-coordinate...'.
		 */
		// fieldEmptyTextY: __('Enter Y-coordinate...'), 

		/** api: hropts[fieldMinX]
		 *  min X value for input area check or null
		 *  for the area check all 4 check fields must be declared
	     *  default value is "null".	 
		 */
		// fieldMinX: null,

		/** api: hropts[fieldMinY]
		 *  min Y value for input area check or null
		 *  for the area check all 4 check fields must be declared
	     *  default value is "null".	 
		 */
		// fieldMinY: null,

		/** api: hropts[fieldMaxX]
		 *  max X value for input area check or null
		 *  for the area check all 4 check fields must be declared
	     *  default value is "null".	 
		 */
		// fieldMaxX: null,

		/** api: config[fieldDecPrecision]
		 *  precision of the input coordinate fields
	     *  default value is 2.
		 */
		// fieldDecPrecision: 2,

		/** api: hropts[fieldMaxY]
		 *  max Y value for input area check or null
		 *  for the area check all 4 check fields must be declared
	     *  default value is "null".	 
		 */
		// fieldMaxY: null,

		/** api: hropts[iconWidth]
		 *  icon width when providing own icon, default 32.
	     *  default value is 32.
		 */
		// iconWidth: 32,

		/** api: hropts[iconHeight]
		 *  icon height when providing own icon, default 32.
	     *  default value is 32.
		 */
		// iconHeight: 32,

		/** api: hropts[localIconFile]
		 *  name of local heron map pin icon to use.
	     *  default value is 'redpin.png'.
		 */
		// localIconFile: 'redpin.png',

		/** api: hropts[iconUrl]
		 *  full URL or path for custom icon to use.
	     *  default value is null.
		 */
		// iconUrl: null,

	initComponent: function () {
		var self = this;
		var map = Heron.App.getMap();
	
		this.arrProj = new Ext.data.ArrayStore({
							fields: [ 	
								{name: 'id'},
								{name: 'idLast'},
								{name: 'idX'},
								{name: 'idY'},
								{name: 'idB'},
								{name: 'projEpsg'},
								{name: 'projDesc'},
								{name: 'fieldLabelX'},
								{name: 'fieldLabelY'},
								{name: 'fieldEmptyTextX'},
								{name: 'fieldEmptyTextY'},
								{name: 'fieldMinX'},
								{name: 'fieldMinY'},
								{name: 'fieldMaxX'},
								{name: 'fieldMaxY'},
								{name: 'fieldDecPrecision'},
								{name: 'iconWidth'},
								{name: 'iconHeight'},
								{name: 'localIconFile'},
								{name: 'iconUrl'},
								{name: 'iconOL'}
							]
						});
		
		// get unique ExtJs id's
		var idX = Ext.id();
		var idY = Ext.id();
		var idB = Ext.id();

		// create combobox store for projection
		var contexts = this.hropts;
		if (contexts && typeof(contexts) !== "undefined") {
			for (var i = 0; i < contexts.length; i++) {
				// set individual entries
				var recArrPrj = this.arrProj.recordType;
				var recSrc = new recArrPrj({
									id: i,
									idLast: this.onProjectionIndex,
									idX: idX,
									idY: idY,
									idB: idB,
									projEpsg: contexts[i].projEpsg,
									projDesc: contexts[i].projDesc ? contexts[i].projDesc : (contexts[i].projEpsg ? contexts[i].projEpsg : __('Map system')),
									fieldLabelX: contexts[i].fieldLabelX ? contexts[i].fieldLabelX : __('X'),
									fieldLabelY: contexts[i].fieldLabelY ? contexts[i].fieldLabelY : __('Y'),
									fieldEmptyTextX: contexts[i].fieldEmptyTextX ? contexts[i].fieldEmptyTextX : __('Enter X-coordinate...'),
									fieldEmptyTextY: contexts[i].fieldEmptyTextY ? contexts[i].fieldEmptyTextY : __('Enter Y-coordinate...'),
									fieldMinX: contexts[i].fieldMinX ? contexts[i].fieldMinX : null,
									fieldMinY: contexts[i].fieldMinY ? contexts[i].fieldMinY : null,
									fieldMaxX: contexts[i].fieldMaxX ? contexts[i].fieldMaxX : null,
									fieldMaxY: contexts[i].fieldMaxY ? contexts[i].fieldMaxY : null,
									fieldDecPrecision: contexts[i].fieldDecPrecision ? contexts[i].fieldDecPrecision : 2,
									iconWidth: contexts[i].iconWidth ? contexts[i].iconWidth : 32,
									iconHeight: contexts[i].iconHeight ? contexts[i].iconHeight : 32,
									localIconFile: contexts[i].localIconFile ? contexts[i].localIconFile : 'redpin.png',
									iconUrl: contexts[i].iconUrl ? contexts[i].iconUrl : null,
									iconOL: null
							}); 
				this.arrProj.add(recSrc);
			}
		} else {
			// set default entries
			var recArrPrj = this.arrProj.recordType;
			var recSrc = new recArrPrj({
								id: 0,
								idLast: 0,
								idX: idX,
								idY: idY,
								idB: idB,
								projEpsg: null,
								projDesc: __('Map system'),
								fieldLabelX: __('X'),
								fieldLabelY: __('Y'),
								fieldEmptyTextX: __('Enter X-coordinate...'),
								fieldEmptyTextY: __('Enter Y-coordinate...'),
								fieldMinX: null,
								fieldMinY: null,
								fieldMaxX: null,
								fieldMaxY: null,
								fieldDecPrecision: 2,
								iconWidth: 32,
								iconHeight: 32,
								localIconFile: 'redpin.png',
								iconUrl: null,
								iconOL: null
						}); 
			this.arrProj.add(recSrc);
		}
		
		// create projection combobox
		this.pCombo = new Ext.form.ComboBox({
								fieldLabel: __('Input system'),
							    emptyText: __('Choose input system...'),
								tooltip: __('Input system'),
								anchor: '100%',
								boxMaxWidth: this.fieldMaxWidth,
								itemCls: this.bodyItemCls,
								cls: this.bodyCls,
								style: this.fieldStyle,
								labelStyle: this.fieldLabelStyle,
								editable: false,
							    triggerAction: 'all',
								mode: 'local',
					            store: this.arrProj,
					            displayField : 'projDesc',
					            valueField : 'id',
								value: this.onProjectionIndex,
								hidden: ((!this.showProjection) || ((this.arrProj.data.length <= 1) && (!this.arrProj.getAt(0).data.projEpsg))) ? true : false,
								listeners: {
									render: function(c) {
										c.el.set({qtip: this.tooltip});
										c.trigger.set({qtip: this.tooltip});
									},
									select: function(combo, record, index) {
										// get last active index
										var idLast = combo.store.data.items[index].data.idLast;
										// check, if there is a new index
										if (idLast != index ) {
											var p  = combo.store.data.items[index].data;
											var pX = Ext.getCmp(p.idX);
											var pY = Ext.getCmp(p.idY);
											var pB = Ext.getCmp(p.idB);
											// set new params for X field
											if (record.data.fieldLabelX) {
												pX.label.update(record.data.fieldLabelX);
											}
											if (record.data.fieldEmptyTextX) {
												Ext.getCmp(idX).emptyText = record.data.fieldEmptyTextX;
											}
											pX.decimalPrecision = record.data.fieldDecPrecision;
											pX.setValue('');
											pX.show();
											// set new params for Y field
											if (record.data.fieldLabelY) {
												pY.label.update(record.data.fieldLabelY);
											}
											if (record.data.fieldEmptyTextY) {
												Ext.getCmp(idY).emptyText = record.data.fieldEmptyTextY;
											}
											pY.decimalPrecision = record.data.fieldDecPrecision;
											pY.setValue('');
											pY.show();
											// disable go button
											pB.disable();
											pB.show();
											// clear marker text 
											this.rLabel.setText(this.fieldResultMarkerText);
											// remember the new index
											for (var i = 0; i < combo.store.data.length; i++) {
												combo.store.data.items[i].data.idLast = index;
											}
										}
									},
								scope: this	
								}
							});
	
		this.tLabel = new Ext.form.Label({
								html: this.titleDescription,
								// itemCls: this.bodyItemCls,
								// cls: this.bodyCls,
								style: this.titleDescriptionStyle
							});

		this.xField = new Ext.form.NumberField({
								id: idX,
								fieldLabel: this.arrProj.getAt(this.onProjectionIndex).data.fieldLabelX,
								emptyText: this.arrProj.getAt(this.onProjectionIndex).data.fieldEmptyTextX, 
								anchor: '100%',
								boxMaxWidth: this.fieldMaxWidth,
								itemCls: this.bodyItemCls,
								cls: this.bodyCls,
								style: this.fieldStyle,
								labelStyle: this.fieldLabelStyle,
								decimalPrecision: this.arrProj.getAt(this.onProjectionIndex).data.fieldDecPrecision,
								enableKeyEvents: true,
									listeners: {
										keyup: function (numberfield, ev) {
											this.onNumberKeyUp(numberfield, ev);
										},
										keydown: function (numberfield, ev) {
											this.rLabel.setText(this.fieldResultMarkerText);
										},
									scope: this
									}
							});

		this.yField = new Ext.form.NumberField({
								id: idY,
								fieldLabel: this.arrProj.getAt(this.onProjectionIndex).data.fieldLabelY,
								emptyText: this.arrProj.getAt(this.onProjectionIndex).data.fieldEmptyTextY, 
								anchor: '100%',
								boxMaxWidth: this.fieldMaxWidth,
								itemCls: this.bodyItemCls,
								cls: this.bodyCls,
								style: this.fieldStyle,
								labelStyle: this.fieldLabelStyle,
								decimalPrecision: this.arrProj.getAt(this.onProjectionIndex).data.fieldDecPrecision,
								enableKeyEvents: true,
									listeners: {
										keyup: function (numberfield, ev) {
											this.onNumberKeyUp(numberfield, ev);
										},
										keydown: function (numberfield, ev) {
											this.rLabel.setText(this.fieldResultMarkerText);
										},
									scope: this
									}
							});

		// create combobox store for 'Zoom-Scale' with 'no zoom' entry
		this.storeZoom = new GeoExt.data.ScaleStore({map: map});
		this.arrZoom = new Ext.data.ArrayStore({
								fields: [ 	
									{name: 'zoom', type: 'string'},
									{name: 'scale', type: 'string'}
								],
								data: [ 	
									['-1', __('no zoom')]
								]
							});
		for (var i = 0; i < this.storeZoom.getCount(); i++) {
			var recArrZoom = this.arrZoom.recordType;
			var rec = new recArrZoom({ 	
							zoom: this.storeZoom.getAt(i).data.level, 
							scale: '1 : ' + parseInt(this.storeZoom.getAt(i).data.scale + 0.5)
						}); 
			this.arrZoom.add(rec);
		}
		
		// create combobox 'Zoom-Scale'
		this.sCombo = new Ext.form.ComboBox({
								fieldLabel: __('Zoom'),
							    emptyText: __('Choose scale...'),
								tooltip: __('Scale'),
								anchor: '100%',
								boxMaxWidth: this.fieldMaxWidth,
								itemCls: this.bodyItemCls,
								cls: this.bodyCls,
								style: this.fieldStyle,
								labelStyle: this.fieldLabelStyle,
								editable: false,
								hidden: this.showZoom ? false : true,
							    triggerAction: 'all',
								mode: 'local',
					            store: this.arrZoom,
					            displayField : 'scale',
					            valueField : 'zoom',
								value: (this.onZoomLevel < 0) ? -1 : this.onZoomLevel,
								listeners: {
									render: function(c){
										c.el.set({qtip: this.tooltip});
										c.trigger.set({qtip: this.tooltip});
									}
								}
							});
	
		this.mCheckbox = new Ext.form.Checkbox({
								fieldLabel: __('Mode'),
								boxLabel: __('Remember locations'),
								anchor: '100%',
								boxMaxWidth: this.arrProj.getAt(0).data.fieldMaxWidth,
								itemCls: this.bodyItemCls,
								cls: this.bodyCls,
								labelStyle: this.fieldLabelStyle,
								checked: this.checkAddMarkers ? true : false,
								hidden: this.showAddMarkers ? false : true
							});
							
		this.cCheckbox = new Ext.form.Checkbox({
								fieldLabel: this.mCheckbox.hidden ? __('Mode') : '',
								boxLabel: this.removeMarkersOnClose ? __('Remove markers on close') : __('Hide markers on close'),
								anchor: '100%',
								boxMaxWidth: this.arrProj.getAt(0).data.fieldMaxWidth,
								itemCls: this.bodyItemCls,
								cls: this.bodyCls,
								labelStyle: this.fieldLabelStyle,
								checked: this.checkHideMarkers ? true : false,
								hidden: this.showHideMarkers ? false : true
							});

		this.rLabel = new Ext.form.Label({
								anchor: '100%',
								html: this.fieldResultMarkerText,
								itemCls: this.bodyItemCls,
								cls: this.bodyCls,
								style: this.fieldResultMarkerStyle,
								hidden: this.showResultMarker ? false : true
							});

		this.rButton = new Ext.Button({
	                    		text: __('Remove markers'),
	                    		minWidth: 90,
	                    		// height: 16,
	                    		// autoWidth: true,
	                    		autoHeight: true,
	                    		flex: 1,
	                    		hidden: this.showRemoveMarkersBtn ? false : true,
								handler: function () {
									self.removeMarkers(self);
									self.rLabel.setText(self.fieldResultMarkerText);
								}
							});
							
		this.gButton = new Ext.Button({
								id: idB,
								text: __('Go!'),
								align: 'right',
								tooltip: __('Pan and zoom to location'),
                    			minWidth: 90,
                    			// height: 16,
                    			// autoWidth: true,
                    			autoHeight: true,
								disabled: true,
                    			flex: 1,
								handler: function () {
									self.panAndZoom(self);
								}
							});

		this.items = [
			{
				layout: 'form',
				// autoHeight: true,
				border: false,
				baseCls: this.bodyBaseCls,
				labelWidth: this.fieldLabelWidth,
				padding: 5,
				items: [  self.tLabel
				 		, self.pCombo
				 		, self.xField 
				 		, self.yField
				 		, self.sCombo
				 		, self.mCheckbox
				 		, self.cCheckbox
				 		, self.rLabel
				],
				buttonAlign: this.buttonAlign,
				buttons: [this.rButton, this.gButton]
			}
		];
		
		// use ENTER keystroke like click on go button
		this.keys = [
			{ key: [Ext.EventObject.ENTER],
				handler: function () {
					if (!self.gButton.disabled) {
						self.panAndZoom(self);
					}
				}
			}
		];

		Heron.widgets.search.CoordSearchPanel.superclass.initComponent.call(this);

        // ExtJS lifecycle events
        this.addListener("afterrender", this.onPanelRendered, this);
	},

    /** private: method[onPanelRendered]
     *  Called when Panel has been rendered.
     */
    onPanelRendered: function () {
        if (this.ownerCt) {
        	// window events
            this.ownerCt.addListener("hide", this.onParentHide, this);
            this.ownerCt.addListener("show", this.onParentShow, this);
        }
    },

	/** private: method[onParentShow]
	 * Called usually before our panel is created.
	 */
	onParentShow: function () {
		var map = Heron.App.getMap();
		var markerLayer = map.getLayersByName(this.layerName);
		if (markerLayer[0]) {
			// show marker layer
			markerLayer[0].setVisibility(true);
		}
	},

	/** private: method[onParentHide]
	 * Cleanup usually before our panel is hidden.
	 */
	onParentHide: function () {
		// check for hide or remove  markers
		if (this.cCheckbox.checked) {
			// hide or remove  markers
			if (this.removeMarkersOnClose) {
				// remove markers
				this.removeMarkers(this);
			}
			var map = Heron.App.getMap();
			var markerLayer = map.getLayersByName(this.layerName);
			if (markerLayer[0]) {
				// hide marker layer
				markerLayer[0].setVisibility(false);
			}
		}
	},

	/** private: method[onNumberKeyUp]
	 * Check number input area if go button can be activated 
	 */
	onNumberKeyUp: function (numberfield, ev) {
		var valueX = parseFloat(this.xField.getValue());
		var valueY = parseFloat(this.yField.getValue());
		var fieldMinX = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMinX;
		var fieldMinY = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMinY;
		var fieldMaxX = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMaxX;
		var fieldMaxY = this.arrProj.getAt(this.pCombo.getValue()).data.fieldMaxY;
		// check value input
		if (valueX && valueY) {
			if (fieldMinX && fieldMinY && fieldMaxX && fieldMaxY) {
				// check input aerea
				if (((valueX >= parseFloat(fieldMinX)) && (valueX <= parseFloat(fieldMaxX))) &&
				    ((valueY >= parseFloat(fieldMinY)) && (valueY <= parseFloat(fieldMaxY)))) {
					this.gButton.enable();
				} else {
					this.gButton.disable();
				}
			} else {
				this.gButton.enable();
			}
		}
		else {
			this.gButton.disable();
		}
	},

	/** private: method[removeMarkers]
	 * remove markers from layer
	 */
	removeMarkers: function (self) {
		var map = Heron.App.getMap();
		// search marker layer by name
		var markerLayer = map.getLayersByName(this.layerName);
		// if marker layer found, remove existing markers
		if (markerLayer[0]) {
			markerLayer[0].clearMarkers();
            map.removeLayer(markerLayer[0]);
		}
	},

	/** private: method[panAndZoom]
	 * pan and zoom to marker coordinates
	 */
	panAndZoom: function (self) {
		var map = Heron.App.getMap();
		// search marker layer by name
		var markerLayer = map.getLayersByName(this.layerName);
		// if marker layer found, remove existing markers (if enabled)
		if (markerLayer[0]) {
			if (!self.mCheckbox.checked) {
				markerLayer[0].clearMarkers();
			}
		}

		var x = self.xField.getValue();
		var y = self.yField.getValue();
		var zoom = (self.sCombo.value >= 0) ? self.sCombo.value : map.getZoom();

		var position = new OpenLayers.LonLat(x, y);
		var selectedEpsg = this.arrProj.getAt(self.pCombo.value).data.projEpsg;

		// Reproject (if required)
		if (selectedEpsg && (selectedEpsg != map.getProjection())) {

			// Custom projection e.g. EPSG:4326 for e.g. Google/OSM projection
			// Need to include proj4js in that case !

			this.olProjection = null;
			this.olProjection = new OpenLayers.Projection(selectedEpsg);
			if (this.olProjection) {
				position.transform(this.olProjection, map.getProjectionObject());
			}
		}
		map.setCenter(position, zoom);

		// generate marker text
		this.rLabel.setText(this.fieldResultMarkerText + position.lon.toFixed(this.fieldResultMarkerPrecision) + this.fieldResultMarkerSeparator + position.lat.toFixed(this.fieldResultMarkerPrecision));

		// if marker layer not found, create
		if (!markerLayer[0]) {
			this.layer = new OpenLayers.Layer.Markers(this.layerName);
			map.addLayer(this.layer);
			markerLayer = map.getLayersByName(this.layerName);
		}

		// if (specific) marker not found, create
		if(!this.arrProj.getAt(self.pCombo.value).data.iconOL) {
			var iconUrl = Heron.Utils.getImageLocation(this.arrProj.getAt(self.pCombo.value).data.localIconFile);
			var iconWidth = this.arrProj.getAt(self.pCombo.value).data.iconWidth;
			var iconHeight = this.arrProj.getAt(self.pCombo.value).data.iconHeight;
			var size = new OpenLayers.Size(iconWidth, iconHeight);
			var offset = new OpenLayers.Pixel(-(size.w / 2), -size.h);
			this.arrProj.getAt(self.pCombo.value).data.iconOL = new OpenLayers.Icon(iconUrl, size, offset);
		}

		// set (specific) marker in marker layer
		var marker = new OpenLayers.Marker(position, this.arrProj.getAt(self.pCombo.value).data.iconOL.clone());
		markerLayer[0].addMarker(marker);
		markerLayer[0].setVisibility(true);
	}
});

/** api: xtype = gx_formpanel */
Ext.reg("hr_coordsearchpanel", Heron.widgets.search.CoordSearchPanel);
