<?php

namespace Plugins\blog\v1;

use Plugins\AbstractPlugin;

/**
 * Blog 插件
 * 提供博客文章管理功能
 */
class blog extends AbstractPlugin
{
    /**
     * 插件安装前钩子
     *
     * @param  string  $projectPrefix  项目前缀
     */
    public function onBeforeInstall(string $projectPrefix): void
    {
        // 创建必要的数据库表
        $this->createBlogTables($projectPrefix);
    }

    /**
     * 插件安装后钩子
     *
     * @param  string  $projectPrefix  项目前缀
     */
    public function onAfterInstall(string $projectPrefix): void
    {
        // 初始化示例数据
        $this->initializeSampleData($projectPrefix);
    }

    /**
     * 插件卸载前钩子
     *
     * @param  string  $projectPrefix  项目前缀
     */
    public function onBeforeUninstall(string $projectPrefix): void
    {
        // 清理数据库表
        $this->dropBlogTables($projectPrefix);
    }

    /**
     * 注册路由
     */
    public function registerRoutes(): void
    {
        // 注册博客相关路由
    }

    /**
     * 创建博客相关数据库表
     *
     * @param  string  $projectPrefix  项目前缀
     */
    private function createBlogTables(string $projectPrefix): void
    {
        // 创建文章表
        // 创建分类表
        // 创建标签表
        // 创建评论表
    }

    /**
     * 初始化示例数据
     *
     * @param  string  $projectPrefix  项目前缀
     */
    private function initializeSampleData(string $projectPrefix): void
    {
        // 创建示例文章
        // 创建示例分类
    }

    /**
     * 删除博客相关数据库表
     *
     * @param  string  $projectPrefix  项目前缀
     */
    private function dropBlogTables(string $projectPrefix): void
    {
        // 删除所有博客相关表
    }
}
