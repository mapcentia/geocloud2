/**
 * Copyright (c) 2008-2011 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires plugins/Tool.js
 * @requires widgets/PlaybackToolbar.js
 * @requires widgets/PlaybackOptionsPanel.js
 */

/** api: (define)
 *  module = gxp.plugins
 *  class = Playback
 */

/** api: (extends)
 *  plugins/Tool.js
 */
Ext.namespace("gxp.plugins");

/** api: constructor
 *  .. class:: Playback(config)
 *
 *    Provides an action to display a Playback in a new window.
 */
gxp.plugins.Playback = Ext.extend(gxp.plugins.Tool, {
    
    /** api: ptype = gxp_playback */
    ptype: "gxp_playback",
    
    /** api: config[autoStart]
     *  ``Boolean``
     *  Should playback begin as soon as possible.
     */
    autoStart: false,

    /** api: config[looped]
     *  ``Boolean``
     *  Should playback start in continuous loop mode.
     */    
    looped: false,
    
    /** api: config[playbackMode]
     *  ``String``
     *  One of: 'track', 'cumulative', or 'ranged'
     */
    playbackMode: 'track',
    
    /** api: config[menuText]
     *  ``String``
     *  Text for Playback menu item (i18n).
     */
    menuText: "Time Playback",

    /** api: config[tooltip]
     *  ``String``
     *  Text for Playback action tooltip (i18n).
     */
    tooltip: "Show Time Playback Panel",

    /** api: config[actionTarget]
     *  ``Object`` or ``String`` or ``Array`` Where to place the tool's actions
     *  (e.g. buttons or menus)? Use null as the default since our tool has both 
     *  output and action(s).
     */
    actionTarget: null,

    /** api: config[outputTarget]
     *  ``Object`` or ``String`` Where to place the tool's output (widgets.PlaybackPanel)
     *  Use 'map' as the default to display a transparent floating panel over the map.
     */
    outputTarget: 'map',
    
    /** private: method[constructor]
     */
    constructor: function(config) {
        gxp.plugins.Playback.superclass.constructor.apply(this, arguments);
    },

    init: function(target) {
        target.on('saved', function() {
            if (this.output) {
                this.output[0].optionsWindow.optionsPanel.readOnly = false;
            }
        }, this, {single: true});
        gxp.plugins.Playback.superclass.init.call(this, target);
    },

    /** private: method[addOutput]
     *  :arg config: ``Object``
     */
    addOutput: function(config){
        delete this._ready;
        OpenLayers.Control.DimensionManager.prototype.maxFrameDelay = 
            (this.target.tests && this.target.tests.dropFrames) ? 10 : NaN;
        config = Ext.applyIf(config || this.outputConfig || {}, {
            xtype: 'gxp_playbacktoolbar',
            mapPanel:this.target.mapPanel,
            playbackMode:this.playbackMode,
            prebuffer: this.target.prebuffer,
            maxframes: this.target.maxframes,
            looped:this.looped,
            autoPlay:this.autoStart,
            optionsWindow: new Ext.Window({
                title: gxp.PlaybackOptionsPanel.prototype.titleText,
                width: 350,
                height: 400,
                layout: 'fit',
                items: [{xtype: 'gxp_playbackoptions', readOnly: (!this.target.isAuthorized() || !(this.target.id || this.target.mapID)), listeners: {
                    'save': function(cmp) {
                        this.target.on('saved', function() {
                            cmp.ownerCt.close();
                        }, this, {single: true});
                        this.target.save();
                    },
                    scope: this
                }}],
                closeable: true,
                closeAction: 'hide',
                renderTo: Ext.getBody(),
                listeners: {
                    'show': function(cmp){
                        var optsPanel = cmp.findByType('gxp_playbackoptions')[0];
                        optsPanel.fireEvent('show', optsPanel);
                    },
                    'hide': function(cmp){
                        var optsPanel = cmp.findByType('gxp_playbackoptions')[0];
                        optsPanel.fireEvent('hide', optsPanel);
                    }
                }
            })
        });
        var toolbar = gxp.plugins.Playback.superclass.addOutput.call(this,config); 
        this.relayEvents(toolbar,['timechange','rangemodified']);
        this.playbackToolbar = toolbar;
        //firing the 'rangemodified' event to indicate that the toolbar has been created with temporal layers
        if(toolbar.control.layers){
            this.fireEvent('rangemodified',this,toolbar.control.range);
        }
        return toolbar;
    },
    addActions: function(actions){
        this._ready = 0;
        this.target.mapPanel.map.events.register('addlayer', this, function(e) {
            var layer = e.layer;
            if (layer instanceof OpenLayers.Layer.WMS && layer.dimensions && layer.dimensions.time) {
                this.target.mapPanel.map.events.unregister('addlayer', this, arguments.callee);
                this._ready += 1;
                if (this._ready > 1) {
                    this.addOutput();
                }
            }
        });

        this.target.on('ready',function() {
            this._ready += 1;
            if (this._ready > 1) {
                this.addOutput();
            }
        }, this);
    },
    /** api: method[setTime]
     *  :arg time: {Date}
     *  :return: {Boolean} - true if the time could be set to the supplied value
     *          false if the time is outside the current range of the DimManager
     *          control.
     *          
     *  Set the time represented by the playback toolbar programatically
     */
    setTime: function(time){
        return this.playbackToolbar.setTime(time);
    },

    /** api: method[getState]
     *  :returns {Object} - initial config plus any user configured settings
     *  
     *  Tool specific implementation of the getState function
     */    
    getState: function() {
        var config = gxp.plugins.Playback.superclass.getState.call(this);
        var toolbar = this.playbackToolbar;
        if(toolbar) {
            var control = toolbar.control;
            config.outputConfig = Ext.apply(toolbar.initialConfig, {
                dynamicRange : toolbar.dyanamicRange,
                playbackMode : toolbar.playbackMode
            });
            if(control) {
                var dimModel = control.modelCache || control.model;
                config.outputConfig.controlConfig = {
                    model: {
                        dimension: dimModel.dimension || control.dimension,
                        values: dimModel.values,
                        range: dimModel.range
                    },
                    animationRange : control.animationRange,
                    timeStep : control.timeStep,
                    timeUnits : (control.timeUnits) ? control.timeUnits : undefined,
                    loop : control.loop,
                    snapToList : control.snapToList,
                    dimension : dimModel.dimension || control.dimension
                };
                if(control.agents.length > 1) {
                    var agents = control.agents;
                    var agentConfigs = [];
                    for(var i = 0; i < agents.length; i++) {
                        var agentConfig = {
                            type : agents[i].CLASS_NAME.split("Agent.")[1],
                            tickMode : agents[i].tickMode,
                            rangeInterval : agents[i].rangeInterval,
                            values : agents[i].values,
                            layers : []
                        };
                        for(var j = 0; j < agents[i].layers.length; j++) {
                            var layerRec = this.target.mapPanel.layers.getByLayer(agents[i].layers[j]);
                            var layerConfig = this.target.layerSources[layerRec.get('source')].getConfigForRecord(layerRec);
                            //don't need or want to serialize the whole capabilities info, just get the stuff we need
                            agentConfig.layers.push({
                                source: layerConfig.source,
                                title: layerConfig.title,
                                name: layerConfig.name,
                                styles: (layerConfig.styles) ? layerConfig.styles : undefined
                            });
                        }
                        agentConfigs.push(agentConfig);
                    }
                    config.outputConfig.controlConfig.agents = agentConfigs;
                }
            }
            //get rid of 2 instantiated objects that will cause problems
            delete config.outputConfig.mapPanel;
            delete config.outputConfig.optionsWindow;
        }
        return config;
    }
});

Ext.preg(gxp.plugins.Playback.prototype.ptype, gxp.plugins.Playback);
