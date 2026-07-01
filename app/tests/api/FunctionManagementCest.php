<?php

use Codeception\Util\HttpCode;

/**
 * Exercises the Lambda-like functions control-plane API surface
 * (app/api/v4/controllers/Func.php). The execution backend defaults to the
 * StubFunctionRunner, so invocations are expected to fail with 501 while still
 * producing an invocation record.
 */
class FunctionManagementCest
{
    private $date;
    private $userName;
    private $password;
    private $userEmail;
    private $userId;
    private $userAccessToken;

    private $functionName;
    private $asyncInvocationId;

    public function __construct()
    {
        $this->date = new DateTime();
        $this->userName = 'Function test super user name ' . $this->date->getTimestamp();
        $this->password = 'A1abcabcabc';
        $this->userEmail = 'functiontest' . $this->date->getTimestamp() . '@example.com';
        $this->functionName = 'test_fn_' . $this->date->getTimestamp();
    }

    public function shouldPrepareUser(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $this->userName,
            'email' => $this->userEmail,
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $response = json_decode($I->grabResponse());
        $this->userId = $response->data->screenname;

        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId,
            'password' => $this->password,
            'schema' => 'public',
        ]));
    }

    public function shouldGetAccessToken(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password',
            'username' => $this->userId,
            'password' => $this->password,
            'database' => $this->userId,
            'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $response = json_decode($I->grabResponse());
        $this->userAccessToken = $response->access_token;
    }

    public function shouldCreateFunction(ApiTester $I)
    {
        $this->auth($I);
        $payload = json_encode([
            'name' => $this->functionName,
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'code' => 'export const handler = async (event) => ({ ok: true });',
            'env' => ['LOG_LEVEL' => 'info'],
            'memory_mb' => 256,
            'timeout_s' => 60,
        ]);
        $I->sendPOST('/api/v4/functions', $payload);
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $location = $I->grabHttpHeader('Location');
        $I->assertStringContainsString('/api/v4/functions/' . $this->functionName, $location);
    }

    public function shouldListFunctions(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/functions');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContains($this->functionName);
    }

    public function shouldGetSingleFunction(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/functions/' . $this->functionName);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'name' => $this->functionName,
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'memory_mb' => 256,
            'timeout_s' => 60,
            'version' => 1,
        ]);
    }

    public function shouldGenerateTypeScriptInterface(ApiTester $I)
    {
        $this->auth($I);
        $I->haveHttpHeader('Accept', 'text/plain');
        $I->sendGET('/api/v4/function-interfaces');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains('export interface Functions {');
        // No dry-run has been done yet -> default types for our function.
        $I->seeResponseContains($this->functionName . '(event: Record<string, unknown>): Promise<unknown>;');
    }

    public function shouldRejectInvalidRuntime(ApiTester $I)
    {
        $this->auth($I);
        $payload = json_encode([
            'name' => 'bad_runtime_fn',
            'runtime' => 'ruby',
            'handler' => 'index.handler',
            'code' => 'noop',
        ]);
        $I->sendPOST('/api/v4/functions', $payload);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INPUT_VALIDATION_ERROR']);
    }

    public function shouldRejectMissingRequiredField(ApiTester $I)
    {
        $this->auth($I);
        // handler and code omitted
        $payload = json_encode([
            'name' => 'incomplete_fn',
            'runtime' => 'nodejs20',
        ]);
        $I->sendPOST('/api/v4/functions', $payload);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INPUT_VALIDATION_ERROR']);
    }

    public function shouldRejectInvalidName(ApiTester $I)
    {
        $this->auth($I);
        $payload = json_encode([
            'name' => 'has spaces!',
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'code' => 'noop',
        ]);
        $I->sendPOST('/api/v4/functions', $payload);
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INPUT_VALIDATION_ERROR']);
    }

    public function shouldRejectInvalidCronSchedule(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/functions', json_encode([
            'name' => 'bad_cron_fn',
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'code' => 'noop',
            'triggers' => ['schedule' => 'every wednesday'],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INVALID_CRON']);
    }

    public function shouldCreateAndShowScheduledFunction(ApiTester $I)
    {
        $this->auth($I);
        $name = 'scheduled_fn_' . $this->date->getTimestamp();
        $I->sendPOST('/api/v4/functions', json_encode([
            'name' => $name,
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'code' => 'export const handler = async () => ({ ok: true });',
            'triggers' => ['schedule' => '*/5 * * * *'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);

        $I->sendGET('/api/v4/functions/' . $name);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['triggers' => ['schedule' => '*/5 * * * *']]);
    }

    public function shouldRejectInvalidEventTrigger(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/functions', json_encode([
            'name' => 'bad_event_fn',
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'code' => 'noop',
            'triggers' => ['event' => ['table' => 'no_schema_qualifier']],
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INVALID_EVENT_TRIGGER']);
    }

    public function shouldRejectInvalidPackage(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/functions', json_encode([
            'name' => 'bad_pkg_fn', 'runtime' => 'nodejs20', 'handler' => 'index.handler',
            'code' => 'x', 'package' => 'tarball',
        ]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->seeResponseContainsJson(['errorCode' => 'INPUT_VALIDATION_ERROR']);
    }

    public function shouldCreateZipPackagedFunction(ApiTester $I)
    {
        $this->auth($I);
        $name = 'zip_fn_' . $this->date->getTimestamp();
        $I->sendPOST('/api/v4/functions', json_encode([
            'name' => $name, 'runtime' => 'nodejs20', 'handler' => 'index.handler',
            'package' => 'zip', 'code' => base64_encode('dummy-zip-bytes'),
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendGET('/api/v4/functions/' . $name);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['package' => 'zip']);
    }

    public function shouldRejectPostWithResourceIdentifier(ApiTester $I)
    {
        $this->auth($I);
        $payload = json_encode([
            'name' => $this->functionName,
            'runtime' => 'nodejs20',
            'handler' => 'index.handler',
            'code' => 'noop',
        ]);
        $I->sendPOST('/api/v4/functions/' . $this->functionName, $payload);
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
        $I->seeResponseContainsJson(['errorCode' => 'POST_WITH_RESOURCE_IDENTIFIER']);
    }

    public function shouldRejectPatchOnCollection(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPATCH('/api/v4/functions', json_encode(['handler' => 'index.other']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldUpdateFunctionAndBumpVersion(ApiTester $I)
    {
        $this->auth($I);
        $I->stopFollowingRedirects();
        $I->sendPATCH('/api/v4/functions/' . $this->functionName, json_encode([
            'code' => 'export const handler = async (event) => ({ ok: false, v: 2 });',
        ]));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->startFollowingRedirects();

        $I->sendGET('/api/v4/functions/' . $this->functionName);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['version' => 2]);
        $I->seeResponseContains('v: 2');
    }

    public function shouldReturn501OnInvokeWithStubRunner(ApiTester $I)
    {
        $this->auth($I);
        $I->sendPOST('/api/v4/functions/' . $this->functionName . '/invocations', json_encode(['foo' => 'bar']));
        $I->seeResponseCodeIs(HttpCode::NOT_IMPLEMENTED);
        $I->seeResponseContainsJson(['errorCode' => 'FUNCTION_RUNTIME_UNAVAILABLE']);
    }

    public function shouldRecordFailedInvocation(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/functions/' . $this->functionName . '/invocations');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseIsJson();
        $invocations = json_decode($I->grabResponse(), true);
        $I->assertNotEmpty($invocations, 'Expected at least one invocation record');
        $I->assertEquals('failed', $invocations[0]['status']);
        $invocationId = $invocations[0]['invocation'];

        $I->sendGET('/api/v4/functions/' . $this->functionName . '/invocations/' . $invocationId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'invocation' => $invocationId,
            'function' => $this->functionName,
            'status' => 'failed',
        ]);
        $I->seeResponseContains('runtime is not configured');
    }

    public function shouldQueueAsyncInvocation(ApiTester $I)
    {
        $this->auth($I);
        // Async path returns 202 immediately without invoking the runner.
        $I->sendPOST('/api/v4/functions/' . $this->functionName . '/invocations?async=true', json_encode(['foo' => 'bar']));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'pending']);
        $response = json_decode($I->grabResponse(), true);
        $I->assertNotEmpty($response['invocation'] ?? null);
        $this->asyncInvocationId = $response['invocation'];
    }

    public function shouldShowQueuedInvocationAsPending(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/functions/' . $this->functionName . '/invocations/' . $this->asyncInvocationId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'invocation' => $this->asyncInvocationId,
            'function' => $this->functionName,
            'status' => 'pending',
        ]);
    }

    public function shouldReturn404ForUnknownInvocation(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/functions/' . $this->functionName . '/invocations/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldReturn404ForUnknownFunction(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/functions/this_function_does_not_exist');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_FUNCTION_ERROR']);
    }

    public function shouldDeleteFunction(ApiTester $I)
    {
        $this->auth($I);
        $I->sendDELETE('/api/v4/functions/' . $this->functionName);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    public function shouldReturn404AfterDelete(ApiTester $I)
    {
        $this->auth($I);
        $I->sendGET('/api/v4/functions/' . $this->functionName);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    /**
     * Set the JSON + bearer auth headers used by every authenticated request.
     */
    private function auth(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->userAccessToken);
    }
}
