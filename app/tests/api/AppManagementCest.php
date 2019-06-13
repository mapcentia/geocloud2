<?php 

class AppManagementCest
{
    public function __construct()
    {
    }

    /*
    public function shouldProvidePubliclyAvailableSettingsInFormOfJavaScriptCode(\ApiTester $I)
    {
        $I->sendGET('http://localhost:80/api/v1/baselayerjs');

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseContains('window.gc2Options = {');
        $I->seeResponseContains("window.bingApiKey = 'your_bing_map_key';");
        $I->seeResponseContains("window.setBaseLayers = [{");
        $I->seeResponseContains("window.mapAttribution = 'Powered by <a href=\"http://geocloud.mapcentia.com\">MapCentia</a> ';");
        $I->seeResponseContains("window.gc2Al='en_US'");
    }

    public function shouldProvidePubliclyAvailableSettingsInFormOfJSON(\ApiTester $I)
    {
        $I->sendGET('http://localhost:80/api/v1/baselayerjs?format=json');

        //var_dump(json_decode($I->grabResponse()));

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
    }
    */
}
