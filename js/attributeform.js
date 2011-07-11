Ext.namespace('attributeForm');

attributeForm.init = function (layer) {
	Ext.QuickTips.init();
    // create attributes store
    attributeForm.attributeStore = new GeoExt.data.AttributeStore({
        url: '/wfs/' + screenName + '?REQUEST=DescribeFeatureType&TYPENAME=' + layer
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
