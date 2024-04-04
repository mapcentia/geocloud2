<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Scheduler report</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
          integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
            integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
            crossorigin="anonymous"></script>

    <!-- Latest compiled and minified CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-table@1.22.4/dist/bootstrap-table.min.css" rel="stylesheet">


    <!-- Latest compiled and minified JavaScript -->
    <script src="https://unpkg.com/bootstrap-table@1.22.4/dist/bootstrap-table.min.js"></script>

</head>
<body>
<div style="padding: 5px">
    <table class="table table-striped table-sm"
           data-toggle='table'
           data-search='true'
           data-sortable='true'
           data-show-columns='true'
           data-search-highlight='true'
    >
        <thead>
        <tr>
            <th data-sortable='true' data-search-highlight-formatter='customSearchFormatter'>Id</th>
            <th data-sortable='true' data-searchable='false'>Success</th>
            <th data-sortable='true' data-search-highlight-formatter='customSearchFormatter'>Database</th>
            <th data-sortable='true' data-search-highlight-formatter='customSearchFormatter'>Schema</th>
            <th data-sortable='true' data-search-highlight-formatter='customSearchFormatter'>Table</th>
            <th data-sortable='true' data-searchable='false'>Last run</th>
            <th data-sortable='true' data-searchable='false'>Since success</th>
            <th data-sortable='true' data-searchable='false'>Type</th>
            <th data-sortable='true' data-searchable='false'>Geom</th>
            <th data-sortable='true' data-searchable='false'>Feature count</th>
            <th data-sortable='true' data-searchable='false'>Max in cell</th>
            <th data-sortable='true' data-searchable='false'>Duplicates</th>
            <th data-sortable='true' data-searchable='false'>Waited</th>
            <th data-searchable='false'>Log</th>
        </tr>
        </thead>
        <?php
        ini_set("display_errors", "Off");
        error_reporting(3);
        setlocale(LC_ALL, 'da_DK');
        date_default_timezone_set("CET");

        include_once(__DIR__ . "/../../app/conf/App.php");
        new \app\conf\App();
        \app\models\Database::setDb("gc2scheduler");
        $job = new \app\inc\Model();

        $db = $_GET['db'];
        $sql = "SELECT * FROM jobs WHERE active='t' or active=:active";
        if ($db) {
            $sql .= " AND db=:db";
        }
        $sql .= " ORDER BY db, id";
        $res = $job->prepare($sql);
        $in = $_GET['in'] == '1' ? 'f': 't';
        try {
            if ($db) {
                $res->execute(['active'=> $in, 'db' => $db]);
            } else {
                $res->execute(['active'=> $in]);
            }
        } catch (\PDOException $e) {
            print "Error: ";
            print_r($e->getMessage());
        }
        while ($row = $job->fetchRow($res)) {
            $report = json_decode($row["report"], true);
            $lastcheck = $row["lastcheck"] ? "<font color=\"green\">true</font>" : "<font color=\"red\">false</font>";
            $lastcheck = $row["lastcheck"] ? "<font color=\"green\">true</font>" : "<font color=\"red\">false</font>";
            $lastrun = date('D jS \of M Y h:i', strtotime($row["lastrun"]));
            $d1 = new DateTime($row["lasttimestamp"]);
            $d2 = new DateTime();
            $interval = $d2->diff($d1);
            $lasttimestamp = $interval->format('%d days, %h hours, %i minutes');
            $featureCount = is_int($report["featureCount"] / 1000) ? "<font color=\"orange\">{$report["featureCount"]}</font>" : $report["featureCount"];
            $maxCellCount = is_int($report["maxCellCount"] / 1000) ? "<font color=\"orange\">{$report["maxCellCount"]}</font>" : $report["maxCellCount"];
            $dupsCount = isset($report["dupsCount"]) && $report["dupsCount"] == 0 ? "<font color=\"orange\">{$report["dupsCount"]}</font>" : $report["dupsCount"];
            $waited = $report["sleep"] ?? "0";
            $inactiveClass = !$row['active'] ? 'class="table-danger"' : '';

            print "<tr $inactiveClass>
                    <td>{$row["id"]}</td>
                    <td>{$lastcheck}</td>
                    <td>{$row["db"]}</td>
                    <td>{$row["schema"]}</td>
                    <td>{$row["name"]}</td>
                    <td>{$lastrun}</td>
                    <td>$lasttimestamp</td>
                    <td>{$report["downloadType"]}</td>
                    <td>{$row["type"]}</td>
                    <td>{$featureCount}</td>
                    <td>{$maxCellCount}</td>
                    <td>{$dupsCount}</td>
                    <td>{$waited}</td>
                    <td><a target='_blank' href='/logs/{$row["id"]}_scheduler.log'>Link</a></td>
                    </tr>";
        }
        ?>
    </table>
</div>
<script>
    window.customSearchFormatter = function (value, searchText) {
        return value.toString().replace(new RegExp('(' + searchText + ')', 'gim'), '<span style="background-color: pink;border: 1px solid red;border-radius:90px;padding:4px">$1</span>')
    }
    $('table').data('height', ($(window).height() - 40));
</script>
</body>
</html>
