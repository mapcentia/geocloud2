<?php

use Codeception\Util\HttpCode;

/**
 * Sign-in, sign-up and sign-out must not be usable to send a signed-in user on to a site the
 * server does not know. The check runs before the provider configuration is
 * looked at, so this holds whether or not a provider is configured.
 */
class SocialLoginRedirectApiCest
{
    public function shouldRefuseUnknownRedirectUri(ApiTester $I)
    {
        foreach (['github', 'google', 'gitlab'] as $provider) {
            $I->stopFollowingRedirects();
            $I->sendGET("/$provider/start", ['action' => 'signup', 'redirect_uri' => 'https://unknown-site.test/']);
            $I->seeResponseCodeIs(HttpCode::OK);
            $I->seeResponseContains('redirect_uri is not allowed');
        }
    }

    // The sign-up page refuses to carry an unknown address through the flow.
    public function shouldRefuseUnknownRedirectUriOnSignupPage(ApiTester $I)
    {
        $I->sendGET('/signup', ['redirect_uri' => 'https://unknown-site.test/']);
        $I->seeResponseContains('redirect_uri is not allowed');
        $I->dontSeeResponseContains("hx-post='/signup'");
    }

    // ... and so does the form post, which is what finally redirects.
    public function shouldRefuseUnknownRedirectUriOnSignupPost(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPOST('/signup', ['redirect_uri' => 'https://unknown-site.test/', 'action' => 'signup']);
        $I->seeResponseContains('redirect_uri is not allowed');
        $I->dontSeeResponseContains('<form');
    }

    public function shouldStillShowSignupForLocalRedirectUri(ApiTester $I)
    {
        $I->sendGET('/signup', ['redirect_uri' => '/app/']);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContains("hx-post='/signup'");
    }

    // Sign-out still signs out, but lands on the server's own root instead.
    public function shouldNotRedirectSignoutToUnknownSite(ApiTester $I)
    {
        $I->stopFollowingRedirects();
        $I->sendGET('/signout', ['redirect_uri' => 'https://unknown-site.test/']);
        $I->seeResponseCodeIs(HttpCode::FOUND);
        $I->seeHttpHeader('Location', '/');
    }

    public function shouldRedirectSignoutToLocalPath(ApiTester $I)
    {
        $I->stopFollowingRedirects();
        $I->sendGET('/signout', ['redirect_uri' => '/app/']);
        $I->seeResponseCodeIs(HttpCode::FOUND);
        $I->seeHttpHeader('Location', '/app/');
    }
}
