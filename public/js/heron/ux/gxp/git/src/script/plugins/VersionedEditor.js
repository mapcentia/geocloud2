/**
 * Copyright (c) 2008-2012 The Open Planning Project
 * 
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires plugins/FeatureEditorGrid.js
 */

Ext.namespace("gxp.plugins");

gxp.plugins.VersionedEditor = Ext.extend(Ext.TabPanel, {

    /** api: config[url]
     *  ``String``
     *  Url of the web-api endpoint of GeoGit.
     */
    url: null,

    /** api: config[historyTpl]
     *  ``String`` Template to use for displaying the commit history.
     *  If not set, a default template will be provided.
     */
    historyTpl: '<ol><tpl for="."><li class="commit"><div class="commit-msg">{message}</div><div>{author} <span class="commit-datetime">authored {date:this.formatDate}</span></div></li></tpl>',

    /* i18n */
    attributesTitle: "Attributes",
    historyTitle: "History",
    hour: "hour",
    hours: "hours",
    day: "day",
    days: "days",
    ago: "ago",
    /* end i18n */

    border: false,
    activeTab: 0,

    /** api: config[editor]
     *  The ptype of the attribute editor to use. One of 'gxp_editorgrid' or
     *  'gxp_editorform'. Defaults to 'gxp_editorgrid'.
     */
    editor: null,

    /** private: property[attributeEditor]
     *  ``gxp.plugins.FeatureEditorGrid`` or ``gxp.plugins.FeatureEditorForm``
     */
    attributeEditor: null,

    /** api: ptype = gxp_versionededitor */
    ptype: "gxp_versionededitor",

    /** private: method[initComponent]
     */
    initComponent: function() {
        gxp.plugins.VersionedEditor.superclass.initComponent.call(this);
        var editorConfig = {
            feature: this.initialConfig.feature,
            schema: this.initialConfig.schema,
            fields: this.initialConfig.fields,
            excludeFields: this.initialConfig.excludeFields,
            propertyNames: this.initialConfig.propertyNames,
            readOnly: this.initialConfig.readOnly
        };
        var config = Ext.apply({
            xtype: this.initialConfig.editor || "gxp_editorgrid",
            title: this.attributesTitle
        }, editorConfig);
        this.attributeEditor = Ext.ComponentMgr.create(config);
        this.add(this.attributeEditor);
        var dataView = this.createDataView();
        this.add({
            xtype: 'panel',
            border: false,
            plain: true,
            layout: 'fit', 
            autoScroll: true, 
            items: [dataView], 
            title: this.historyTitle
        });
    },

    /** private: method[createDataView]
     */
    createDataView: function() {
        var typeName = this.schema.reader.raw.featureTypes[0].typeName;
        var path = typeName.split(":").pop() + "/" + this.feature.fid;
        if (this.url.charAt(this.url.length-1) !== '/') {
            this.url = this.url + "/";
        }
        var command = 'log';
        var url = this.url + command;
        url = Ext.urlAppend(url, 'path=' + path + '&output_format=json');
        var store = new Ext.data.JsonStore({
            url: url,
            root: 'response.commit',
            fields: ['message', 'author', 'email', 'commit', {
                name: 'date', type: 'date', convert: function(value) {
                    return new Date(value);
                }
            }],
            autoLoad: true
        });
        var me = this;
        var tpl = new Ext.XTemplate(this.historyTpl, {
            formatDate: function(value) {
                var now = new Date(), result = '';
                if (value > now.add(Date.DAY, -1)) {
                    var hours = Math.round((now-value)/(1000*60*60));
                    result += hours + ' ';
                    result += (result > 1) ? me.hours : me.hour;
                    result += ' ' + me.ago;
                    return result;
                } else if (value > now.add(Date.MONTH, -1)) {
                    var days = Math.round((now-value)/(1000*60*60*24));
                    result += days + ' ';
                    result += (result > 1) ? me.days : me.day;
                    result += ' ' + me.ago;
                    return result;
                }
            }
        });
        return new Ext.DataView({
            store: store,
            tpl: tpl,
            autoHeight:true
        });
    },

    /** private: method[init]
     *
     *  :arg target: ``gxp.FeatureEditPopup`` The feature edit popup 
     *  initializing this plugin.
     */
    init: function(target) {
        // make sure the editor is not added, we will take care
        // of adding the editor to our container later on
        target.on('beforeadd', OpenLayers.Function.False, this);
        this.attributeEditor.init(target);
        target.un('beforeadd', OpenLayers.Function.False, this);
        target.add(this);
        target.doLayout();
    }

});

Ext.preg(gxp.plugins.VersionedEditor.prototype.ptype, gxp.plugins.VersionedEditor);
