Ext.ns('gxp.plugins');

gxp.plugins.GeoRowEditor = Ext.ux && Ext.ux.grid && Ext.ux.grid.RowEditor && Ext.extend(Ext.ux.grid.RowEditor, {

    drawControl: null,
    modifyControl: null,

    addPointGeometryText: 'Add point',
    addLineGeometryText: 'Add line',
    addPolygonGeometryText: 'Add polygon',
    modifyGeometryText: 'Modify geometry',
    deleteGeometryText: 'Delete geometry',
    deleteGeometryTooltip: 'Delete the existing geometry',
    addGeometryTooltip: 'Add a new geometry by clicking in the map',
    modifyGeometryTooltip: 'Modify an existing geometry',

    beforedestroy: function() {
        this.drawControl = null;
        this.modifyControl = null;
        this.feature = null;
        this.geometry = null;
        gxp.plugins.GeoRowEditor.superclass.beforedestroy.apply(this, arguments);       
    },

    /** private: method[handleAddGeometry]
     *  Use a DrawFeature Control to add new geometries.
     */
    handleAddGeometry: function(handler) {
        var store = this.grid.store;
        this.feature = this.record.get("feature");
        if (this.drawControl === null) {
            this.drawControl = new OpenLayers.Control.DrawFeature(
                new OpenLayers.Layer.Vector(),
                handler, {
                eventListeners: {
                    "featureadded": function(evt) {
                        this.drawControl.deactivate();
                        var feature = this.feature;
                        feature.modified = Ext.apply(feature.modified || {}, {
                            geometry: null});
                        feature.geometry = evt.feature.geometry.clone();
                        if (feature.state !== OpenLayers.State.INSERT) {
                            feature.state =  OpenLayers.State.UPDATE;
                        }
                        this.record.set("state", feature.state);
                    },
                    scope: this
                }
            });
            this.feature.layer.map.addControl(this.drawControl);
        } else {
            var ctrl = this.drawControl;
            ctrl.handler.destroy();
            ctrl.handler = new handler(ctrl, ctrl.callbacks, ctrl.handlerOptions);
        }
        this.drawControl.activate();
    },

    handleAddPointGeometry: function() {
        this.handleAddGeometry(OpenLayers.Handler.Point);
    },

    handleAddLineGeometry: function() {
        this.handleAddGeometry(OpenLayers.Handler.Path);
    },

    handleAddPolygonGeometry: function() {
        this.handleAddGeometry(OpenLayers.Handler.Polygon);
    },

    handleDeleteGeometry: function() {
        this.feature = this.record.get("feature");
        this.feature.layer.eraseFeatures([this.feature]);
        this.feature.geometry.destroy();
        this.feature.geometry = null;
        this.feature.state = OpenLayers.State.UPDATE;
        this.btns.items.get(2).show();
        this.btns.items.get(3).show();
        this.btns.items.get(4).show();    
        this.btns.items.get(5).hide();
        this.btns.items.get(6).hide();
    },

    /** private: method[handleModifyGeometry]
     *  Use a ModifyFeature Control to modify existing geometries.
     */
    handleModifyGeometry: function() {
        var store = this.grid.store;
        this.feature = this.record.get("feature");
        if (this.modifyControl === null) {
            this.modifyControl = new OpenLayers.Control.ModifyFeature(
                this.feature.layer,
                {standalone: true}
            );
            this.feature.layer.map.addControl(this.modifyControl);
        }
        this.modifyControl.activate();
        this.modifyControl.selectFeature(this.feature);
    },

    stopEditing : function(saveChanges){
        this.editing = false;
        if(!this.isVisible()){
            return;
        }
        if(saveChanges === false || !this.isValid()){
            this.hide();
            this.fireEvent('canceledit', this, saveChanges === false);
            return;
        }
        var changes = {},
            r = this.record,
            hasChange = false,
            cm = this.grid.colModel,
            fields = this.items.items;
        for(var i = 0, len = cm.getColumnCount(); i < len; i++){
            if(!cm.isHidden(i)){
                var dindex = cm.getDataIndex(i);
                if(!Ext.isEmpty(dindex)){
                    var oldValue = r.data[dindex],
                        value = this.postEditValue(fields[i].getValue(), oldValue, r, dindex);
                    if(String(oldValue) !== String(value)){
                        changes[dindex] = value;
                        hasChange = true;
                    }
                }
            }
        }

        // bartvde, additional check if geometry has been modified
        hasChange = hasChange || (this.feature && this.feature.state === OpenLayers.State.UPDATE);

        if(hasChange && this.fireEvent('validateedit', this, changes, r, this.rowIndex) !== false){
            r.beginEdit();
            Ext.iterate(changes, function(name, value){
                r.set(name, value);
            });
            r.endEdit();
            this.fireEvent('afteredit', this, changes, r, this.rowIndex);
        }
        this.hide();
    },

    init: function(grid){
        gxp.plugins.GeoRowEditor.superclass.init.apply(this, arguments);
        this.on('afteredit', function() {
                this.grid.store.save();
                if (this.modifyControl !== null) {
                    this.modifyControl.deactivate();
                }
                if (this.drawControl !== null) {
                    this.drawControl.deactivate();
                }
            }, this);
        this.on('canceledit', function() {
                this.grid.store.rejectChanges();
                // restore the original geometry
                if (this.feature) {
                    if (this.feature.geometry) {
                        this.feature.layer.eraseFeatures([this.feature]);
                    }
                    this.feature.geometry = this.geometry;
                    this.feature.layer.drawFeature(this.feature);
                }
                if (this.modifyControl !== null) {
                    this.modifyControl.deactivate();
                }
                if (this.drawControl !== null) {
                    this.drawControl.deactivate();
                }
            }, this);
        this.on("beforeedit", function(plugin, rowIndex) { 
                var g = this.grid, view = g.getView(),
                    record = g.store.getAt(rowIndex);
                this.geometry = record.get("feature").geometry && record.get("feature").geometry.clone();
                if (this.btns) {
                    if (record.get("feature").geometry === null) {
                        this.btns.items.get(6).hide();
                        this.btns.items.get(5).hide();
                        this.btns.items.get(2).show();
                        this.btns.items.get(3).show();
                        this.btns.items.get(4).show();
                    } else {
                        this.btns.items.get(2).hide();
                        this.btns.items.get(3).hide();
                        this.btns.items.get(4).hide();
                        this.btns.items.get(5).show();
                        this.btns.items.get(6).show();
                    }
                }
                return true;
            }, this);
    },

    onRender: function(){
        Ext.ux.grid.RowEditor.superclass.onRender.apply(this, arguments);
        this.el.swallowEvent(['keydown', 'keyup', 'keypress']);
        var numButtons = 5;
        this.btns = new Ext.Panel({
            baseCls: 'x-plain',
            cls: 'x-btns',
            elements:'body',
            layout: 'table',
            width: (this.minButtonWidth * (numButtons+1)) + (this.frameWidth * numButtons) + (this.buttonPad * numButtons*2), // width must be specified for IE
            items: [{
                ref: 'saveBtn',
                itemId: 'saveBtn',
                xtype: 'button',
                text: this.saveText,
                width: this.minButtonWidth,
                handler: this.stopEditing.createDelegate(this, [true])
            }, {
                xtype: 'button',
                text: this.cancelText,
                width: this.minButtonWidth,
                handler: this.stopEditing.createDelegate(this, [false])
            }, {
                xtype: 'button',
                text: this.addPointGeometryText,
                tooltip: this.addGeometryTooltip,
                handler: this.handleAddPointGeometry,
                scope: this,
                hidden: (this.record.get("feature").geometry !== null),
                width: this.minButtonWidth*1.5
            }, {
                xtype: 'button',
                text: this.addLineGeometryText,
                tooltip: this.addGeometryTooltip,
                handler: this.handleAddLineGeometry,
                scope: this,
                hidden: (this.record.get("feature").geometry !== null),
                width: this.minButtonWidth*1.5
            }, {
                xtype: 'button',
                text: this.addPolygonGeometryText,
                tooltip: this.addGeometryTooltip,
                handler: this.handleAddPolygonGeometry,
                scope: this,
                hidden: (this.record.get("feature").geometry !== null),
                width: this.minButtonWidth*1.5
            }, {
                xtype: 'button',
                text: this.modifyGeometryText,
                tooltip: this.modifyGeometryTooltip,
                handler: this.handleModifyGeometry,
                scope: this,
                hidden: (this.record.get("feature").geometry === null),
                width: this.minButtonWidth*1.5
            }, {
                xtype: 'button',
                text: this.deleteGeometryText,
                tooltip: this.deleteGeometryTooltip,
                handler: this.handleDeleteGeometry,
                scope: this,
                hidden: (this.record.get("feature").geometry === null),
                width: this.minButtonWidth*1.5
            }]
        });
        this.fireEvent('buttonrender', this, this.btns);
        this.btns.render(this.bwrap);
    }
});

gxp.plugins.GeoRowEditor && Ext.preg('gxp_georoweditor', gxp.plugins.GeoRowEditor);
