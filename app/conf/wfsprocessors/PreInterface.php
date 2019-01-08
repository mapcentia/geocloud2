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
    public function processUpdate($arr, $typeName) : array;

    /**
     * The main function called by the WFS prior to the single INSERT transaction.
     * @param $arr
     * @return array
     */
    public function processInsert($arr, $typeName) : array;

    /**
     * The main function called by the WFS prior to the single DELETE transaction.
     * @param $arr
     * @return array
     */
    public function processDelete($arr, $typeName) : array;
}