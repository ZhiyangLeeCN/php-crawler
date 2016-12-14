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
$httpDownloader = new HttpDownloader();

//添加种子页面
$scheduler->push(new Task("http://blog.csdn.net/"));

//开始迭代链接进行抓取
while (($task = $scheduler->next()) != null) {

    //获取任务指定的URL的响应内容
    $response = $httpDownloader->send('GET', $task->getUrl());
    $body = $response->getBody()->getContents();
    $htmlDom = new HtmlDom($body, $task->getUrl(), $task->getUrlHost());

    //如果是首页，只获取文章详情页链接
    if (preg_match("/http:\\/\\/blog.csdn.net\\//", $task->getUrl())) {

        $htmlDom->filterXPath('//h3[@class="tracking-ad"]')->filter("a")->each(function(HtmlDom $node, $index) use($scheduler) {

            $scheduler->pushTarget(new Task($node->attr('href')));

        });

    }

    //如果是文章详情页，那么就打印每个文章详情页的标题
    if (preg_match("/http:\\/\\/blog.csdn.net\\/\w+\\/article\\/details\\/\d+/", $task->getUrl())) {

        $title = $htmlDom->filter('title')->html();

        echo "title:" . $title . "\n";

    }


}

echo "all task ok!\n";