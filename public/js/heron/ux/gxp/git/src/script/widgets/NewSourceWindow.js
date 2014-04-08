/**
 * Copyright (c) 2008-2011 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires widgets/NewSourceDialog.js
 */

/** api: (define)
 *  module = gxp
 *  class = NewSourceWindow
 *  extends = Ext.Window
 */
Ext.namespace("gxp");

//TODO Remove this component when we cut a release

/** api: constructor
 * .. class:: gxp.NewSourceWindow(config)
 *
 *     An Ext.Window with some defaults that better lend themselves toward use 
 *     as a quick query to get a service URL from a user.
 */
gxp.NewSourceWindow = Ext.extend(Ext.Window, {

    /** api: config[bodyStyle]
     * The default bodyStyle sets the padding to 0px
     */
    bodyStyle: "padding: 0px",

    /** api: config[hideBorders]
     *  Defaults to true.
     */
    hideBorders: true,
    
    /** api: config[width]
     * The width defaults to 300
     */
    width: 300,

    /** api: config[closeAction]
     * The default closeAction is 'hide'
     */
    closeAction: 'hide',

    /** api: property[error]
     * ``String``
     * The error message set (for example, when adding the source failed)
     */
    error: null,    

    /** api: event[server-added]
     * Fired with the URL that the user provided as a parameter when the form 
     * is submitted.
     */
    
    initComponent: function() {
        window.setTimeout(function() {
            throw("gxp.NewSourceWindow is deprecated. Use gxp.NewSourceDialog instead.");
        }, 0);
        this.addEvents("server-added");
        gxp.NewSourceWindow.superclass.initComponent.apply(this, arguments);
        this.addEvents("server-added");
        var dialog = this.add(new gxp.NewSourceDialog(Ext.applyIf({
            addSource: this.addSource,
            header: false,
            listeners: {
                urlselected: function(cmp, url) {
                    this.fireEvent("server-added", url);
                }
            }
        }, this.initialConfig)));
        this.setTitle(dialog.title);
        this.setLoading = dialog.setLoading.createDelegate(dialog);
        this.setError = dialog.setError.createDelegate(dialog);
        this.on("hide", dialog.onHide, dialog);
    },
    
    /** api: config[addSource]
     * A callback function to be called when the user submits the form in the 
     * NewSourceWindow.
     *
     * TODO this can probably be extracted to an event handler
     */
    addSource: function(url, success, failure, scope) {
    }
});
