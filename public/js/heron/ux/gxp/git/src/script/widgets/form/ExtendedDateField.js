/**
 * Copyright (c) 2008-2012 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/** api: (define)
 *  module = gxp.form
 *  class = ExtendedDateField
 *  base_link = `Ext.form.DateField <http://extjs.com/deploy/dev/docs/?class=Ext.form.DateField>`_
 */
Ext.namespace("gxp.form");

Date.defaults.d = 1;
Date.defaults.m = 1;

gxp.form.ExtendedDateTimeField = Ext.extend(Ext.form.CompositeField, {
   
    initComponent: function() {
        //ensure that qtips are initialized
        Ext.QuickTips.init();
        this.items = [{
            xtype: 'gxp_datefield',
            allowBlank: (this.initialConfig.allowBlank !== false),
            todayText: this.initialConfig.todayText,
            selectToday: this.initialConfig.selectToday,
            ref: "date"
        }, {
            xtype: 'timefield',
            width: 80,
            ref: "time"
        }];
        gxp.form.ExtendedDateTimeField.superclass.initComponent.apply(this, arguments);
    },

    getValue : function() {
        var dateValue = this.date.getValue();
        var timeValue = this.time.getValue();
        if (dateValue !== null && timeValue === "") {
            timeValue = "12:00 AM";
        }
        if (timeValue !== "") {
            var dateTimeCurrent = this.time.parseDate(timeValue);
            var dateTimeOriginal = new Date(this.time.initDate);
            var diff = (dateTimeCurrent.getTime()/1000) - (dateTimeOriginal.getTime()/1000);
            // we should always return times as UTC
            return dateValue + diff - (new Date(dateValue*1000).getTimezoneOffset()*60);
        } else {
            return dateValue;
        }
    },

    setValue: function(v) {
        this.date.setValue(v);
        var value = new Date(parseFloat(v)*1000);
        value.setTime( value.getTime() + value.getTimezoneOffset()*60*1000 );
        if (value) {
            var hours = value.getHours();
            if (hours > 12) {
                hours -= 12;
            } else if (hours === 0) {
                hours = 12;
            }
            var minutes = value.getMinutes();
            if (minutes < 10) {
                minutes = '0' + minutes;
            }
            this.time.setValue(hours + ":" + minutes + " " + (value.getHours() > 12 ? "PM" : "AM"));
        }
    }

});

/** api: xtype = gxp_datetimefield */
Ext.reg('gxp_datetimefield', gxp.form.ExtendedDateTimeField);

/** api: constructor
 *  .. class:: ExtendedDateField(config)
 *   
 *      It has been noted that to support the entire date range of earth's
 *      history, we'll need an approach that does not totally rely on date
 *      objects. A reasonable approach is to use a big integer (or
 *      long) that represents the number of seconds before or after
 *      1970-01-01. This allows us to use date objects with little effort when
 *      a value is within the supported range and to use a date-like object
 *      (ignores things like leap-year, etc.) when the value is outside of
 *      that range.
 */
gxp.form.ExtendedDateField = Ext.extend(Ext.form.DateField, {

    //negative year formats will parse but give a positive year 
    altFormats : "-c|-Y|m -Y|n -Y|M -Y|m/d/-Y|n/j/-Y|m/j/-Y|n/d/-Y|c|Y|" + 
                    "m/d/Y|n/j/Y|n/j/y|m/j/y|n/d/y|m/j/Y|n/d/Y|m-d-y|m-d-Y|" +
                    "m/d|m-d|md|mdy|mdY|d|Y-m-d|n-j|n/j",
    
    bcYrRegEx : /^(-\d{3,4})|(-\d{3,4})$/,
    
    invalidText : "{0} is not a valid date. If you are attempting to enter a BCE date please enter a zero padded 4 digit year or just enter the year",
    
    beforeBlur: Ext.emptyFn,
    
    getValue : function() {
        var value = Ext.form.DateField.superclass.getValue.call(this);
        var date = this.parseDate(value);
        var bcYear = value.match(this.bcYrRegEx) && ((value.match(/-/g)||[]).length === 1 || value.charAt(0) === '-');
        if (bcYear) {
            if(date){
                date = new Date(-1*date.getFullYear(),date.getMonth(),date.getDate(),date.getHours(),date.getMinutes(),date.getSeconds());
            }
        }
        return (date) ? date.getTime()/1000 : null;
    },

    setValue: function(v) {
        var d = v;
        if (Ext.isNumber(parseFloat(v))) {
            d = new Date(parseFloat(v)*1000);
            d.setTime( d.getTime() + d.getTimezoneOffset()*60*1000 );
        }
        var str = this.formatDate(d);
        if (str) {
            var bcYear = str.match(this.bcYrRegEx);
            if (bcYear) {
                bcYear = bcYear[0] || bcYear[1];
                if (bcYear && bcYear.length < 5) {
                    var zeropad = '-';
                    for (var i=bcYear.length;i<=4; ++i) {
                        zeropad += '0';
                    }
                    str = str.replace(bcYear, zeropad + Math.abs(parseInt(bcYear, 10)));
                }
            }
        }
        return Ext.form.DateField.superclass.setValue.call(this, str);
    },

    getPickerDate: function() {
        return new Date();
    },

    onTriggerClick : function(){
        if(this.disabled){
            return;
        }
        if(!this.menu){
            this.menu = new Ext.menu.DateMenu({
                hideOnClick: false,
                focusOnSelect: false
            });
        }
        this.onFocus();
        Ext.apply(this.menu.picker,  {
            minDate : this.minValue,
            todayText: this.todayText ? this.todayText: Ext.DatePicker.prototype.todayText,
            selectToday: this.selectToday ? this.selectToday: Ext.DatePicker.prototype.selectToday,
            maxDate : this.maxValue,
            disabledDatesRE : this.disabledDatesRE,
            disabledDatesText : this.disabledDatesText,
            disabledDays : this.disabledDays,
            disabledDaysText : this.disabledDaysText,
            format : this.format,
            showToday : this.showToday,
            startDay: this.startDay,
            minText : String.format(this.minText, this.formatDate(this.minValue)),
            maxText : String.format(this.maxText, this.formatDate(this.maxValue))
        });
        // changed code
        var d;
        var v = this.getValue();
        if (v === null) {
            d = this.getPickerDate();
        } else {
            d = new Date(v*1000);
        }
        this.menu.picker.setValue(d);
        // end of change
        this.menu.show(this.el, "tl-bl?");
        this.menuEvents('on');
    }

});

Ext.reg('gxp_datefield', gxp.form.ExtendedDateField);
