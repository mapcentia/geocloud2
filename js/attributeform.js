Ext.namespace('attributeForm');
Ext.namespace("filter");
attributeForm.init = function (layer) {
	Ext.QuickTips.init();
    // create attributes store
    attributeForm.attributeStore = new GeoExt.data.AttributeStore({
        url: '/wfs/' + screenName + '/' + schema + '?REQUEST=DescribeFeatureType&TYPENAME=' + layer,
        listeners: {
        load: {scope:this, fn:function(_store)  {
        	attributeForm.attributeStoreCopy = 
			new Ext.data.ArrayStore({
				fields: ['value', 'name'],
				data: [
					
				   ]
			});
        	_store.each(function(record) {
				var match = /gml:((Multi)?(Point|Line|Polygon|Curve|Surface)).*/.exec(record.get("type"));
				if (!match) {
					var newDataRow = {"name":record.get("name"), "value":getFieldType(record.get("type"))};
					var newRecord = new attributeForm.attributeStore.recordType(newDataRow);
					attributeForm.attributeStoreCopy.add(newRecord);
				}
        	},this);
			//console.log(attributeForm.attributeStoreCopy);
			filter.filterBuilder = new gxp.FilterBuilder({
            			attributes: attributeForm.attributeStoreCopy,
            			allowGroups: false
					
        			});
			filter.queryPanel = new Ext.Panel({
            id: "uploadpanel",
            frame: false,
            region: "center",
			bodyStyle: {
							background: '#ffffff',
							padding: '7px'
						},
         	tbar: ["->",
            {
                text: "Query",
                iconCls: "icon-find",
                disabled: false,
                handler: function () {
                    filter.queryPanel.query();
                }
            }],
            query: function () {
                var protocol = store.proxy.protocol;
                protocol.defaultFilter = filter.filterBuilder.getFilter();
                store.load();
            layer;},
            items: [filter.filterBuilder]
        });
		filter.win = new Ext.Window( {
			title : "Feature filter",
			modal : false,
			layout : 'fit',
			initCenter : false,
			border: false,
			x : 25,
			y : 100,
			width : 400,
			height : 400,
			closeAction : 'hide',
			plain : true,
			items : [ new Ext.Panel( {
				frame : false,
				layout : 'border',
				items : [filter.queryPanel]
			}) ]
		});
        	}
        }}
        
    });
    
    
     

    attributeForm.form = new Ext.form.FormPanel({
        autoScroll: true,
		region: 'center',
		border: false,
		bodyStyle: {
							background: '#ffffff',
							padding: '7px'
						},
        defaults: {
            width: 110,
            maxLengthText: "too long",
            minLengthText: "too short"
        },
        plugins: [
            new GeoExt.plugins.AttributeForm({
                attributeStore: attributeForm.attributeStore
            })
        ],
		buttons: [{
                //iconCls: 'silk-add',
                text: 'Update feature',
                handler: function () {
				
					 if (attributeForm.form.form.isValid()) {
                   var record = grid.getSelectionModel().getSelected();
				   attributeForm.form.getForm().updateRecord(record);
				   var feature = record.get("feature");
					if (feature.state !== OpenLayers.State.INSERT) {
					feature.state = OpenLayers.State.UPDATE;
				}
                   //attributeForm.win.close();
				   }
                        
					
					else {
                        var s = '';
                        Ext.iterate(detailForm.form.form.getValues(), function (key, value) {
                            s += String.format("{0} = {1}<br />", key, value);
                        }, this);
                        //Ext.example.msg('Form Values', s);
                    }}
  
                
            }]
    });

    attributeForm.attributeStore.load();
};
attributeForm.onSubmit = function(){};
function getFieldType(attrType) {
		//alert(attrType);
        return ({
            "xs:boolean": "boolean",
            "xs:int": "int",
            "xs:integer": "int",
            "xs:short": "int",
            "xs:long": "int",
            "xs:date": "date",
            "xs:string": "string",
            "xs:float": "float",
            "xs:double": "float",
            "gml:PointPropertyType": "int"
    	    })[attrType];
    		};
