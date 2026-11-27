<?php

return [
    'project_id' => env('BIGQUERY_PROJECT_ID'),

    'dataset' => env('BIGQUERY_DATASET'),

    'key_file_path' => env(
        'GOOGLE_CLOUD_KEY_FILE',
        storage_path('app/credentials/bigquery-key.json')
    ),
];
