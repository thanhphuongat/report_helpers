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
            's3Options'  => [
                'region'   => 'abc',
                'key'      => '123',
                'secret'   => 'a1!',
            ],
            'esOptions'  => [
                'credential' => true,
                'region'     => 'abc',
                'key'        => '123',
                'secret'     => 'a1!',
                'endpoint'   => 'http://test.aws.local'
            ],
        ]);

        $this->assertTrue($c['report_export'] instanceof Export);
        $this->assertTrue($c['go1.client.s3'] instanceof S3Client);
        $this->assertTrue($c['go1.client.es'] instanceof ElasticsearchClient);
    }
}
