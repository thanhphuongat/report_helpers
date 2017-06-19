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
        $this->s3Client->registerStreamWrapper();
        $context = stream_context_create(array(
            's3' => array(
                'ACL' => 'public-read'
            )
        ));
        // Opening a file in 'w' mode truncates the file automatically.
        $stream = fopen("s3://{$bucket}/{$key}", 'w', 0, $context);

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
            'size' => 50,
        ];

        if (!$scrollId) {
            // Write header.
            fputcsv($stream, $headers);

            $docs = $this->elasticsearchClient->search($params);
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
                    fputcsv($stream, $csv);
                }
            }

            fclose($stream);

            return [
                'scrollId' => $docs['_scroll_id']
            ];
        }
        else {
            return [
                'file' => "https://s3-{$region}.amazonaws.com/{$bucket}/{$key}"
            ];
        }
    }

    protected function getValues($fields, $hit)
    {
        $values = [];
        foreach ($fields as $key => $field) {
            $value = array_get($hit['_source'], $key);
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            $values[] = $value;
        }
        return $values;
    }
}
