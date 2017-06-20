<?php

namespace go1\report_helpers;

use Aws\S3\S3Client;
use Elasticsearch\Client as ElasticsearchClient;

class Export
{
    /** @var S3Client */
    protected $s3Client;
    /** @var ElasticsearchClient */
    protected $elasticsearchClient;

    public function __construct(S3Client $s3Client, ElasticsearchClient $elasticsearchClient)
    {
        $this->s3Client = $s3Client;
        $this->elasticsearchClient = $elasticsearchClient;
    }

    public function doExport($region, $bucket, $key, $fields, $headers, $params, $selectedIds, $excludedIds, $scrollId = null)
    {
        if (!is_dir(dirname("/tmp/{$key}"))) {
            mkdir(dirname("/tmp/{$key}"), 0777, true);
        }
        $temp = fopen("/tmp/{$key}", 'a+');

        if ($selectedIds !== ['All']) {
            // Improve performance by not loading all records then filter out.
            $params['body']['query']['filtered']['filter']['and'][] = [
                'terms' => [
                    'id' => $selectedIds
                ]
            ];
        }

        $params += [
            'search_type' => 'scan',
            'scroll' => '30s',
            'size' => 1000,
        ];

        if (!$scrollId) {
            // Write header.
            fputcsv($temp, $headers);
            fclose($temp);

            $docs = $this->elasticsearchClient->search($params);

            return [
                'scrollId' => $docs['_scroll_id'],
                'key' => $key,
            ];
        }
        else {
            $docs = $this->elasticsearchClient->scroll([
                'scroll_id' => $scrollId,
                'scroll' => '30s',
            ]);
        }

        if (isset($docs['hits']['hits']) && count($docs['hits']['hits']) > 0) {
            foreach ($docs['hits']['hits'] as $hit) {
                if (empty($excludedIds) || in_array($excludedIds, $hit['id'])) {
                    $csv = $this->getValues($fields, $hit);
                    // Write row.
                    fputcsv($temp, $csv);
                }
            }
            fclose($temp);

            return [
                'scrollId' => $docs['_scroll_id'],
                'key' => $key,
            ];
        }
        else {
            rewind($temp);
            $this->s3Client->registerStreamWrapper();
            $context = stream_context_create(array(
                's3' => array(
                    'ACL' => 'public-read'
                )
            ));
            file_put_contents("s3://{$bucket}/{$key}", $temp, 0, $context);
            unlink("/tmp/{$key}");
            return [
                'file' => "https://s3-{$region}.amazonaws.com/{$bucket}/{$key}"
            ];
        }
    }

    protected function getValues($fields, $hit)
    {
        $values = [];
        foreach ($fields as $field) {
            $value = array_get($hit['_source'], $field);
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            $values[] = $value;
        }
        return $values;
    }
}
