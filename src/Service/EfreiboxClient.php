<?php

namespace App\Service;

use Aws\S3\S3Client;

class EfreiboxClient {

    public static function createS3Client(): S3Client{
        $options = [
            'region' => $_ENV['AWS_REGION'],
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS'],
                'secret' => $_ENV['AWS_SECRET'],
            ],
            'version' => 'latest',
            'http' => [
                'verify' => !($_ENV['APP_ENV'] === 'dev')
            ]
        ];

        return new S3Client($options);
    }
}