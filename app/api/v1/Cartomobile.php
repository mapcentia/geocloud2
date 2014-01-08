<?php
namespace app\api\v1;

class CartoMobile extends \app\inc\Controller
{
    function __construct()
    {


    }

    function get_index()
    {
        $postgisschema = \app\inc\Input::getPath()->part(5);
        $cartomobile = new \app\models\Cartomobile();
        header('Content-Type: text/xml');
        echo '<MobileConfiguration xmlns="http://www.cluetrust.com/XML/C11aMobileConfig/1/0">
        <!--Created by MapCentia-->';
        echo $cartomobile->getXml($postgisschema);
        echo '</MobileConfiguration>';
    }
}