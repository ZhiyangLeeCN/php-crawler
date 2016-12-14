<?php namespace Zhiyang\Crawler;

/**
 * @author zhiyang <zhiyanglee@foxmail.com>
 * @date 2016/12/12
 */
class Task
{

    /**
     * @var string 待采集的URL
     */
    private $url;

    /**
     * @var array 附加属性
     */
    private $property = [];

    /**
     * @var bool 是否是目标类型
     */
    private $isTarget = false;

    /**
     * Request constructor.
     *
     * @param string $url
     * @internal param bool $enableCache
     */
    public function __construct($url)
    {
        $this->url = static::urlNormalize($url);
    }

    /**
     *  URL正规化(从Nutch里扒来的)
     *
     * @param  string $urlString
     * @return string
     */
    public static function urlNormalize($urlString)
    {
        $urlString = trim($urlString);

        $url = parse_url($urlString);

        $protocol = isset($url['scheme']) ? $url['scheme'] : '';
        $host = isset($url['host']) ? $url['host'] : '';
        $port = isset($url['port']) ? $url['port'] : '';
        $path = isset($url['path']) ? $url['path'] : '';
        $query = isset($url['query']) ? $url['query'] : '';
        $fragment = isset($url['fragment']) ? $url['fragment'] : '';

        $file = $path;
        if (!empty($query)) {

            $file .= '?' . $query;

        }

        if (!empty($fragment)) {

            $file .= '#' . $fragment;

        }

        if (preg_match("/\\.?\\.?/", $file)) {

            $oldLen =  mb_strlen($file);
            $newLen = $oldLen - 1;

            // All substitutions will be done step by step, to ensure that certain
            // constellations will be normalized, too
            //
            // URL正规化会一步一步进行，下面给出一个例子
            //
            // For example: "/aa/bb/../../cc/../foo.html will be normalized in the
            // following manner:
            //   "/aa/bb/../../cc/../foo.html"
            //   "/aa/../cc/../foo.html"
            //   "/cc/../foo.html"
            //   "/foo.html"
            //
            // The normalization also takes care of leading "/../", which will be
            // replaced by "/", because this is a rather a sign of bad webserver
            // configuration than of a wanted link.  For example, urls like
            // "http://www.foo.com/../" should return a http 404 error instead of
            // redirecting to "http://www.foo.com".
            //
            while ($oldLen != $newLen) {

                $oldLen = mb_strlen($file);

                // substitue first occurence of "/xx/../" by "/"
                // remove leading "/../"
                // remove unnecessary "/./"
                // collapse adjacent slashes with "/"
                $file = preg_replace([
                    "(/[^/]*[^/.]{1}[^/]*/\\.\\./)",
                    "/^(\\/\\.\\.\\/)+/",
                    "(/\\./)",
                    "/\\/{2,}/"
                ], "/", $file);

                $newLen = mb_strlen($file);

            }

        }

        $newHost = '';
        if (!empty($port)) {

            $newHost .= $host . ':' . $port;

        } else {

            $newHost = $host;

        }

        return $protocol . '://' . $newHost . $file;

    }

    /**
     *  获取待采集的URL
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     *  获取URL的host名称
     *
     * @return mixed
     */
    public function getUrlHost()
    {
        $host = 'http://' . parse_url($this->url, PHP_URL_HOST);

        $port = parse_url($this->url, PHP_URL_PORT);

        if (!is_null($port)) {

            $host .= ':' . $port;

        }

        return $host;
    }

    /**
     *  设置附加属性
     *
     * @param string $key
     * @param string|null $value
     * @return string|null
     */
    public function property($key, $value = null)
    {
        if (is_null($value)) {

            if (is_array($key)) {

                $this->property = $key;

                return $key;

            } else {

                if (isset($this->property[$key])) {

                    return $this->property[$key];

                } else {

                    return null;

                }

            }

        } else {

            $this->property[$key] = $value;

            return $value;

        }
    }

    /**
     *  是否是目标类型
     *
     * @return bool
     */
    public function isTarget()
    {
        return $this->isTarget;
    }

    /**
     * 标记为目标类型
     */
    public function markAsTarget()
    {
        $this->isTarget = true;
    }

    /**
     * 取消标记为目标类型
     */
    public function unMarkAsTarget()
    {
        $this->isTarget = false;
    }

    /**
     *  将对象编码成json
     *
     * @return string
     */
    public function encode()
    {
        return json_encode([
            'url'           =>  $this->url,
            'property'      =>  $this->property,
            'isTarget'      =>  $this->isTarget
        ]);
    }

    /**
     *  将json解码成Task对象
     *
     * @param string $taskRaw
     * @return Task
     */
    public static function decode($taskRaw)
    {
        $taskJsonObj = json_decode($taskRaw);

        $task = new static($taskJsonObj->url);
        $task->property($taskJsonObj->property);

        if ($taskJsonObj->isTarget) {

            $task->markAsTarget();

        }

        return $task;
    }

}