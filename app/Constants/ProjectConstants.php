<?php

namespace App\Constants;

class ProjectConstants
{
    /**
     * 项目框架层表前缀，例如: {prefix}_pf_molds
     */
    public const PROJECT_FRAMEWORK_PREFIX = '_pf_';

    /**
     * 内容模型层表前缀（如有需要可以使用），例如: {prefix}_mc_articles
     */
    public const MODEL_CONTENT_PREFIX = '_mc_';

    /**
     * 系统预定义表前缀（在模型表名计算时需要剥离）
     */
    public const SYSTEM_PREFIXES = ['sys_'];
}
