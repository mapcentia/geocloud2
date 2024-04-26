<?php
//$requiredParams = ['client_id', 'client_secret', 'scope', 'redirect_uri', 'response_type', 'state'];
$requiredParams = ['response_type', 'client_id'];

foreach ($requiredParams as $requiredParam) {
    if (!array_key_exists($requiredParam, $_GET)) {
        echo "<div id='alert' hx-swap-oob='true'>Required parameter is missing: $requiredParam</div>";
        exit();
    }
    if ($requiredParam == 'response_type' && !($_GET[$requiredParam] == 'token' || $_GET[$requiredParam] == 'code') ) {
        echo "<div id='alert' hx-swap-oob='true'>$requiredParam must be either token or code</div>";
        exit();
    }
}
