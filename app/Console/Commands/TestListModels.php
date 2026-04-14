<?php

namespace App\Console\Commands;

use App\Adapter\LlmAdapter;
use Illuminate\Console\Command;

class TestListModels extends Command
{
    protected $signature = 'test:list-models {provider=deepseek}';

    public function handle(): int
    {
        $provider = $this->argument('provider');

        $this->info("Listing models for: {$provider}");

        try {
            $models = LlmAdapter::getModels($provider);
            $this->info('Models: '.implode(', ', $models));
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());
        }

        return 0;
    }
}
