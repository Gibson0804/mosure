<?php

namespace Tests\Concerns;

trait InteractsWithInstallLock
{
    private ?string $installLockBackupPath = null;

    protected function backupInstallLockState(): void
    {
        $lockPath = base_path('.locked');

        if (file_exists($lockPath)) {
            $backupPath = base_path('.locked.testing-backup');
            copy($lockPath, $backupPath);
            unlink($lockPath);
            $this->installLockBackupPath = $backupPath;
        }
    }

    protected function restoreInstallLockState(): void
    {
        $lockPath = base_path('.locked');

        if (file_exists($lockPath)) {
            unlink($lockPath);
        }

        if ($this->installLockBackupPath && file_exists($this->installLockBackupPath)) {
            rename($this->installLockBackupPath, $lockPath);
        }

        $this->installLockBackupPath = null;
    }

    protected function createInstallLock(): void
    {
        file_put_contents(base_path('.locked'), 'locked-testing');
    }

    protected function removeInstallLock(): void
    {
        $lockPath = base_path('.locked');

        if (file_exists($lockPath)) {
            unlink($lockPath);
        }
    }
}
