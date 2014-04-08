/**
 * Copyright (c) 2008 The Open Planning Project
 */

/**
 * @require widgets/form/ColorField.js
 */

Ext.namespace("gxp");

/**
 * Class: gxp.ColorManager
 * A simple manager that handles the rendering of a ColorPicker window and
 *     coordinates the setting of values in a ColorField.  May be used as a
 *     plugin for a ColorField (or any text field that is intended to gather a
 *     RGB hex color value).
 */
gxp.ColorManager = function(config) {
    Ext.apply(this, config);
};

Ext.apply(gxp.ColorManager.prototype, {
    
    /**
     * Property: field
     * {<Styer.form.ColorField>} The currently focussed field.
     */
    field: null,
    
    /**
     * Method: init
     * Called when the color manager is used as a plugin.
     *
     * Parameters:
     * field - {<Styler.form.ColorField>} The field using this manager as a
     *     plugin.
     */
    init: function(field) {
        this.register(field);
    },
    
    /**
     * Method: destroy
     * Cleans up the manager.
     */
    destroy: function() {
        if(this.field) {
            this.unregister(this.field);
        }
    },
    
    
    /**
     * Method: register
     * Register a field with this manager.
     *
     * Parameters:
     * field - {<Styler.form.ColorField>} The field using this manager as a
     *     plugin.
     */
    register: function(field) {
        if(this.field) {
            this.unregister(this.field);
        }
        this.field = field;
        field.on({
            focus: this.fieldFocus,
            destroy: this.destroy,
            scope: this
        });
    },
    
    /**
     * Method: unregister
     * Unregister a field with this manager.
     * 
     * Parameters:
     * field - {<Styler.form.ColorField>} The field using this manager as a
     *     plugin.
     */
    unregister: function(field) {
        field.un("focus", this.fieldFocus, this);
        field.un("destroy", this.destroy, this);
        if(gxp.ColorManager.picker && field == this.field) {
            gxp.ColorManager.picker.un("pickcolor", this.setFieldValue, this);
        }
        this.field = null;
    },
    
    /**
     * Method: fieldFocus
     * Listener for "focus" event on a field.
     *
     * Parameters:
     * field - {<Styler.form.ColorField>} The focussed field.
     */
    fieldFocus: function(field) {
        if(!gxp.ColorManager.pickerWin) {
            gxp.ColorManager.picker = new Ext.ColorPalette();
            gxp.ColorManager.pickerWin = new Ext.Window({
                title: "Color Picker",
                closeAction: "hide",
                autoWidth: true,
                autoHeight: true
            });
        } else {
            gxp.ColorManager.picker.purgeListeners();
        }
        var listenerCfg = {
            select: this.setFieldValue,
            scope: this
        };
        var value = this.getPickerValue();
        if (value) {
            var colors = [].concat(gxp.ColorManager.picker.colors);
            if (!~colors.indexOf(value)) {
                if (gxp.ColorManager.picker.ownerCt) {
                    gxp.ColorManager.pickerWin.remove(gxp.ColorManager.picker);
                    gxp.ColorManager.picker = new Ext.ColorPalette();
                }
                colors.push(value);
                gxp.ColorManager.picker.colors = colors;
            }
            gxp.ColorManager.pickerWin.add(gxp.ColorManager.picker);
            gxp.ColorManager.pickerWin.doLayout();
            if (gxp.ColorManager.picker.rendered) {
                gxp.ColorManager.picker.select(value);
            } else {
                listenerCfg.afterrender = function() {
                    gxp.ColorManager.picker.select(value);
                };
            }
        }
        gxp.ColorManager.picker.on(listenerCfg);
        gxp.ColorManager.pickerWin.show();
    },
    
    /**
     * Method: setFieldValue
     * Listener for the "pickcolor" event of the color picker.  Only sets the
     *     field value if the field is visible.
     *
     * Parameters:
     * picker - {Ext.ux.ColorPicker} The color picker
     * color - {String} The RGB hex value (not prefixed with "#")
     */
    setFieldValue: function(picker, color) {
        if(this.field.isVisible()) {
            this.field.setValue("#" + color);
        }
    },
    
    /**
     * Method: getPickerValue
     */
    getPickerValue: function() {
        var field = this.field;
        var hex = field.getHexValue ?
            (field.getHexValue() || field.defaultBackground) :
            field.getValue();
        if (hex) {
            return hex.substr(1);
        }
    }
    
});

(function() {
    // register the color manager with every color field
    Ext.util.Observable.observeClass(gxp.form.ColorField);
    gxp.form.ColorField.on({
        render: function(field) {
            var manager = new gxp.ColorManager();
            manager.register(field);
        }
    });
})();

gxp.ColorManager.picker = null;

gxp.ColorManager.pickerWin = null;