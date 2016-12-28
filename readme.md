[轻量级PHP爬虫](https://github.com/ZhiyangLeeCN/php-crawler)
======
[![Build Status](https://travis-ci.org/baidu/bfs.svg?branch=master)](https://travis-ci.org/baidu/bfs)

一个用PHP实现的轻量级爬虫，只提供了爬虫最核心的调度功能，所以整体实现非常精简，使用也非常简单并且易于上手。

##特点
1. 轻量级，内核简单非常易于上手

2. 基于Redis的调度插件支持分布式以及断点抓取

3. 易扩展易定制，可以随时按照自己的需求定制调度插件

##安装
```shell
composer require zhiyang/php-crawler:master-dev
```

##快速开始
回想一下你写爬虫的过程，总是会先从一个页面(可以叫做种子页面)开始不断提取链接，然后不断迭代这些链接并从中获取目标链接，最终抓取到目标页面的过程。

以一个新闻分页列表来说，会先从第一页开始抓取详情页的链接并在抓取后续页面的链接，其中详情页才是我们需要的最终页面(Target),
分页页面的链接只不过是辅助，然后反复进行这个过程最终抓取到我们想要的目标，对任何抓取任务总体过程都基本类似。

对于爬虫而言，最通用的地方只在于链接的管理以及调度，所以只提供了最简单的调度功能。

#### 单进程
```php
<?php

require __DIR__ . '/vendor/autoload.php';

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
```

###多进程
在windows下可以通过运行多次脚本来达到多进程的目的，在linux/unix下可通过php提供的pcntl_fork函数来实现

pcntl_fork版本
```php
<?php

require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('PRC');

use Zhiyang\Crawler\Task;
use Zhiyang\Crawler\HttpDownloader;
use Zhiyang\Crawler\Scheduler\Redis\RedisTaskScheduler;

use Symfony\Component\DomCrawler\Crawler as HtmlDom;

//创建多个进程处理抓取任务
for ($i = 0; $i < 4; $i++) {

    $pid = pcntl_fork();

    if ($pid == -1) {

        echo "pcntl_fork error!\n";

    } else if ($pid) {

        //等待子进程完成
        pcntl_wait($status);

        echo "pid:{$pid} finished !\n";

    } else {

        $scheduler = new RedisTaskScheduler("csdn-blog", "tcp://127.0.0.1:6379");
        $scheduler->initialize();
        $httpDownloader = new HttpDownloader();

        //只让第一个创建的进程添加种子页面
        if ($i == 0) {

            $scheduler->push(new Task("http://blog.csdn.net/"));

            echo "add seed page\n";

        }

        echo "crawler process start\n";

        //开始迭代链接进行抓取
        while (($task = $scheduler->tryNext(5)) != null) {

            $url = $task->getUrl();
            //判断链接是否已经抓取过了
            if ($scheduler->urlIsDuplicated($url)) {

                echo "url:{$url} skip!\n";

            } else {

                //获取任务指定的URL的响应内容
                $response = $httpDownloader->send('GET', $url);
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

                //标记URL已经完成，方便urlIsDuplicated判断去重
                $scheduler->finished($url);

            }

        }


    }

}
```

同样你还可以使用[swoole](https://github.com/swoole/swoole-src)扩展提供的swoole_process来进行多进程的抓取
```php
<?php

require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('PRC');

use Zhiyang\Crawler\Task;
use Zhiyang\Crawler\HttpDownloader;
use Zhiyang\Crawler\Scheduler\Redis\RedisTaskScheduler;

use Symfony\Component\DomCrawler\Crawler as HtmlDom;

for ($i = 0; $i < 4; $i++) {

    $crawler_process = new swoole_process(function ($worker) use($i) {

        $pid = $worker->pid;
        $worker->name("crawler process");
        $scheduler = new RedisTaskScheduler("csdn-blog", "tcp://127.0.0.1:6379");
        $httpDownloader = new HttpDownloader();

        //只让第一个创建的进程添加种子页面
        if ($i == 0) {

            $scheduler->push(new Task("http://blog.csdn.net/"));

            echo "pid:{$pid} add seed page\n";

        }

        echo "process start pid:" . $pid . "\n";

        //开始迭代链接进行抓取
        while (($task = $scheduler->tryNext(5)) != null) {

            $url = $task->getUrl();
            //判断链接是否已经抓取过了
            if ($scheduler->urlIsDuplicated($url)) {

                echo "pid:{$pid} url:{$url} skip!\n";

            } else {

                //获取任务指定的URL的响应内容
                $response = $httpDownloader->send('GET', $url);
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

                    echo "pid:{$pid} title:" . $title . "\n";

                }

                $scheduler->finished($url);

            }

        }

    });

    //启动进程
    $pid = $crawler_process->start();

    echo "pid{$pid} start!\n";

    swoole_process::wait();

    echo "pid: {$pid} finished\n";

}
```
