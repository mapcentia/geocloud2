/**
 * Copyright (c) 2008-2012 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 * @requires OpenLayers/Control/DimensionManager.js
 * @requires OpenLayers/Dimension/Agent.js
 * @requires OpenLayers/Dimension/Model.js
 */
 
/** api: (define)
 *  module = gxp.slider
 *  class = TimeSlider
 *  base_link = `Ext.slider.MultiSlider <http://extjs.com/deploy/dev/docs/?class=Ext.slider.MultiSlider>`_
 */
Ext.ns("gxp.slider");

gxp.slider.TimeSlider = Ext.extend(Ext.slider.MultiSlider, {
    ref : 'slider',
    cls : 'gx_timeslider',
    indexMap : null,
    width : 200,
    animate : false,
    timeFormat : "l, F d, Y g:i:s A",
    timeManager : null,
    playbackMode : 'track',
    autoPlay : false,
    aggressive: false,
    changeBuffer: 10,
    map: null,
    initComponent : function() {
        if(!this.timeManager) {
            this.timeManager = new OpenLayers.Control.DimensionManager();
            this.map.addControl(this.timeManager);
        }
                
        if(!this.model){
            this.model = this.timeManager.model;
        }

        if(this.timeManager.agents) {
            if(!this.timeManager.timeUnits && !this.timeManager.snapToList) {
                if(this.model.values && !this.model.resolution && this.timeManager.snapToList !== false){
                    this.timeManager.snapToList = true;
                }
                if(this.model.resolution && !this.model.values && this.model.timeUnits){
                    this.timeManager.timeUnits = this.model.timeUnits;
                    this.timeManager.timeStep = this.model.timeStep;
                }
                if(this.model.values && this.model.resolution){
                    //this.manageConflict();
                }
            }
            if(this.playbackMode && this.playbackMode != 'track') {
                if(this.timeManager.timeUnits) {
                    this.timeManager.incrementTimeValue(this.timeManager.rangeInterval);
                }
            }
        }
        
        var sliderInfo = this.buildSliderValues();
        if(sliderInfo) {
            if(!this.timeManager.snapToList && !this.timeManager.timeUnits){
            this.timeManager.guessPlaybackRate();
            }
            var initialSettings = {
                maxValue: sliderInfo.maxValue,
                minValue: sliderInfo.minValue,
                increment : sliderInfo.interval,
                keyIncrement : sliderInfo.interval,
                indexMap : sliderInfo.map,
                values: sliderInfo.values
            };
            //set an appropiate time format if one was not specified
            if(!this.initialConfig.timeFormat){
                if (sliderInfo.interval) {
                    var interval = sliderInfo.interval*OpenLayers.TimeStep[this.timeManager.timeUnits];
                    this.setTimeFormat(gxp.PlaybackToolbar.guessTimeFormat(interval));
                } else if (this.model.values) {
                    var allUnits = ['Seconds', 'Minutes', 'Hours', 'Days', 'Months', 'Years'];
                    var units = {};
                    for (var i = 1, ii = this.model.values.length; i<ii; ++i) {
                        diff = this.model.values[i] - this.model.values[i-1];
                        info = gxp.PlaybackToolbar.smartIntervalFormat(diff);
                        units[info.units] = true;
                    }
                    var unit = null;
                    for (i = 0, ii = allUnits.length; i < ii; ++i) {
                        if (units[allUnits[i]] === true) {
                            unit = allUnits[i];
                            break;
                        }
                    }
                    if (unit !== null) {
                        var format = gxp.PlaybackToolbar.timeFormats[unit];
                        if (format) {
                            this.setTimeFormat(format);
                        }
                    }
                }
            }
            //modify initialConfig so that it properly
            //reflects the initial state of this component
            Ext.applyIf(this.initialConfig,initialSettings);
            Ext.apply(this,this.initialConfig);
        }
        
        this.timeManager.events.on({
            'rangemodified': this.onRangeModified,
            'tick': this.onTimeTick,
            scope: this
        });
        
        this.plugins = (this.plugins || []).concat(
            [new Ext.slider.Tip({cls: 'gxp-timeslider-tip', getText:this.getThumbText})]);

        this.listeners = Ext.applyIf(this.listeners || {}, {
            'dragstart' : function() {
                if(this.timeManager.timer) {
                    this.timeManager.stop();
                    this._restartPlayback = true;
                }
            },
            'beforechange' : function(slider, newVal, oldVal, thumb) {
                var allow = true;
                if(!(this.timeManager.timeUnits || this.timeManager.snapToList)) {
                    allow = false;
                }
                else if(this.playbackMode == 'cumulative' && slider.indexMap[thumb.index] == 'tail') {
                    allow = false;
                }
                return allow;
            },
            'afterrender' : function(slider) {
                this.sliderTip = slider.plugins[0];
                if(this.timeManager.units && slider.thumbs.length > 1) {
                    slider.setThumbStyles();
                }
                //start playing after everything is rendered when autoPlay is true
                if(this.autoPlay) {
                    this.timeManager.play();
                }
            },
            scope : this
        });
        if (this.aggressive === true) {
            this.listeners['change'] = {fn: this.onSliderChangeComplete, buffer: this.changeBuffer};
        } else {
            this.listeners['changecomplete'] = this.onSliderChangeComplete;
        }
        gxp.slider.TimeSlider.superclass.initComponent.call(this);
        this.addEvents(
            /**
             * @event sliderclick
             * Fires when somebody clicks in the slider to change its position.
             * @param {Ext.slider.MultiSlider} slider The slider
             */
            'sliderclick'
        );
    },

    onClickChange : function(local) {
        this.fireEvent('sliderclick', this);
        gxp.slider.TimeSlider.superclass.onClickChange.apply(this, arguments);
    },

    beforeDestroy : function(){
        this.map = null;
        gxp.slider.TimeSlider.superclass.beforeDestroy.call(this);
    },

    /** api: method[setPlaybackMode]
     * :arg mode: {String} one of 'track',
     * 'cumulative', or 'ranged'
     *  
     *  Set the playback mode of the control.
     */
    setPlaybackMode: function(mode){
        this.playbackMode = mode;
        var sliderInfo = this.buildSliderValues();
        this.reconfigureSlider(sliderInfo);
        if (this.playbackMode != 'track') {
            if(this.timeManager.rangeInterval){ 
                this.timeManager.incrementTimeValue(this.timeManager.rangeInterval); 
                this.setValue(0,this.timeManager.currentValue);
            }
        }
        this.setThumbStyles();
    },
    
    setTimeFormat : function(format){
        if(format){
            this.timeFormat = format;
        }
    },
    
    onRangeModified : function(evt) {
        var ctl = this.timeManager;
        if(!ctl.agents || !ctl.agents.length) {
            //we don't have any time agents which means we should get rid of the time manager control
            //we will automattically add the control back when a time layer is added via handlers on the
            //playback plugin or the application code if the playback toolbar was not build via the plugin
            ctl.map.removeControl(this.ctl);
            ctl.destroy();
            ctl = null;
        }
        else {
            var oldvals = {
                start : ctl.animationRange[0],
                end : ctl.animationRange[1],
                resolution : {
                    units : ctl.units,
                    step : ctl.step
                }
            };
            ctl.guessPlaybackRate();
            if(ctl.animationRange[0] != oldvals.start || ctl.animationRange[1] != oldvals.end ||
                 ctl.units != oldvals.units || ctl.step != oldvals.step) {
                this.reconfigureSlider(this.buildSliderValues());
                /*
                 if (this.playbackMode == 'ranged') {
                 this.timeManager.incrementTime(this.control.rangeInterval, this.control.units);
                 }
                 */
                this.setThumbStyles();
                this.fireEvent('rangemodified', this, ctl.animationRange);
            }
        }
    },
    
    onTimeTick : function(evt) {
        var currentValue = evt.currentValue;
        if (currentValue) {
            var toolbar = this.refOwner; //TODO use relay event instead
            var tailIndex = this.indexMap ? this.indexMap.indexOf('tail') : -1;
            var offset = (tailIndex > -1) ? currentValue - this.thumbs[0].value : 0;
            this.setValue(0, currentValue);
            if(tailIndex > -1) {
                this.setValue(tailIndex, this.thumbs[tailIndex].value + offset);
            }
            this.updateTimeDisplay();
            //TODO use relay event instead, fire this directly from the slider
            toolbar.fireEvent('timechange', toolbar, currentValue);
        }
    },
    
    updateTimeDisplay: function(){
        this.sliderTip.onSlide(this,null,this.thumbs[0]);
        this.sliderTip.el.alignTo(this.el, 'b-t?', this.offsets);
    },
    
    buildSliderValues : function() {
        var mngr = this.timeManager;
        if(!mngr.step && !mngr.snapToList){
            //timeManager is essentially empty if both of these are false/null
            return false;
        }
        else{
            var indexMap = ['primary'], 
                values = [mngr.currentValue],
                min = mngr.animationRange[0],
                max = mngr.animationRange[1],
                interval = false;

            if(this.dynamicRange) {
                var rangeAdj = (min - max) * 0.1;
                values.push( min = min - rangeAdj, max = max + rangeAdj);
                indexMap[1] = 'minTime';
                indexMap[2] = 'maxTime';
            }
            if(this.playbackMode != 'track') {
                values.push(min);
                indexMap[indexMap.length] = 'tail';
            }
            //set slider interval based on the step value
            if(!mngr.snapToList){
                // OpenLayers.Control.DimensionManger.step should
                // always be a real numeric value, even if timeUnits & timeStep are set
                interval = mngr.step;
            }

            return {
                'values' : values,
                'map' : indexMap,
                'maxValue' : max,
                'minValue' : min,
                'interval' : interval
            };
        }
    },

    reconfigureSlider : function(sliderInfo) {
        var slider = this;
        slider.setMaxValue(sliderInfo.maxValue);
        slider.setMinValue(sliderInfo.minValue);
        Ext.apply(slider, {
            increment : sliderInfo.interval,
            keyIncrement : sliderInfo.interval,
            indexMap : sliderInfo.map
        });
        for(var i = 0; i < sliderInfo.values.length; i++) {
            if(slider.thumbs[i]) {
                slider.setValue(i, sliderInfo.values[i]);
            }
            else {
                slider.addThumb(sliderInfo.values[i]);
            }
        }
        //set format of slider based on the interval steps
        if(!sliderInfo.interval && slider.timeManager.modelCache.values) {
            sliderInfo.interval = Math.round((sliderInfo.maxValue - sliderInfo.minValue) / this.timeManager.modelCache.values.length);
        }
        this.setTimeFormat(gxp.PlaybackToolbar.guessTimeFormat(sliderInfo.interval));
    },

    setThumbStyles : function() {
        var slider = this;
        var tailIndex = slider.indexMap.indexOf('tail');
        if(slider.indexMap[1] == 'min') {
            slider.thumbs[1].el.addClass('x-slider-min-thumb');
            slider.thumbs[2].el.addClass('x-slider-max-thumb');
        }
        if(tailIndex > -1) {
            var tailThumb = slider.thumbs[tailIndex];
            var headThumb = slider.thumbs[0];
            tailThumb.el.addClass('x-slider-tail-thumb');
            tailThumb.constrain = false;
            headThumb.constrain = false;
        }
    },    

    getThumbText: function(thumb) {
        if(thumb.slider.indexMap[thumb.index] != 'tail') {
            var d = new Date(thumb.value);
            d.setTime( d.getTime() + d.getTimezoneOffset()*60*1000 );
            return (d.format(thumb.slider.timeFormat));
        }
        else {
            var formatInfo = gxp.PlaybackToolbar.smartIntervalFormat.call(thumb, thumb.slider.thumbs[0].value - thumb.value);
            return formatInfo.value + ' ' + formatInfo.units;
        }
    },

    onSliderChangeComplete: function(slider, value, thumb, silent){
        var timeManager = slider.timeManager;
        if (value === timeManager.currentValue) {
            return;
        }
        //test if this is the main time slider
        switch (slider.indexMap[thumb.index]) {
            case 'primary':
                //if we have a tail slider, then the range interval should be updated first
                var tailIndex = slider.indexMap.indexOf('tail'); 
                if (tailIndex>-1){
                    slider.onSliderChangeComplete(slider,slider.thumbs[tailIndex].value,slider.thumbs[tailIndex],true);
                }
                if (!timeManager.snapToList && timeManager.timeUnits) {
                    //this will make the value actually be modified by the exact time unit
                    var op = value > timeManager.currentValue ? 'ceil' : 'floor';
                    var steps = Math[op]((value-timeManager.currentValue)/OpenLayers.TimeStep[timeManager.timeUnits]);
                    timeManager.setCurrentValue(timeManager.incrementTimeValue(steps));
                            } else {
                    timeManager.setCurrentValue(value);
                }
                break;
            case 'min':
                    timeManager.setAnimationStart(value);
                break;
            case 'max':
                    timeManager.seAnimantionEnd(value);
                break;
            case 'tail':
                for (var i = 0, len = timeManager.agents.length; i < len; i++) {
                    if(timeManager.agents[i].tickMode == 'range'){
                        timeManager.agents[i].rangeInterval = (slider.thumbs[0].value - value);
                    }
                }
                if(!silent){
                    timeManager.setCurrentValue(slider.thumbs[0].value);
                }
        }
        if (this._restartPlayback) {
            delete this._restartPlayback;
            timeManager.play();
        }
    },

    // override to add pre buffer progress
    onRender : function() {
        this.autoEl = {
            cls: 'x-slider ' + (this.vertical ? 'x-slider-vert' : 'x-slider-horz'),
            cn : [{
                cls: 'x-slider-end',
                cn : {
                    cls:'x-slider-inner',
                    cn : [{tag:'a', cls:'x-slider-focus', href:"#", tabIndex: '-1', hidefocus:'on'}]
                }
            }, {cls: 'x-slider-progress'}]
        };

        Ext.slider.MultiSlider.superclass.onRender.apply(this, arguments);

        this.endEl   = this.el.first();
        this.progressEl = this.el.child('.x-slider-progress');
        this.innerEl = this.endEl.first();
        this.focusEl = this.innerEl.child('.x-slider-focus');

        //render each thumb
        for (var i=0; i < this.thumbs.length; i++) {
            this.thumbs[i].render();
        }

        //calculate the size of half a thumb
        var thumb      = this.innerEl.child('.x-slider-thumb');
        this.halfThumb = (this.vertical ? thumb.getHeight() : thumb.getWidth()) / 2;

        this.initEvents();
    }

});

Ext.reg('gxp_timeslider', gxp.slider.TimeSlider);
