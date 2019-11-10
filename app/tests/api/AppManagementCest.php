<?php 

class AppManagementCest
{
    public function __construct()
    {
    }

    public function shouldProvidePubliclyAvailableSettingsInFormOfJavaScriptCode(\ApiTester $I)
    {
        $I->sendGET('http://localhost:80/api/v1/baselayerjs');

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseContains('window.gc2Options = {');
        $I->seeResponseContains("window.setBaseLayers = [{");
    }

    public function shouldProvidePubliclyAvailableSettingsInFormOfJSON(\ApiTester $I)
    {
        $I->sendGET('http://localhost:80/api/v1/baselayerjs?format=json');

        $I->seeResponseCodeIs(\Codeception\Util\HttpCode::OK);
        $I->seeResponseIsJson();
    }
}
