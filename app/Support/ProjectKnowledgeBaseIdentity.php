<?php

namespace App\Support;

class ProjectKnowledgeBaseIdentity
{
    private const PROJECT_KB_USER_ID_OFFSET = 1000000000000;

    public static function userIdForProject(int $projectId): int
    {
        return self::PROJECT_KB_USER_ID_OFFSET + max(0, $projectId);
    }
}

