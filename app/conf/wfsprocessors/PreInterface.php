<?php

namespace app\conf\wfsprocessors;

/**
 * Interface PreInterface
 * @package app\conf\wfsprocessors
 */
interface PreInterface
{
    /**
     * The main function called by the WFS prior to the single UPDATE transaction.
     * @param $arr
     * @return array
     */
    function processUpdate($arr, $typeName);

    /**
     * The main function called by the WFS prior to the single INSERT transaction.
     * @param $arr
     * @return array
     */
    function processInsert($arr, $typeName);

    /**
     * The main function called by the WFS prior to the single DELETE transaction.
     * @param $arr
     * @return array
     */
    function processDelete($arr, $typeName);
}