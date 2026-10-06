<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

use Aws\PsrCacheAdapter;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;
use Cache\Adapter\Apcu\ApcuCachePool;
use GuzzleHttp\Psr7\MimeType;

class AwsS3_Storage_Adapter_AwsS3 implements Omeka_Storage_Adapter_AdapterInterface
{
    /**
     * @var Aws\S3\S3Client
     */
    private $client;

    /**
     * @var string
     */
    private $bucket;

    /**
     * @var finfo
     */
    private $finfo;

    public function __construct(array $options = [])
    {
        $clientParams = $options['clientParams'] ?? [];
        if (!isset($clientParams['credentials']) && extension_loaded('apcu')) {
            $clientParams['credentials'] = new PsrCacheAdapter(new ApcuCachePool);
        }
        $this->client = new S3Client($clientParams);
        $this->bucket = $options['bucket'] ?? null;
    }

    public function setUp()
    {
        // no-op
    }

    public function canStore()
    {
        return isset($this->bucket);
    }

    public function store($source, $dest)
    {
        $mediaType = MimeType::fromFilename($dest);
        if (!$mediaType) {
            $mediaType = $this->getMimeType($source);
        }
        $stream = fopen($source, 'rb');

        $success = false;
        try {
            $result = $this->client->upload($this->bucket, $dest, $stream, null, [
                'params' => [
                    'ContentType' => $mediaType,
                ],
            ]);
            if ($result['@metadata']['statusCode'] == '200') {
                $success = true;
            }
        } finally {
            fclose($stream);
        }

        if (!$success) {
            throw new Omeka_Storage_Exception('Unable to store file.');
        }

        unlink($source);
    }

    public function move($source, $dest)
    {
        throw new Omeka_Storage_Exception('Move not supported.');
    }

    public function delete($path)
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);
    }

    public function getUri($path)
    {
        return $this->client->getObjectUrl($this->bucket, $path);
    }

    private function getMimeType($source)
    {
        if (!$this->finfo) {
            $this->finfo = new finfo(FILEINFO_MIME_TYPE);
        }
        return $this->finfo->file($source);
    }
}
