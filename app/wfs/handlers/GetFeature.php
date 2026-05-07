<?php
namespace app\wfs\handlers;

use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;

final class GetFeature implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    public function handle(Request $req, GmlWriter $writer): void
    {
        $writer->write("<!-- GetFeature not yet implemented -->\n");
    }
}
