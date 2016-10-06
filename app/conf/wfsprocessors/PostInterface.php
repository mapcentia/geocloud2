<?php

namespace app\conf\wfsprocessors;

/**
 * Interface PreInterface
 * @package app\conf\wfsprocessors
 */
interface PostInterface
{
    /**
     * The main function called by the WFS after all INSERT, UPDATE and DELETE statements are run, but before the final COMMIT.
     * @return array
     */
    function process();

}