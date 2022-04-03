<?php
/**
 * @desc 阿里云OSS适配器
 *
 * @author Tinywan(ShaoBo Wan)
 * @date 2022/3/7 19:54
 */
declare(strict_types=1);

namespace Tinywan\Storage\Adapter;

use OSS\Core\OssException;
use OSS\OssClient;
use Throwable;
use Tinywan\Storage\Exception\StorageException;

class OssAdapter extends AdapterAbstract
{
    protected static $instance = null;

    /**
     * @desc: 阿里雲实例
     *
     * @throws OssException
     */
    public static function getInstance(): ?OssClient
    {
        if (is_null(self::$instance)) {
            $config = config('plugin.tinywan.storage.app.storage.oss');
            static::$instance = new OssClient(
                $config['accessKeyId'],
                $config['accessKeySecret'],
                $config['endpoint']
            );
        }

        return static::$instance;
    }

    /**
     * @desc: 方法描述
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadFile(array $options = []): array
    {
        try {
            $config = config('plugin.tinywan.storage.app.storage.oss');
            $result = [];
            foreach ($this->files as $key => $file) {
                $uniqueId = hash_file('sha256', $file->getPathname()).date('YmdHis');
                $saveName = $uniqueId.'.'.$file->getUploadExtension();
                $object = $config['dirname'].$this->dirSeparator.$saveName;
                $temp = [
                    'key' => $key,
                    'origin_name' => $file->getUploadName(),
                    'save_name' => $saveName,
                    'save_path' => $object,
                    'url' => $config['domain'].$this->dirSeparator.$object,
                    'unique_id' => $uniqueId,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getUploadMineType(),
                    'extension' => $file->getUploadExtension(),
                ];
                $upload = self::getInstance()->uploadFile($config['bucket'], $object, $file->getPathname());
                if (!isset($upload['info']) && 200 != $upload['info']['http_code']) {
                    throw new StorageException((string) $upload);
                }
                array_push($result, $temp);
            }
        } catch (Throwable|OssException $exception) {
            throw new StorageException($exception->getMessage());
        }

        return $result;
    }

    /**
     * @desc: 上传Base64
     *
     * @return array|bool
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadBase64(array $options)
    {
        if (!isset($options['base64'])) {
            return $this->setError(false, 'base64参数不能为空');
        }
        if (!isset($options['extension'])) {
            return $this->setError(false, 'extension参数不能为空');
        }
        $base64 = explode(',', $options['base64']);
        $config = config('plugin.tinywan.storage.app.storage.oss');
        $bucket = $config['bucket'];
        $uniqueId = date('YmdHis').uniqid();
        $object = $config['dirname'].$this->dirSeparator.$uniqueId.'.'.$options['extension'];

        try {
            $result = self::getInstance()->putObject($bucket, $object, base64_decode($base64[1]));
            if (!isset($result['info']) && 200 != $result['info']['http_code']) {
                return $this->setError(false, (string) $result);
            }
        } catch (OssException $e) {
            return $this->setError(false, $e->getMessage());
        }
        $imgLen = strlen($base64['1']);
        $fileSize = $imgLen - ($imgLen / 8) * 2;

        return [
            'save_path' => $object,
            'url' => $config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $fileSize,
            'extension' => $options['extension'],
        ];
    }

    /**
     * @desc: 上传服务端文件
     *
     * @throws OssException
     *
     * @author Tinywan(ShaoBo Wan)
     */
    public function uploadServerFile(string $file_path): array
    {
        $file = new \SplFileInfo($file_path);
        if (!$file->isFile()) {
            throw new StorageException('不是一个有效的文件');
        }
        $config = config('plugin.tinywan.storage.app.storage.oss');
        $uniqueId = hash_file('sha256', $file->getPathname()).date('YmdHis');
        $object = $config['dirname'].$this->dirSeparator.$uniqueId.'.'.$file->getExtension();

        $result = [
            'origin_name' => $file->getRealPath(),
            'save_path' => $object,
            'url' => $config['domain'].$this->dirSeparator.$object,
            'unique_id' => $uniqueId,
            'size' => $file->getSize(),
            'extension' => $file->getExtension(),
        ];
        $upload = self::getInstance()->uploadFile($config['bucket'], $object, $file->getRealPath());
        if (!isset($upload['info']) && 200 != $upload['info']['http_code']) {
            throw new StorageException((string) $upload);
        }

        return $result;
    }
}
