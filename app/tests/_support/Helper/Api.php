<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Api extends \Codeception\Module
{
    public function capturePHPSESSID()
    {
        $cookie = $this->getClient()->getCookieJar()->get('PHPSESSID');
        return $cookie->getValue();
    }

    /**
     * @return \Symfony\Component\HttpKernel\Client|\Symfony\Component\BrowserKit\Client $client
     */
    protected function getClient()
    {
        return $this->getModule('REST')->client;
    }
}
