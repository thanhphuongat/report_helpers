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
        $c['go1.report_helpers.export'] = function (Container $c) {
            return new Export($c['go1.report_helpers.s3'], $c['go1.report_helpers.elasticsearch']);
        };
        $c['go1.report_helpers.s3'] = function (Container $c) {
            $options = $c['s3'];
            return new S3Client([
                'region'      => $options['region'],
                'version'     => '2006-03-01',
                'credentials' => new Credentials($options['key'], $options['access']),
            ]);
        };
        $c['go1.report_helpers.elasticsearch'] = function (Container $c) {
            $options = $c['elasticsearch'];
            $clientBuilder = ClientBuilder::create();

            if (isset($options['key']) && isset($options['access']) && isset($options['region'])) {
                $provider = CredentialProvider::fromCredentials(
                    new Credentials($options['key'], $options['access'])
                );

                $handler = new ElasticsearchPhpHandler($options['region'], $provider);

                $clientBuilder->setHandler($handler);
            }

            $clientBuilder->setHosts([$options['endpoint']]);
            return $clientBuilder->build();
        };

    }
}
