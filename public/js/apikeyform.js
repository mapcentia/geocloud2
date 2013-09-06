Ext.namespace('apiKey');
apiKey.form = new Ext.FormPanel({
    frame : false,
    border : false,
    autoHeight : false,
    region: "west",
    //bodyStyle: 'padding: 10px 10px 0 10px;',
    labelWidth : 1,
    defaults : {
        anchor : '95%',
        allowBlank : false,
        msgTarget : 'side'
    },
    items : [new Ext.Panel({
        frame : false,
        border : false,
        bodyStyle : 'padding: 7px 7px 10px 7px;',
        contentEl : "apikey"
    })],
    buttons : [{
        text : 'New API key',
        handler : function() {"use strict";
            Ext.MessageBox.confirm('Confirm', 'Are you sure you want to do that?', function(btn) {
                if (btn === "yes") {
                    $.ajax({
                        url : '/controller/settings_viewer/' + screenName + '/updateapikey',
                        async : false,
                        dataType : 'json',
                        type : 'GET',
                        success : function(data, textStatus, http) {
                            console.log(http);
                            if (http.readyState === 4) {
                                if (http.status === 200) {
                                    var response = eval('(' + http.responseText + ')');
                                    if (response.success === true) {
                                        $("#apikeyholder").html(response.key)
                                    } else {
                                        var message = "<p>Sorry! Some thing failed.</p>";
                                        Ext.MessageBox.show({
                                            title : 'Failure',
                                            msg : message,
                                            buttons : Ext.MessageBox.OK,
                                            width : 300,
                                            height : 300
                                        });
                                    }
                                }
                            }
                        }
                    }).fail(function() {
                        var message = "<p>Sorry! Connection problems</p>";
                        Ext.MessageBox.show({
                            title : 'Failure',
                            msg : message,
                            buttons : Ext.MessageBox.OK,
                            width : 300,
                            height : 300
                        });
                    });
                }
                else {
                    return false;
                }
            });
        }
    }]
});
