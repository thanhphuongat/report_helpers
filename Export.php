<?php

namespace go1\reportHelpers;

use Aws\S3\S3Client;
use Elasticsearch\Client as ElasticsearchClient;

class Export
{
    public function uploadCsv(S3Client $s3Client, ElasticsearchClient $elasticsearchClient, $region, $bucket, $key, $fields, $params, $selectedIds, $excludedIds)
    {
        $this->hideFields($fields);
        $this->sortFields($fields);

        $s3Client->registerStreamWrapper();
        $context = stream_context_create(array(
            's3' => array(
                'ACL' => 'public-read'
            )
        ));
        // Opening a file in w mode truncates the file automatically, so we need
        // to use a mode.
        $stream = fopen("s3://{$bucket}/{$key}", 'a', 0, $context);

        // Acquire an exclusive lock.
        if (flock($stream, LOCK_EX)) {
            // Truncate file.
            ftruncate($fp, 0);

            // Write header.
            fputcsv($stream, $this->getHeaders($fields));

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

            $docs = $elasticsearchClient->search($params);
            $scrollId = $docs['_scroll_id'];

            while (\true) {
                $response = $elasticsearchClient->scroll([
                        'scroll_id' => $scrollId,
                        'scroll' => '30s',
                    ]
                );

                if (count($response['hits']['hits']) > 0) {
                    foreach ($response['hits']['hits'] as $hit) {
                        if (empty($excludedIds) || in_array($excludedIds, $hit['id'])) {
                            $csv = $this->getValues($fields, $hit);
                            // Write row.
                            fputcsv($stream, $csv);
                        }
                    }

                    $scrollId = $response['_scroll_id'];
                } else {
                    break;
                }
            }

            // Flush output before releasing the lock, Not sure it is needed.
            fflush($stream);

            // Release the lock.
            flock($stream, LOCK_UN);
        }
        else {
            // We are uploading the file.
        }

        fclose($stream);
    }

    public function getFile($region, $bucket, $key)
    {
        return "https://s3-{$region}.amazonaws.com/$bucket/{$key}";
    }

    protected function hideFields(&$fields)
    {
        foreach ($fields as $key => $field) {
            if (!$field['options']['datatable']['visible']) {
                unset($fields[$key]);
            }
        }
    }

    protected function sortFields(&$fields)
    {
        uasort($fields, function ($a, $b) {
            if ($a['options']['datatable']['order'] == $b['options']['datatable']['order']) {
                return 0;
            }
            return ($a['options']['datatable']['order'] < $b['options']['datatable']['order']) ? -1 : 1;
        });
    }

    protected function getHeaders($fields)
    {
        $header = [];
        foreach ($fields as $field) {
            $header[] = $field['title'];
        }
        return $header;
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
