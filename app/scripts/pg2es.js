var pg = require('pg');
var nconf = require('nconf');
var request = require("request");

nconf.argv();
var db = (nconf.get()._[0]);
var host = nconf.get("host") || "127.0.0.1";
var user = nconf.get("user") || "postgres";
var key = nconf.get("key") || null;
var pgConString = "postgres://" + user + "@" + host + "/" + db;

if (!db) {
    console.log("usage: test.js [database]");
    process.exit(1);
} else {
    console.log("Listen on database: " + db + " @ " + host + " with user " + user);
}

pg.connect(pgConString, function (err, client) {
    if (err) {
        console.log(err);
    }
    client.on('notification', function (msg) {
        var uri, split = msg.payload.split(","), url;
        if (split[0] === "UPDATE" || split[0] === "INSERT") {
            uri = "upsert/" + db + "/" + split[1] + "/" + split[2] + "/" + split[3] + "/" + split[4];
        }
        if (split[0] === "DELETE") {
            uri = "delete/" + db + "/" + split[1] + "/" + split[2] + "/" + split[3];
        }
        url = "http://" + host + "/api/v1/elasticsearch/" + uri;
        if (key) {
            url = url + "?key=" + key;
        }
        request.get(url, function (err, res, body) {
            console.log(body)
            if (!err) {
                var resultsObj = JSON.parse(body);
                console.log(resultsObj);
            } else {
                console.log(err);
            }
        });

    });
    var query = client.query("LISTEN _gc2_notify_transaction");
});