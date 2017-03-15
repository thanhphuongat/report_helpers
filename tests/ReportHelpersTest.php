<?php

namespace go1\report_helpers\tests;

use Aws\S3\S3Client;
use Elasticsearch\Client as ElasticsearchClient;
use go1\report_helpers\Export;
use go1\report_helpers\ReportHelpersServiceProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

class ReportHelpersTest extends TestCase
{
    public function testContainerValidation()
    {
        $c = new Container;
        $c->register(new ReportHelpersServiceProvider, [
            's3'             => [
                'region'   => 'abc',
                'key'      => '123',
                'access'   => 'a1!',
            ],
            'elasticsearch'  => [
                'region'   => 'abc',
                'key'      => '123',
                'access'   => 'a1!',
                'endpoint' => 'test.aws.local'
            ],
        ]);

        $this->assertTrue($c['go1.report_helpers.export'] instanceof Export);
        $this->assertTrue($c['go1.report_helpers.s3'] instanceof S3Client);
        $this->assertTrue($c['go1.report_helpers.elasticsearch'] instanceof ElasticsearchClient);
    }
}
