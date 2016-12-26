<?php namespace Zhiyang\Crawler\Scheduler;

/**
 * @author zhiyang <zhiyanglee@foxmail.com>
 * @date 2016/12/12
 */

use Zhiyang\Crawler\Task;

interface TaskScheduler
{
    /**
     *  初始化任务调度器
     */
    public function initialize();

    /**
     *  该URL是否已经抓取过了
     *
     * @param string $url
     * @return bool
     */
    public function urlIsDuplicated($url);

    /**
     *  获取下一个抓取任务
     *
     * @param bool $ignoreTarget 目标类型任务不参与迭代
     * @return Task|null
     */
    public function next($ignoreTarget = false);

    /**
     *  在指定秒数内不断尝试获取任务，超时则返回null
     *
     * @param int $second
     * @param bool $ignoreTarget
     * @return Task|null
     */
    public function tryNext($second, $ignoreTarget = false);

    /**
     *  获取下一个目标任务
     *
     * @return Task|null
     */
    public function nextTarget();

    /**
     *  在指定秒数内不断尝试获取目标任务，超时返回null
     *
     * @param int $second
     * @return mixed
     */
    public function tryNextTarget($second);

    /**
     *  添加一个抓取任务
     *
     * @param Task $task
     * @return bool
     */
    public function push(Task $task);

    /**
     *  添加一个目标抓取任务
     *
     * @param Task $task
     * @return bool
     */
    public function pushTarget(Task $task);

    /**
     *  标记这个URL已经抓取过(方便urlIsDuplicated去重)
     *
     * @param  string $url
     *
     */
    public function finished($url);

}