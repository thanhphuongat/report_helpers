<?php

namespace go1\report_helpers;

use Aws\Credentials\CredentialProvider;
use Aws\Credentials\Credentials;
use Aws\ElasticsearchService\ElasticsearchPhpHandler;
use Aws\S3\S3Client;
use Elasticsearch\ClientBuilder;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ReportHelpersServiceProvider implements ServiceProviderInterface
{
    public function register(Container $c)
    {
        $c['report_export'] = function (Container $c) {
            return new Export($c['go1.client.s3'], $c['go1.client.es']);
        };
        $c['go1.client.s3'] = function (Container $c) {
            $o = $c['s3Options'];
            return new S3Client([
                'region'      => $o['region'],
                'version'     => '2006-03-01',
                'credentials' => new Credentials($o['key'], $o['secret']),
            ]);
        };
        $c['go1.client.es'] = function (Container $c) {
            $client = ClientBuilder::create();

            if (($o = $c['esOptions']) && $o['credential']) {
                $provider = CredentialProvider::fromCredentials(new Credentials($o['key'], $o['secret']));
                $client->setHandler(new ElasticsearchPhpHandler($o['region'], $provider));
            }

            return $client
                ->setHosts([parse_url($o['endpoint'])])
                ->build();
        };

    }
}
