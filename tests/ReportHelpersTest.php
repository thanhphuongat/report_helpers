<?php

namespace go1\reportHelpers\tests;

use go1\reportHelpers\Export;
use go1\reportHelpers\ReportHelpersServiceProvider;
use PHPUnit_Framework_TestCase;
use Pimple\Container;

class ReportHelpersTest extends PHPUnit_Framework_TestCase
{
    public function testContainerValidation()
    {
        $c = new Container;
        $c->register(new ReportHelpersServiceProvider);

        $this->assertTrue($c['go1.report_helpers.export'] instanceof Export);
    }
}
