<?php

namespace App\Services\ConnectionTesters;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Exception;

class S3ConnectionTester
{
    /**
     * Test S3 connectivity by uploading, reading, and deleting a test file.
     *
     * @param  array{key: string, secret: string, region: string, bucket: string, endpoint: string|null, use_path_style: bool}  $config
     * @return array{success: bool, error: string|null}
     */
    public function test(array $config): array
    {
        try {
            $s3 = $this->createClient($config);

            $testKey = '.signals-test-'.bin2hex(random_bytes(8));
            $testContent = 'signals-install-test-'.time();

            $s3->putObject([
                'Bucket' => $config['bucket'],
                'Key' => $testKey,
                'Body' => $testContent,
            ]);

            $result = $s3->getObject([
                'Bucket' => $config['bucket'],
                'Key' => $testKey,
            ]);

            $body = (string) $result['Body'];

            if ($body !== $testContent) {
                return [
                    'success' => false,
                    'error' => 'Upload succeeded but read-back content did not match.',
                ];
            }

            $s3->deleteObject([
                'Bucket' => $config['bucket'],
                'Key' => $testKey,
            ]);

            return ['success' => true, 'error' => null];
        } catch (AwsException $e) {
            return ['success' => false, 'error' => $e->getAwsErrorMessage() ?: $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array{key: string, secret: string, region: string, bucket: string, endpoint: string|null, use_path_style: bool}  $config
     */
    protected function createClient(array $config): S3Client
    {
        $clientConfig = [
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ];

        if (! empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        if ($config['use_path_style']) {
            $clientConfig['use_path_style_endpoint'] = true;
        }

        return new S3Client($clientConfig);
    }
}
