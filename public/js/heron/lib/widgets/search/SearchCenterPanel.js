/*
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
Ext.namespace("Heron.widgets.search");

/** api: (define)
 *  module = Heron.widgets.search
 *  class = SearchCenterPanel
 *  base_link = `GeoExt.form.FormPanel <http://www.geoext.org/lib/GeoExt/widgets/form/FormPanel.html>`_
 */

/** api: example
 *  Sample code showing how to configure a Heron SearchCenterPanel.
 *  Note that the  config here contains both a Heron FormSearchPanel object (search form) and a Heron FeaturePanel
 *  (result panel). Other possible SearchPanels to use are: SpatialSearchPanel and  GXP_QueryPanel.
 *
 *  .. code-block:: javascript

     {
        xtype: 'hr_searchcenterpanel',
        id: 'hr-searchcenterpanel',
        title: __('Search'),

        hropts: {
            searchPanel: {
                xtype: 'hr_formsearchpanel',
                id: 'hr-formsearchpanel',
                header: false,
                bodyStyle: 'padding: 6px',
                style: {
                    fontFamily: 'Verdana, Arial, Helvetica, sans-serif',
                    fontSize: '12px'
                },
                protocol: new OpenLayers.Protocol.WFS({
                    version: "1.1.0",
                    url: "http://kademo.nl/gs2/wfs?",
                    srsName: "EPSG:28992",
                    featureType: "hockeyclubs",
                    featureNS: "http://innovatie.kadaster.nl"
                }),
                items: [
                    {
                        xtype: "textfield",
                        name: "name__like",
                        value: 'H.C*',
                        fieldLabel: "  name"
                    },
                    {
                        xtype: "label",
                        id: "helplabel",
                        html: 'Type name of an NL hockeyclub, use * as wildcard<br/>',
                        style: {
                            fontSize: '10px',
                            color: '#AAAAAA'
                        }
                    }
                ],
                hropts: {
                    onSearchCompleteZoom : 11
                }
            },
            resultPanel: {
                xtype: 'hr_featurepanel',
                id: 'hr-featurepanel',
                title: __('Search'),
                header: false,
                columns: [
                    {
                        header: "Name",
                        width: 100,
                        dataIndex: "name",
                        type: 'string'
                    },
                    {
                        header: "Desc",
                        width: 200,
                        dataIndex: "cmt",
                        type: 'string'
                    }
                ],
                 hropts: {
                      zoomOnRowDoubleClick : true,
                     zoomOnFeatureSelect : true,
                     zoomLevelPointSelect : 8
                 }

            }
        }
    }
 */

/** api: constructor
 *  .. class:: SearchCenterPanel(config)
 *
 *  A panel designed to hold a (geo-)search form plus results (features) in grid and on map.
 *  Combines both the FeaturePanel and SearchPanel widgets
 */
Heron.widgets.search.SearchCenterPanel = Ext.extend(Ext.Panel, {

	initComponent: function () {
		var self = this;

		// Define SearchPanel and lazily the ResultPanel in card layout.
		Ext.apply(this, {
			layout: 'card',
			activeItem: 0,
			bbar: [
				{
					text: __('< Search'),
					ref: '../prevButton',
					disabled: true,
					handler: function () {
						self.showSearchPanel(self);
					}
				},
				'->',
				{
					text: __('Result >'),
					ref: '../nextButton',
					disabled: true,
					handler: function () {
						self.showResultGridPanel(self);
					}
				}
			]
		});

		// Items may have been set by subclass
		if (!this.items) {
			this.items = [this.hropts.searchPanel];
		}

		// Cleanup.
		if (this.ownerCt) {
			// If we are contained in Window act on hide/show
			this.ownerCt.addListener("hide", this.onParentHide, this);
			this.ownerCt.addListener("show", this.onParentShow, this);

			// Setup our own events
			this.addEvents({
				"parenthide": true,
				"parentshow": true
			});
		}

		Heron.widgets.search.SearchCenterPanel.superclass.initComponent.call(this);

		this.addListener("afterrender", this.onRendered, this);
	},

	/***
	 * Display search form.
	 */
	showSearchPanel: function (self) {
		self.getLayout().setActiveItem(this.searchPanel);
		self.prevButton.disable();
		self.nextButton.disable();
        if (this.resultPanel && this.resultPanel.hasFeatures()) {
            self.nextButton.enable();
        }
	},

	/***
	 * Display result grid.
	 */
	showResultGridPanel: function (self) {
		self.getLayout().setActiveItem(this.resultPanel);
		self.prevButton.enable();
		self.nextButton.disable();
	},

	/***
	 * Callback when our Panel has been rendered.
	 */
	onRendered: function () {
		this.searchPanel = this.getComponent(0);
		if (this.searchPanel) {
			this.searchPanel.addListener('searchissued', this.onSearchIssued, this);
			this.searchPanel.addListener('searchsuccess', this.onSearchSuccess, this);
			this.searchPanel.addListener('searchcomplete', this.onSearchComplete, this);
            this.searchPanel.addListener('searchreset', this.onSearchReset, this);
		}
	},

	/***
	 * Callback from SearchPanel when search just issued.
	 */
	onSearchIssued: function (searchPanel) {
		this.showSearchPanel(this);
		this.nextButton.disable();
	},

	/***
	 * Callback from SearchPanel when search just issued.
	 */
	onSearchComplete: function (searchPanel) {
	},

    /***
   	 * Callback from SearchPanel when searchform reset.
   	 */
   	onSearchReset: function (searchPanel) {
        if (this.resultPanel) {
            this.resultPanel.removeFeatures();
        }
   	},

	/***
	 * Callback from SearchPanel on successful search.
	 */
	onSearchSuccess: function (searchPanel, result) {
		if (this.hropts.resultPanel.autoConfig && this.resultPanel) {
			this.resultPanel.cleanup();
			this.remove(this.resultPanel);
			this.resultPanel = null;
		}

        var features = result.olResponse.features;
		if (!this.resultPanel) {
			// Create Result Panel the first time
			// For autoConfig the features are used to setup grid columns
			this.hropts.resultPanel.features = features;
            this.hropts.resultPanel.downloadInfo = result.downloadInfo;
            this.hropts.resultPanel.featureType = searchPanel.getFeatureType();
			this.resultPanel = new Heron.widgets.search.FeaturePanel(this.hropts.resultPanel);

			// Will be item(1) in card layout
			this.add(this.resultPanel);
		}

		// Show result in card layout
		this.resultPanel.loadFeatures(features, searchPanel.getFeatureType());
		if (features && features.length > 0) {
			this.showResultGridPanel(this);
		}
	},

	/** private: method[onParentShow]
	 * Called usually before our panel is created.
	 */
	onParentShow: function () {
		if (this.resultPanel) {
			this.showSearchPanel(this);
		}
		this.fireEvent('parentshow');
	},

	/** private: method[onParentHide]
	 * Cleanup usually before our panel is hidden.
	 */
	onParentHide: function () {
		this.fireEvent('parenthide');
	}
});

/** api: xtype = hr_searchcenterpanel */
Ext.reg('hr_searchcenterpanel', Heron.widgets.search.SearchCenterPanel);

/** Compatibilty with pre 0.73. renamed Heron.widgets.FeatSelSearchPanel to Heron.widgets.search.SearchCenterPanel */
Ext.reg('hr_featselsearchpanel', Heron.widgets.search.SearchCenterPanel);


