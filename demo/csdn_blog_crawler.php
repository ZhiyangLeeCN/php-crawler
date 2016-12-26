<?php

/**
 * @author zhiyang <zhiyanglee@foxmail.com>
 * @date 2016/12/12
 */

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('PRC');

use Zhiyang\Crawler\Task;
use Zhiyang\Crawler\HttpDownloader;
use Zhiyang\Crawler\Scheduler\Redis\RedisTaskScheduler;

use Symfony\Component\DomCrawler\Crawler as HtmlDom;

$scheduler = new RedisTaskScheduler("csdn-blog", "tcp://127.0.0.1:6379");
$scheduler->initialize();
$httpDownloader = new HttpDownloader();

//添加种子页面
$scheduler->push(new Task("http://blog.csdn.net/"));

//开始迭代链接进行抓取
while (($task = $scheduler->next()) != null) {

    $url = $task->getUrl();
    if ($scheduler->urlIsDuplicated($url)) {

        echo "url:{$url} skip!\n";

    } else {

        //获取任务指定的URL的响应内容
        $response = $httpDownloader->send('GET', $task->getUrl());
        $body = $response->getBody()->getContents();
        $htmlDom = new HtmlDom($body, $task->getUrl(), $task->getUrlHost());

        //如果是首页，只获取文章详情页链接
        if (preg_match("/http:\\/\\/blog.csdn.net\\//", $task->getUrl())) {

            $htmlDom->filterXPath('//h3[@class="tracking-ad"]')->filter("a")->each(function(HtmlDom $node, $index) use($scheduler) {

                //添加文章详情地址添加到调度器中
                $scheduler->pushTarget(new Task($node->attr('href')));

            });

        }

        //如果是文章详情页，那么就打印每个文章详情页的标题
        if (preg_match("/http:\\/\\/blog.csdn.net\\/\w+\\/article\\/details\\/\d+/", $task->getUrl())) {

            $title = $htmlDom->filter('title')->html();

            echo "title:" . $title . "\n";

        }

        //标记这个URL已经抓取过，方便urlIsDuplicated判断去重
        $scheduler->finished($url);

    }


}

echo "finished!\n";