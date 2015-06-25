Ext.namespace('apiKey');
apiKey.form = new Ext.FormPanel({
    frame: false,
    border: false,
    autoHeight: false,
    region: "center",
    //bodyStyle: 'padding: 10px 10px 0 10px;',
    labelWidth: 1,
    defaults: {
        anchor: '95%',
        allowBlank: false,
        msgTarget: 'side'
    },
    items: [new Ext.Panel({
        frame: false,
        border: false,
        bodyStyle: 'padding: 7px 7px 10px 7px;',
        contentEl: "apikey"
    })],
    buttons: [
        {
            text: __('New API key'),
            handler: function () {
                "use strict";
                Ext.MessageBox.confirm(__('Confirm'), __('Are you sure you want to do that?'), function (btn) {
                    if (btn === "yes") {
                        $.ajax(
                            {
                                url: '/controllers/setting/apikey',
                                async: false,
                                dataType: 'json',
                                type: 'put',
                                success: function (data, textStatus, http) {
                                    if (http.readyState === 4) {
                                        if (http.status === 200) {
                                            var response = eval('(' + http.responseText + ')');
                                            if (response.success === true) {
                                                $("#apikeyholder").html(response.key)
                                            } else {
                                                var message = __("Sorry! Some thing failed.");
                                                Ext.MessageBox.show({
                                                    title: __('Failure'),
                                                    msg: message,
                                                    buttons: Ext.MessageBox.OK,
                                                    width: 300,
                                                    height: 300
                                                });
                                            }
                                        }
                                    }
                                }
                            }
                        ).fail(
                            function () {
                                var message = __("Sorry! Connection problems");
                                Ext.MessageBox.show(
                                    {
                                        title: __('Failure'),
                                        msg: message,
                                        buttons: Ext.MessageBox.OK,
                                        width: 300,
                                        height: 300
                                    }
                                );
                            }
                        );
                    } else {
                        return false;
                    }
                });
            }
        }
    ]
});
