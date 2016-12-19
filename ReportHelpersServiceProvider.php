<?php

namespace go1\reportHelpers;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ReportHelpersServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        $c['go1.report_helpers.export'] = function () {
            return new Export();
        };
    }
}
