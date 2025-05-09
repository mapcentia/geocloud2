<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 *
 */


use app\inc\Route2;
use app\extensions\demo\api\DemoRest;

/*
GET my_api/demo

POST /my_api/demo
Content-Type: application/json
Accept: application/json; charset=utf-8


{
  "foo": "hi",
  "bar": "a"
}
*/
Route2::add("my_api/demo", new DemoRest());

// GET /my_api/demo/my_resource/name/martin/message/hello
// () = action, / = separator, {} = required, [] = optional
Route2::add("my_api/demo/(action)/name/{name}/message/[message]", new DemoRest());
