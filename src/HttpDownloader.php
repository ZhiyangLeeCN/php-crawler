<?php namespace Zhiyang\Crawler;

/**
 * @author zhiyang <zhiyanglee@foxmail.com>
 * @date 2016/12/14
 */

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Response;

class HttpDownloader
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var bool 是否启动缓存
     */
    private $enableCache = true;

    /**
     * @var string 缓存文件保存路径
     */
    private $cacheFileSavePath;

    /**
     * RequestDownloader constructor.
     * @param bool $enableCache
     */
    public function __construct($enableCache = true)
    {
        $this->httpClient = new HttpClient();
        $this->enableCache = $enableCache;
        $this->cacheFileSavePath = __DIR__ . '/html_cache';

        //创建缓存文件路径
        if (!file_exists($this->cacheFileSavePath)) {

            mkdir($this->cacheFileSavePath);

        }
    }

    /**
     *  以带锁的形式完整读入一个缓存文件
     *
     * @param  string $cacheFilePath 缓存文件路径
     * @return bool|string
     */
    protected function cacheFileGetContentWithLock($cacheFilePath)
    {
        if (file_exists($cacheFilePath)) {

            $fd = fopen($cacheFilePath, "r");

            //如果打开失败
            if ($fd === false) {

                return false;

            }

            $content = false;

            //如果获取共享读锁失败
            if (flock($fd, LOCK_SH)) {

                $content = fread($fd, filesize($cacheFilePath));

                flock($fd, LOCK_UN);

            }

            fclose($fd);

            //只有读取成功才返回
            if ($content !== false) {

                return $content;

            }

        }

        return false;

    }

    /**
     *  以带锁形式写入一个内容
     *
     * @param  string $cacheFilePath 缓存文件路径
     * @param  string $content 要写入的缓存内容
     * @return bool
     */
    protected function cacheFilePutContentWithLock($cacheFilePath, $content)
    {
        $cacheDirPath = dirname($cacheFilePath);

        //如果目录不存在则创建目录
        if (!file_exists($cacheDirPath)) {

            if (!mkdir($cacheDirPath, 0777, true)) {

                return false;

            }

        }

        $fd = fopen($cacheFilePath, "w");
        //如果文件打开失败
        if ($fd === false) {

            return false;

        }

        $result = false;

        //获取一个排他写锁，如果成功则开始写入内容，失败则代表别的进程正在进行这个操作
        if (flock($fd, LOCK_EX)) {

            $result = (fwrite($fd, $content) !== false);

            flock($fd, LOCK_UN);

        }

        fclose($fd);

        return $result;

    }

    /**
     *  发送一个请求但不经过缓存
     *
     * @param $method
     * @param $uri
     * @param array $options
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function sendWithoutCache($method, $uri, $options = [])
    {
        return $this->httpClient->request($method, $uri, $options);
    }

    /**
     *  发送一个请求并检查是否存在缓存
     *
     * @param  string $method
     * @param  string $uri
     * @param  array $options
     * @return mixed|\Psr\Http\Message\ResponseInterface|string
     */
    public function send($method, $uri, $options = [])
    {
        $uriInfo = parse_url($uri);

        if (!isset($uriInfo['host']) || empty($uriInfo['host'])) {

            return null;

        }

        $uriHasCode = md5($uri);

        $cacheDirPath = $this->cacheFileSavePath . '/' . $uriInfo['host'];
        $cacheFilePath = $cacheDirPath . '/' . $uriHasCode;
        $cacheHeadFilePath = $cacheDirPath . '/headers/' . $uriHasCode;

        //如果缓存可用
        if ($this->enableCache) {

            $cacheContent = $this->cacheFileGetContentWithLock($cacheFilePath);
            $cacheContentHeader = $this->cacheFileGetContentWithLock($cacheHeadFilePath);

            if ($cacheContent !== false) {

                if ($cacheContentHeader === false) {

                    $cacheContentHeader = [];

                } else {

                    $cacheContentHeader = unserialize($cacheContentHeader);

                }

                return new Response(200, $cacheContentHeader, $cacheContent);

            }

        }

        $response = $this->sendWithoutCache($method, $uri, $options);

        //写入缓存
        if ($response->getStatusCode() == 200 && $this->enableCache) {

            $this->cacheFilePutContentWithLock($cacheFilePath, $response->getBody());
            $this->cacheFilePutContentWithLock($cacheHeadFilePath, serialize($response->getHeaders()));

        }

        return $response;

    }

}