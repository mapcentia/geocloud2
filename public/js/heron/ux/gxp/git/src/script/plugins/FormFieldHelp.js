/**
 * Copyright (c) 2008-2012 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/** api: (define)
 *  module = gxp.plugins
 *  class = FormFieldHelp
 */

Ext.namespace("gxp.plugins");

/** api: constructor
 *  .. class:: FormFieldHelp(config)
 *
 *    Plugin for showing a help text when hovering over a form field.
 *    Uses an Ext.QuickTip.
 */
/** api: example
 *    The code example below shows how to use this plugin with a form field:
 *
 *    .. code-block:: javascript
 *
 *      var field = new Ext.form.TextField({
 *          name: 'foo',
 *          value: 'bar',
 *          plugins: [{
 *              ptype: 'gxp_formfieldhelp',
 *              helpText: 'This is the help text for my form field'
 *          }]
 *      });
 */
gxp.plugins.FormFieldHelp = Ext.extend(Object, {

    /** api: ptype = gxp_formfieldhelp */
    ptype: 'gxp_formfieldhelp',

    /** api: config[helpText]
     *  ``String`` The help text to show.
     */
    helpText: null,

    /** api: config[dismissDelay]
     *  ``Integer`` How long before the quick tip should disappear.
     *  Defaults to 5 seconds.
     */
    dismissDelay: 5000,

    /** private: method[constructor]
     */
    constructor: function(config) {
        Ext.apply(this, config);
    },

    /** private: method[init]
     *
     *  :arg target: ``Ext.form.Field`` The form field initializing this 
     *  plugin.
     */
    init: function(target){
        this.target = target;
        target.on('render', this.showHelp, this);
    },

    /** private: method[showHelp]
     *  Show the help popup for the field. Show it on the associated label if
     *  present, with a fallback to the form element itself.
     */
    showHelp: function() {
        var target;
        if (this.target.label) {
            target = this.target.label;
        } else {
            target = this.target.getEl();
        }
        Ext.QuickTips.register({
            target: target, 
            dismissDelay: this.dismissDelay,
            text: this.helpText
        });
    }
});

Ext.preg(gxp.plugins.FormFieldHelp.prototype.ptype, gxp.plugins.FormFieldHelp);
