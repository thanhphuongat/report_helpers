<?php

namespace go1\reportHelpers;

use Aws\S3\S3Client;
use Elasticsearch\Client as ElasticsearchClient;

class Export
{
    public function uploadCsv(S3Client $s3Client, ElasticsearchClient $elasticsearchClient, $bucket, $key, $fields, $params, $selectedIds, $excludedIds, $flushEvery = 500)
    {
        $this->hideFields($fields);
        $this->sortFields($fields);

        $s3Client->registerStreamWrapper();
        $context = stream_context_create(array(
            's3' => array(
                'ACL' => 'public-read'
            )
        ));
        // Opening a file in 'w' mode truncates the file automatically.
        $stream = fopen("s3://{$bucket}/{$key}", 'w', 0, $context);

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
            'scroll' => '1s',
            'size' => 50,
        ];

        $docs = $elasticsearchClient->search($params);
        $scrollId = $docs['_scroll_id'];
        $total = 0;

        while (\true) {
            $response = $elasticsearchClient->scroll([
                    'scroll_id' => $scrollId,
                    'scroll' => '1s',
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
                $total += count($response['hits']['hits']);

                if ($total % $flushEvery === 0) {
                    // Flush the output.
                    fflush($stream);
                }
            } else {
                break;
            }
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
