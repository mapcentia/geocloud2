/**
 * Copyright (c) 2008-2011 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires widgets/PlaybackToolbar.js
 * @requires widgets/form/PlaybackModeComboBox.js
 * @requires OpenLayers/Control/DimensionManager.js
 */

/** api: (define)
 *  module = gxp
 *  class = PlaybackOptionsPanel
 *  base_link = `Ext.Panel <http://extjs.com/deploy/dev/docs/?class=Ext.Panel>`_
 */
Ext.namespace("gxp");

/** api: constructor
 *  .. class:: PlaybackOptionsPanel(config)
 *   
 *      A panel for displaying and modifiying the configuration options for the PlaybackToolbar.
 */
gxp.PlaybackOptionsPanel = Ext.extend(Ext.Panel, {
    
    /** api: config[viewer]
     *  ``gxp.Viewer``
     */

    /** api: config[playbackToolbar]
     *  ``gxp.PlaybackToolbar``
     */
    
    /** api: config[timeManager]
     *  ``OpenLayers.Control.DimensionalManager``
     */
    
    layout: "fit",

    /** i18n */
    titleText: "Date & Time Options",
    rangeFieldsetText: "Time Range",
    animationFieldsetText: "Animation Options",
    startText:'Start',
    endText:'End',
    listOnlyText:'Use Exact List Values Only',
    stepText:'Animation Step',
    unitsText:'Animation Units',
    noUnitsText:'Snap To Time List',
    loopText:'Loop Animation',
    reverseText:'Reverse Animation',
    rangeChoiceText:'Choose the range for the time control',
    rangedPlayChoiceText:'Playback Mode',
    
    /** private: method[initComponent]
     */
    initComponent: function() {
        var config = Ext.applyIf(this.initialConfig,{
            minHeight:400,
            minWidth:275,
            ref:'optionsPanel',
            items:[
            {
                xtype: 'form',
                layout: 'form',
                autoScroll: true,
                ref:'form',
                labelWidth:10,
                defaultType: 'textfield',
                items: [{
                    xtype: 'fieldset',
                    title: this.rangeFieldsetText,
                    defaultType: 'datefield',
                    labelWidth: 60,
                    items: [{
                        xtype: 'displayfield',
                        text: this.rangeChoiceText
                    }, {
                        fieldLabel: this.startText,
                        listeners: {
                            'select': this.setStartTime,
                            'change': this.setStartTime,
                            scope: this
                        },
                        ref: '../../rangeStartField'
                    }, {
                        fieldLabel: this.endText,
                        listeners: {
                            'select': this.setEndTime,
                            'change': this.setEndTime,
                            scope: this
                        },
                        ref: '../../rangeEndField'
                    }]
                }, {
                    xtype: 'fieldset',
                    title: this.animationFieldsetText,
                    labelWidth:100,
                    items: [
                    {
                      boxLabel:this.listOnlyText,
                      hideLabel:true,
                      xtype:'checkbox',
                      handler:this.toggleListMode,
                      scope:this,
                      ref:'../../listOnlyCheck'
                    },
                    {
                        fieldLabel: this.stepText,
                        xtype: 'numberfield',
                        anchor:'-25',
                        enableKeyEvents:true,
                        listeners: {
                            'change': this.setStep,
                            scope: this
                        },
                        ref: '../../stepValueField'
                    }, {
                        fieldLabel: this.unitsText,
                        xtype: 'combo',
                        anchor:'-5',
                        //TODO: i18n these time units
                        store: [
                            [OpenLayers.TimeUnit.SECONDS,'Seconds'], 
                            [OpenLayers.TimeUnit.MINUTES,'Minutes'], 
                            [OpenLayers.TimeUnit.HOURS,'Hours'], 
                            [OpenLayers.TimeUnit.DAYS,'Days'], 
                            [OpenLayers.TimeUnit.MONTHS,"Months"], 
                            [OpenLayers.TimeUnit.YEARS,'Years']
                        ],
                        valueNotFoundText:this.noUnitsText,
                        mode:'local',
                        forceSelection:true,
                        autoSelect:false,
                        editable:false,
                        triggerAction:'all',
                        listeners: {
                            'select': this.setUnits,
                            scope: this
                        },
                        ref: '../../stepUnitsField'
                    },{
                        //TODO: provide user information about these modes (Change to radio group?)
                        fieldLabel:this.rangedPlayChoiceText,
                        xtype:'gxp_playbackmodecombo',
                        agents: this.timeManager && this.timeManager.agents,
                        anchor:'-5',
                        listeners:{
                            'modechange':this.setPlaybackMode,
                            scope:this
                        },
                        ref:'../../playbackModeField'
                    }]
                },
                {
                    xtype:'checkbox',
                    boxLabel:this.loopText,
                    handler:this.setLoopMode,
                    scope:this,
                    ref:'../loopModeCheck'
                },
                {
                    xtype:'checkbox',
                    boxLabel:this.reverseText,
                    handler:this.setReverseMode,
                    scope:this,
                    ref:'../reverseModeCheck'
                }]
            }
            ],
            bbar: [{text: "Save", ref: '../saveBtn', hidden: this.readOnly, handler: function() { this.fireEvent('save', this); }, scope: this}]
        });
        Ext.apply(this,config);
        this.on('show', this.populateForm, this);
        gxp.PlaybackOptionsPanel.superclass.initComponent.call(this);
    },
    destroy:function(){
        this.timeManager = null;
        this.playbackToolbar = null;
        this.un('show',this,this.populateForm);
        gxp.PlaybackOptionsPanel.superclass.destroy.call(this);
    },
    setStartTime: function(cmp, date){
        this.timeManager.setAnimationStart(date.getTime());
        this.timeManager.fixedRange=true;
    },
    setEndTime:function(cmp,date){
        this.timeManager.setAnimationEnd(date.getTime());
        this.timeManager.fixedRange=true;
    },
    toggleListMode: function(cmp, checked){
        this.stepValueField.setDisabled(checked);
        this.stepUnitsField.setDisabled(checked);
        this.timeManager.snapToList = checked;
    },
    setUnits:function(cmp,record,index){
        var units = record.get('field1');
        if(this.timeManager.timeUnits != units){
            this.timeManager.timeUnits = units;
            this.timeManager.step = cmp.refOwner.stepValueField.value * OpenLayers.TimeStep[units];
            if(this.playbackToolbar.playbackMode != 'track'){
                this.timeManager.incrementValue();
            }
        }
    },
    setStep:function(cmp,newVal,oldVal){
        if(cmp.validate() && newVal){
            this.timeManager.step = newVal * OpenLayers.TimeStep[this.timeManager.timeUnits];
            this.timeManager.timeStep = newVal;
            if(this.playbackToolbar.playbackMode == 'ranged' && 
                this.timeManager.rangeInterval != newVal){
                    this.timeManager.rangeInterval = newVal;
                    this.timeManager.incrementTimeValue(newVal);
            }
        }
    },
    setPlaybackMode:function(cmp,mode,agents){
        var origMode = cmp.startValue;

        //adjust any time agents which had the same playback mode as the toolbar
        Ext.each(agents, function(agent){
            if(agent.tickMode == origMode){
                agent.tickMode = mode;
        }
        });

        this.disableListMode(mode=='ranged');
        this.playbackToolbar.setPlaybackMode(mode);
    },
    disableListMode:function(state){
        var disable = state!==false;
        if (disable) {
            this.listOnlyCheck.setValue(!disable);
        }
        this.listOnlyCheck.setDisabled(disable);
    },
    setLoopMode:function(cmp,checked){
        this.timeManager.loop=checked;
    },
    setReverseMode:function(cmp,checked){
        this.timeManager.step *= -1;
    },
    populateForm: function(cmp){
        this.readOnly ? this.saveBtn.hide() : this.saveBtn.show();
        this.doLayout();
        if (this.timeManager) {
            var start = new Date(this.timeManager.animationRange[0]),
            end = new Date(this.timeManager.animationRange[1]),
            step = this.timeManager.timeStep,
            unit = this.timeManager.timeUnit,
            snap = this.timeManager.snapToList,
            mode = (this.playbackToolbar) ? this.playbackToolbar.playbackMode : this.timeManager.agents[0].tickMode,
            loop = this.timeManager.loop,
            reverse = this.timeManager.step < 0;
            this.rangeStartField.setValue(start);
            this.rangeStartField.originalValue = start;
            this.rangeEndField.setValue(end);
            this.rangeEndField.originalValue = end;
            this.stepValueField.originalValue = this.stepValueField.setValue(step);
            this.stepUnitsField.originalValue = this.stepUnitsField.setValue(unit);
            this.listOnlyCheck.setValue(snap);
            this.listOnlyCheck.originalValue = snap;
            if(!this.playbackModeField.agents || !this.playbackModeField.agents.length){
                this.playbackModeField.agents = this.timeManager.agents;
            }
            this.playbackModeField.setValue(mode);
            this.playbackModeField.originalValue = mode;
            this.loopModeCheck.setValue(loop);
            this.loopModeCheck.originalValue = loop;
            this.reverseModeCheck.setValue(reverse);
            this.reverseModeCheck.originalValue=reverse;
        }
    },
    close: function(btn){
        if(this.ownerCt && this.ownerCt.close){
            this.ownerCt[this.ownerCt.closeAction]();
        }
    }
});

/** api: xtype = gxp_playbackoptions */
Ext.reg('gxp_playbackoptions', gxp.PlaybackOptionsPanel);
