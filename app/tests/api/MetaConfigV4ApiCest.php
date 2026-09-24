<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use Codeception\Util\HttpCode;

/**
 * GET /api/v4/meta-config (app/api/v4/controllers/MetaConfig.php): the merged
 * meta config a client renders as the relation properties form. Checks the
 * shape, that it is the merged result (not only the built-in fieldsets),
 * that a sub-user may read it, and that a token is required.
 */
class MetaConfigV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subUserId;
    private $subToken;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function asSub(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
    }

    public function shouldPrepareUsersAndTokens(ApiTester $I)
    {
        $ts = time();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "metaconfig super $ts", 'email' => "metaconfigsuper$ts@example.com", 'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // Sub-user, created through the super-user's session
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();

        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => "metaconfig sub $ts", 'email' => "metaconfigsub$ts@example.com",
            'password' => $this->password, 'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;
    }

    public function shouldReturnTheMergedFieldsetsAsABareArray(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/meta-config');
        $I->seeResponseCodeIs(HttpCode::OK);
        $fieldsets = json_decode($I->grabResponse());
        $I->assertIsArray($fieldsets, 'the response is a bare JSON array');
        $I->assertNotEmpty($fieldsets);

        $names = array_map(fn($f) => $f->fieldsetName, $fieldsets);
        $I->assertSame($names, array_values(array_unique($names)), 'fieldsetName is unique');
        $I->assertContains('Layer type', $names);

        foreach ($fieldsets as $fieldset) {
            $I->assertIsArray($fieldset->fields);
            foreach ($fieldset->fields as $field) {
                $I->assertNotEmpty($field->name);
                $I->assertContains($field->type, ['text', 'textarea', 'checkbox', 'combo', 'checkboxgroup']);
                $I->assertNotEmpty($field->title);
            }
        }

        // The checkboxgroup of the built-in "Layer type" fieldset, with its choices
        $layerType = array_values(array_filter($fieldsets, fn($f) => $f->fieldsetName === 'Layer type'))[0];
        $type = array_values(array_filter($layerType->fields, fn($f) => $f->name === 'vidi_layer_type'))[0];
        $I->assertSame('checkboxgroup', $type->type);
        $I->assertNotEmpty($type->values);
        $I->assertNotEmpty($type->values[0]->name);
        $I->assertNotEmpty($type->values[0]->value);
    }

    /**
     * The endpoint must serve the same merged result as the old GUI gets from
     * /api/v1/baselayerjs, so a custom metaConfig in App.php shows up in both.
     */
    public function shouldMatchTheMetaConfigOfBaselayerjs(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/meta-config');
        $I->seeResponseCodeIs(HttpCode::OK);
        $fromV4 = json_decode($I->grabResponse(), true);

        $I->sendGET('/api/v1/baselayerjs/' . $this->userId . '?format=json');
        $I->seeResponseCodeIs(HttpCode::OK);
        $settings = json_decode($I->grabResponse(), true);
        $I->assertIsArray($settings['gc2Options']['metaConfig'] ?? null, 'baselayerjs carries metaConfig');
        $I->assertSame($settings['gc2Options']['metaConfig'], $fromV4);
    }

    public function subUserMayReadTheMetaConfig(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET('/api/v4/meta-config');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertNotEmpty(json_decode($I->grabResponse()));
    }

    public function shouldRequireAToken(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->sendGET('/api/v4/meta-config');
        // v4 answers a missing token with 400 "No token in header"
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['success' => false]);
    }

    public function shouldAnswerPreflight(ApiTester $I)
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Access-Control-Request-Method', 'GET');
        $I->sendOPTIONS('/api/v4/meta-config');
        $I->seeResponseCodeIsSuccessful();
        $I->deleteHeader('Access-Control-Request-Method');
    }

    public function shouldRejectWrite(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/meta-config', json_encode([]));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
        $I->seeResponseContainsJson(['errorCode' => 'NOT_ACCEPTABLE']);
    }
}
