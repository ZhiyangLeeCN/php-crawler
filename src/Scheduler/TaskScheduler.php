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
     *  获取下一个目标任务
     *
     * @return Task|null
     */
    public function nextTarget();

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

}