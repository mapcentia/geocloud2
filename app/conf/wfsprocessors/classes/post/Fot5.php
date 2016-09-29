<?php

namespace app\conf\wfsprocessors\classes\post;

use app\conf\wfsprocessors\PostInterface;
use app\conf\wfsprocessors\classes\pre\Fot3;

class Fot5 implements PostInterface
{
    private $logFile;
    private $serializer;
    private $unserializer;
    private $db;

    function __construct($db)
    {
        $this->db = $db;
        $this->serializer = new \XML_Serializer();
        $unserializer_options = array(
            'parseAttributes' => TRUE,
            'typeHints' => FALSE
        );
        $this->unserializer = new \XML_Unserializer($unserializer_options);
        $this->logFile = fopen(dirname(__FILE__) . "/../../../../../public/logs/geodanmark.log", "a");
    }

    function __destruct()
    {
        fclose($this->logFile);
    }

    private function log($txt)
    {
        fwrite($this->logFile, $txt);
    }

    /**
     * @param $xml
     * @return string
     */
    private function formatXml($xml)
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        return $dom->saveXML();
    }

    /**
     * @param $transactionXml
     * @return mixed
     */
    private function post($transactionXml)
    {
        $ch = curl_init("https://fot.kms.dk/FAS/TransactionServlet");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "user=MH&pw=hksj7rHlf8&transactionxml=" . $transactionXml);
        return curl_exec($ch);
    }

    public function process()
    {
        // TODO Check if empty
        $transactions = fot3::getTransactions();

        $transactionsReady = '<?xml version="1.0" encoding="UTF-8"?>
                <wfs:Transaction version="1.1.0" service="WFS"
                xmlns="http://schemas.kms.dk/fot/FOT5.1_svid90_inputMedInterval_version1"
                xmlns:ogc="http://www.opengis.net/ogc"
                xmlns:gml="http://www.opengis.net/gml"
                xmlns:wfs="http://www.opengis.net/wfs"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://schemas.kms.dk/fot/FOT5.1_svid90_inputMedInterval_version1 http://schemas.kms.dk/fot/FOT5.1_svid90_inputMedInterval_version1.xsd http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd">
                ' . $transactions .
            '</wfs:Transaction>';


        $this->log("---------- " . date('l jS \of F Y H:i:s') . " ----------\n\n");
        $this->log(($this->formatXml($transactionsReady)) . "\n");

        //die($transaction);
        $buffer = $this->post($transactionsReady);

        $this->log("\n    - Response -\n\n");

        $this->log($this->formatXml($buffer) . "\n\n");
        $this->log("\n\n");

        $status = $this->unserializer->unserialize($buffer);
        if (isset($status->error_message_prefix)) {
            $res["success"] = false;
            $res["message"] = "Noget gik galt";
            return $res;
        }
        $resFromFot = $this->unserializer->getUnserializedData();
        $res = [];
        if ($resFromFot["Exception"]) {
            $res["success"] = false;
            $res["message"] = strip_tags(html_entity_decode($resFromFot["Exception"]["ExceptionText"]));

        } else {
            $res["success"] = true;
            //print_r($resFromFot);
            $oldFotId = $resFromFot["wfs:InsertResults"]["wfs:Feature"]["handle"];
            $newFotId = $resFromFot["wfs:InsertResults"]["wfs:Feature"]["ogc:FeatureId"]["fid"];
            $sql = "UPDATE geodanmark.bygning SET gml_id=:new WHERE gml_id=:old";
            $resUpdate = $this->db->prepare($sql);
            try {
                $resUpdate->execute(["new"=>$newFotId, "old"=>$oldFotId]);
            } catch (\PDOException $e) {
                makeExceptionReport(print_r($e, true));
            }

        }
        return $res;
    }
}