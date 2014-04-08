/** api: override[blur_on_enter]
 *
 *  This override blurs textfields when the Enter key is pressed. Since many
 *  gxp components perform actions on a textfield's change event, using this
 *  override improves user experience by providing the standard behavior of
 *  the Enter key to confirm an input.
 */
(function() {
    Ext.util.Observable.observeClass(Ext.form.TextField);
    Ext.form.TextField.on('specialkey', function(f, e) {
        if (f.hasFocus && e.getKey() === e.ENTER) {
            // delay the blur action so we don't interfer with other listeners
            f.blur.defer(10, f);
        }
    });
}());
