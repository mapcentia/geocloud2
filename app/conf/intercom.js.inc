<script id="IntercomSettingsScriptTag">
    window.intercomSettings = {
        // TODO: The current logged in user's email address.
        email: "<?php echo $_SESSION['email']; ?>",
        // TODO: The current logged in user's sign-up date as a Unix timestamp.
        created_at: <?php echo $_SESSION['created']; ?>,
        widget: {
            activator: "#IntercomDefaultWidget"
        },
        zone: "<?php echo $_SESSION['zone']; ?>",
        user_hash: "<?php echo hash_hmac("sha256", $_SESSION['email'], "o9n0p044wnqsjYZX5QRN_W4AezyeHPEVBagyZfXQ");?>",
        app_id: "154aef785c933674611dca1f823960ad5f523b92"
    };
    (function () {
        var w = window;
        var ic = w.Intercom;
        if (typeof ic === "function") {
            ic('reattach_activator');
            ic('update', intercomSettings);
        } else {
            var d = document;
            var i = function () {
                i.c(arguments)
            };
            i.q = [];
            i.c = function (args) {
                i.q.push(args)
            };
            w.Intercom = i;
            function l() {
                var s = d.createElement('script');
                s.type = 'text/javascript';
                s.async = true;
                s.src = 'https://api.intercom.io/api/js/library.js';
                var x = d.getElementsByTagName('script')[0];
                x.parentNode.insertBefore(s, x);
            }

            if (w.attachEvent) {
                w.attachEvent('onload', l);
            } else {
                w.addEventListener('load', l, false);
            }
        }
    })()
</script>