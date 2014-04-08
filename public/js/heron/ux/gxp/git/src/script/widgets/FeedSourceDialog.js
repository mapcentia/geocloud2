/**
 * Copyright (c) 2008-2011 The Open Planning Project
 *
 * Published under the GPL license.
 * See https://github.com/opengeo/gxp/raw/master/license.txt for the full text
 * of the license.
 */

/**
 * @requires plugins/FeedSource.js
 * @requires plugins/PicasaFeedSource.js
 * @requires plugins/YouTubeFeedSource.js
 * @requires widgets/PointSymbolizer.js
 */

/** api: (define)
 *  module = gxp
 *  class = FeedSourceDialog
 *  base_link = `Ext.Container <http://extjs.com/deploy/dev/docs/?class=Ext.Container>`_
 */

Ext.namespace("gxp");

/** api: constructor
 *  .. class:: FeedSourceDialog(config)
 *
 *      A  dialog for creating a GeoRSS feed layer
 */
gxp.FeedSourceDialog = Ext.extend(Ext.Container, {
    /** api: config[feedTypeText] ``String`` i18n */
    feedTypeText: "Source",
    /** api: config[addPicasaText] ``String`` i18n */
    addPicasaText: "Picasa Photos",
    /** api: config[addYouTubeText] ``String`` i18n */
    addYouTubeText: "YouTube Videos",
    /** api: config[addRSSText] ``String`` i18n */
    addRSSText: "GeoRSS Feed",
    /** api: config[addFeedText] ``String`` i18n */
    addFeedText: "Add to Map",
    /** api: config[addTitleText] ``String`` i18n */
    addTitleText: "Title",
    /** api: config[keywordText] ``String`` i18n */
    keywordText: "Keyword",
    /** api: config[doneText] ``String`` i18n */
    doneText: "Done",
    /** api: config[titleText] ``String`` i18n */
    titleText: "Add Feeds",
    /** api: config[maxResultsText] ``String`` i18n */
    maxResultsText: "Max Items",

    /**
     * api: config[width]
     * ``Number`` width of dialog
     */
    width: 300,

    /**
     * api: config[autoHeight]
     * ``Boolean`` default is true
     */
    autoHeight: true,

    /**
     * api: config[closeAction]
     * ``String`` default is destroy
     */
    closeAction: 'destroy',

    /** private: method[initComponent]
     */
    initComponent: function() {

        /** api: event[addfeed]
         * Fired after the dialog form is submitted.
         * Intended to be used for adding the feed
         * layer to the map
         */
        this.addEvents("addfeed");

        if (!this.feedTypes) {
            this.feedTypes  = [
                [gxp.plugins.PicasaFeedSource.ptype, this.addPicasaText],
                [gxp.plugins.YouTubeFeedSource.ptype, this.addYouTubeText],
                [gxp.plugins.FeedSource.ptype, this.addRSSText]
            ];
        }

        var feedStore = new Ext.data.ArrayStore({
            fields: ['type', 'name'],
            data : this.feedTypes
        });

        var sourceTypeSelect = new Ext.form.ComboBox({
            store: feedStore,
            fieldLabel: this.feedTypeText,
            displayField:'name',
            valueField:'type',
            typeAhead: true,
            width: 180,
            mode: 'local',
            triggerAction: 'all',
            emptyText:'Select a feed source...',
            selectOnFocus:true,
            listeners: {
                "select": function(choice) {
                    if (choice.value == gxp.plugins.FeedSource.ptype) {
                        urlTextField.show();
                        keywordTextField.hide();
                        maxResultsField.hide();
                        symbolizerField.show();
                    } else {
                        urlTextField.hide();
                        keywordTextField.show();
                        maxResultsField.show();
                        symbolizerField.hide();
                    }
                    submitButton.setDisabled(choice.value == null);
                },
                scope: this
            }
        });

        var urlTextField = new Ext.form.TextField({
            fieldLabel: "URL",
            allowBlank: false,
            //hidden: true,
            width: 180,
            msgTarget: "right",
            validator: this.urlValidator.createDelegate(this)
        });

        var keywordTextField = new Ext.form.TextField({
            fieldLabel: this.keywordText,
            allowBlank: true,
            hidden: true,
            width: 180,
            msgTarget: "right"
        });

        var titleTextField = new Ext.form.TextField({
            fieldLabel: this.addTitleText,
            allowBlank: true,
            width: 180,
            msgTarget: "right"
        });

        var maxResultsField = new Ext.form.ComboBox({
            fieldLabel: this.maxResultsText,
            hidden: true,
            hiddenName: 'max-results',
            store: new Ext.data.ArrayStore({
                fields: ['max-results'],
                data : [[10],[25],[50],[100]]
            }),
            displayField: 'max-results',
            mode: 'local',
            triggerAction: 'all',
            emptyText:'Choose number...',
            labelWidth: 70,
            width: 180,
            defaults: {
                labelWidth: 70,
                width:180
            }
        });


        var symbolizerField = new gxp.PointSymbolizer({
            bodyStyle: {padding: "10px"},
            width: 280,
            border: false,
            hidden: true,
            labelWidth: 70,
            defaults: {
                labelWidth: 70
            },
            symbolizer: {pointGraphics: "circle", pointRadius: "5"}
        });


        symbolizerField.find("name", "rotation")[0].hidden = true;

        if (this.symbolType === "Point" && this.pointGraphics) {
            cfg.pointGraphics = this.pointGraphics;
        }

        var submitButton =  new Ext.Button({
            text: this.addFeedText,
            iconCls: "gxp-icon-addlayers",
            disabled: true,
            handler: function() {
                var ptype = sourceTypeSelect.getValue();
                var config = {
                    "name" : titleTextField.getValue()
                };

                if (ptype != "gxp_feedsource") {
                    config.params = {"q" : keywordTextField.getValue(), "max-results" : maxResultsField.getValue()};
                } else {
                    config.url = urlTextField.getValue();
                    var symbolizer = symbolizerField.symbolizer;
                    config.defaultStyle = {};
                    config.selectStyle = {};
                    Ext.apply(config.defaultStyle, symbolizer);
                    Ext.apply(config.selectStyle, symbolizer);
                    Ext.apply(config.selectStyle, {
                        fillColor: "Yellow",
                        pointRadius: parseInt(symbolizer["pointRadius"]) + 2
                    });
                }

                this.fireEvent("addfeed", ptype, config);

            },
            scope: this
        });


        var bbarItems = [
            "->",
            submitButton,
            new Ext.Button({
                text: this.doneText,
                handler: function() {
                    this.hide();
                },
                scope: this
            })
        ];

        this.panel = new Ext.Panel({
            bbar: bbarItems,
            autoScroll: true,
            items: [
                sourceTypeSelect,
                titleTextField,
                urlTextField,
                keywordTextField,
                maxResultsField,
                symbolizerField
            ],
            layout: "form",
            border: false,
            labelWidth: 100,
            bodyStyle: "padding: 5px",
            autoWidth: true,
            autoHeight: true
        });

        this.items = this.panel;

        gxp.FeedSourceDialog.superclass.initComponent.call(this);

    },



    /** private: property[urlRegExp]
     *  `RegExp`
     *
     *  We want to allow protocol or scheme relative URL
     *  (e.g. //example.com/).  We also want to allow username and
     *  password in the URL (e.g. http://user:pass@example.com/).
     *  We also want to support virtual host names without a top
     *  level domain (e.g. http://localhost:9080/).  It also makes sense
     *  to limit scheme to http and https.
     *  The Ext "url" vtype does not support any of this.
     *  This doesn't have to be completely strict.  It is meant to help
     *  the user avoid typos.
     */
    urlRegExp: /^(http(s)?:)?\/\/([\w%]+:[\w%]+@)?([^@\/:]+)(:\d+)?\//i,

    /** private: method[urlValidator]
     *  :arg url: `String`
     *  :returns: `Boolean` The url looks valid.
     *
     *  This method checks to see that a user entered URL looks valid.  It also
     *  does form validation based on the `error` property set when a response
     *  is parsed.
     */
    urlValidator: function(url) {
        var valid;
        if (!this.urlRegExp.test(url)) {
            valid = this.invalidURLText;
        } else {
            valid = !this.error || this.error;
        }
        // clear previous error message
        this.error = null;
        return valid;
    }


});

/** api: xtype = gxp_feedsourcedialog */
Ext.reg('gxp_feedsourcedialog', gxp.FeedSourceDialog);



