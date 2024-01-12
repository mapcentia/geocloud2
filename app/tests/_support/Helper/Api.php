<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Module;
use Symfony\Component\BrowserKit\Client;

class Api extends Module
{
    public function capturePHPSESSID()
    {
        $cookie = $this->getClient()->getCookieJar()->get('PHPSESSID');
        return $cookie->getValue();
    }

    /**
     * @return \Symfony\Component\HttpKernel\Client|Client $client
     */
    protected function getClient()
    {
        return $this->getModule('REST')->client;
    }
}
