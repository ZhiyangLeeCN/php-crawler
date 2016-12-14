<?php namespace Zhiyang\Crawler\Scheduler\Redis;

/**
 * @author zhiyang <zhiyanglee@foxmail.com>
 * @date 2016/12/13
 */

use Zhiyang\Crawler\Scheduler\TaskScheduler;

use Predis\Client as RedisClient;
use Zhiyang\Crawler\Task;

class RedisTaskScheduler implements  TaskScheduler
{
    /**
     * @var string 调度任务名称
     */
    private $taskName;

    /**
     * @var RedisClient
     */
    private $redisClient;

    /**
     * @var string Redis密码
     */
    private $redisPassword;

    /**
     * RedisRequestScheduler constructor.
     * @param string $taskName
     * @param array|null $parameters
     * @param array|null $options
     * @param string|null $password
     */
    public function __construct($taskName, $parameters = null, $options = null, $password = null)
    {
        $this->taskName = $taskName;
        $this->redisClient = new RedisClient($parameters, $options);
        $this->redisPassword = $password;
    }

    /**
     *  初始化任务调度器
     */
    public function initialize()
    {
        if (!is_null($this->redisPassword)) {

            $this->redisClient->auth($this->redisPassword);

        }

        //建立爬虫信息汇总表
        $this->buildSchedulerInfo();

    }

    /**
     * 建立该爬虫任务调度的总表
     */
    private function buildSchedulerInfo()
    {
        if (!$this->redisClient->exists($this->getInfoKeyName())) {

            $nowDateTime = date('Y-m-d H:i:s');

            $this->redisClient->hmget($this->getInfoKeyName(), [
                //任务名称
                'taskName'                  => $this->taskName,

                //调度创建时间
                'createdAt'                 => $nowDateTime,

                //还剩下多少待抓取的链接
                'links_count'               =>  0,

                //链接总和
                'links_sum'                 =>  0,

                //还剩下多少待抓取的目标链接
                'target_links_count'        =>  0,

                //目标链接总和
                'target_links_sum'          =>  0,

                //最后一次迭代调度器的时间
                'last_iterator_date_time'    =>  $nowDateTime,

                //最后一次push抓取任务的时间
                'last_push_max_date_time'    =>  $nowDateTime

            ]);

        }
    }

    /**
     * 自增链接剩余数量计数器
     */
    private function incrementLinksCount()
    {
        $this->redisClient->hincrby($this->getInfoKeyName(), 'links_count', 1);
    }

    /**
     * 自减链接剩余数量计数器
     */
    private function decrementLinksCount()
    {
        $this->redisClient->hincrby($this->getInfoKeyName(), 'links_count', -1);
    }

    /**
     * 自增链接数量总和计数器
     */
    private function incrementLinksSum()
    {
        $this->redisClient->hincrby($this->getInfoKeyName(), 'links_sum', 1);
    }

    /**
     * 自增目标链接剩余数量计数器
     */
    private function incrementTargetLinksCount()
    {
        $this->redisClient->hincrby($this->getInfoKeyName(), 'target_links_count', 1);
    }

    /**
     * 自减目标链接剩余数量计数器
     */
    private function decrementTargetLinksCount()
    {
        $this->redisClient->hincrby($this->getInfoKeyName(), 'target_links_count', -1);
    }

    /**
     * 自增目标链接总数计数器
     */
    private function incrementTargetLinksSum()
    {
        $this->redisClient->hincrby($this->getInfoKeyName(), 'target_links_sum', 1);
    }

    /**
     *  添加历史记录
     *
     * @param $url
     */
    private function historySetAdd($url)
    {
        $this->redisClient->sadd($this->getHistorySetKeyName(), $url);
    }

    /**
     *  设置最后一次迭代调度器的时间
     *
     * @param string|null $dateTime
     */
    private function setLastIteratorDateTime($dateTime = null)
    {
        if (is_null($dateTime)) {

            $dateTime = date('Y-m-d H:i:s');

        }

        $this->redisClient->hset($this->getInfoKeyName(), 'last_iterator_date_time', $dateTime);

    }

    /**
     *  设置最后一次的抓取时间
     *
     * @param string|null $dateTime
     */
    private function setLastPushDateTime($dateTime = null)
    {
        if (is_null($dateTime)) {

            $dateTime = date('Y-m-d H:i:s');

        }

        $this->redisClient->hset($this->getInfoKeyName(), 'last_push_max_date_time', $dateTime);
    }

    /**
     *  获取调度信息表在Redis中的key名
     *
     * @return string
     */
    protected function getInfoKeyName()
    {
        return 'ZhiYang:Crawlers:Scheduler:' . $this->taskName . ':info';
    }

    /**
     *  获取任务队列在redis中的key名
     *
     * @return string
     */
    protected function getIteratorQueueKeyName()
    {
        return 'ZhiYang:Crawlers:Queue:Requests:' . $this->taskName;
    }

    /**
     *  获取目标任务队列在redis中的key名
     *
     * @return string
     */
    protected function getTargetIteratorQueueKeyName()
    {
        return 'ZhiYang:Crawlers:Queue:TargetRequests:' . $this->taskName;
    }

    protected function getHistorySetKeyName()
    {
        return 'ZhiYang:Crawlers:History:' . $this->taskName;
    }

    /**
     *  该URL是否已经抓取过了
     *
     * @param string $url
     * @return bool
     */
    public function urlIsDuplicated($url)
    {
        return $this->redisClient->sismember($this->getHistorySetKeyName(), $url) > 0;
    }

    /**
     *  获取下一个抓取任务
     *
     * @param bool $ignoreTarget 目标类型任务不参与迭代
     * @return Task|null
     */
    public function next($ignoreTarget = false)
    {
        $task = $this->redisClient->lpop($this->getIteratorQueueKeyName());

        if (is_null($task)) {

            $task = $this->redisClient->lpop($this->getTargetIteratorQueueKeyName());

        }

        if (!is_null($task)) {

            $task = Task::decode($task);

            if ($task->isTarget()) {

                $this->decrementTargetLinksCount();

            } else {

                $this->decrementLinksCount();

            }

        }

        $this->setLastIteratorDateTime();

        return $task;

    }

    /**
     *  获取下一个目标任务
     *
     * @return Task|null
     */
    public function nextTarget()
    {
        $task = $this->redisClient->lpop($this->getTargetIteratorQueueKeyName());

        if (!is_null($task)) {

            $task = Task::decode($task);

        }

        $this->setLastIteratorDateTime();
        $this->decrementTargetLinksCount();

        return $task;

    }

    /**
     *  添加一个抓取任务
     *
     * @param Task $task
     * @return bool
     */
    public function push(Task $task)
    {
        $result = $this->redisClient->rpush(
            $this->getIteratorQueueKeyName(), $task->encode()
        );

        if ($result > 0) {

            $this->incrementLinksCount();
            $this->incrementLinksSum();
            $this->historySetAdd($task->getUrl());
            $this->setLastPushDateTime();

            return true;

        } else {

            return false;

        }
    }

    /**
     *  添加一个目标抓取任务
     *
     * @param Task $task
     * @return bool
     */
    public function pushTarget(Task $task)
    {
        $task->markAsTarget();

        $result = $this->redisClient->rpush(
            $this->getTargetIteratorQueueKeyName(), $task->encode()
        );

        if ($result > 0) {

            $this->incrementTargetLinksCount();
            $this->incrementTargetLinksSum();
            $this->historySetAdd($task->getUrl());
            $this->setLastPushDateTime();

            return true;

        } else {

            return false;

        }

    }
}