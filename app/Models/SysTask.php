<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class SysTask extends Model
{
    protected $table = 'sys_tasks';

    public const TYPE_RICH_TEXT_EDIT = 'rich_text_edit';

    public const TYPE_MARKDOWN_EDIT = 'markdown_edit';

    public static function getTableSchema()
    {
        return function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('project_prefix', 64)->nullable()->index();

            $table->string('domain', 64)->nullable()->index();
            $table->string('type', 100);
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();

            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('root_id')->nullable()->index();
            $table->string('group_key', 64)->nullable()->index();
            $table->unsignedInteger('sort_no')->nullable()->index();

            $table->string('status', 32)->default('pending');
            $table->string('stage', 64)->nullable();
            $table->unsignedInteger('progress_total')->nullable();
            $table->unsignedInteger('progress_done')->nullable();
            $table->unsignedInteger('progress_failed')->nullable();

            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->json('error_detail')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(0);
            $table->timestamp('run_at')->nullable()->index();
            $table->unsignedInteger('timeout_sec')->nullable();
            $table->integer('priority')->default(0);
            $table->string('queue', 64)->nullable();
            $table->timestamp('locked_at')->nullable()->index();
            $table->string('lock_token', 64)->nullable()->index();
            $table->string('locked_by', 64)->nullable();
            $table->timestamp('canceled_at')->nullable()->index();
            $table->string('cancel_reason', 255)->nullable();

            $table->string('request_id', 64)->nullable()->index();
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->unsignedInteger('cost_ms')->nullable();
            $table->unsignedInteger('token_prompt')->nullable();
            $table->unsignedInteger('token_completion')->nullable();
            $table->unsignedInteger('token_total')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('related_type', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('related_id');
        };
    }

    protected $fillable = [
        'project_prefix',
        'domain',
        'type',
        'title',
        'description',
        'parent_id',
        'root_id',
        'group_key',
        'sort_no',
        'status',
        'stage',
        'progress_total',
        'progress_done',
        'progress_failed',
        'payload',
        'result',
        'error_message',
        'error_code',
        'error_detail',
        'attempts',
        'max_attempts',
        'run_at',
        'timeout_sec',
        'priority',
        'queue',
        'locked_at',
        'lock_token',
        'locked_by',
        'canceled_at',
        'cancel_reason',
        'request_id',
        'provider',
        'model',
        'cost_ms',
        'token_prompt',
        'token_completion',
        'token_total',
        'started_at',
        'finished_at',
        'requested_by',
        'related_type',
        'related_id',
        'retry_count',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'error_detail' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'retry_count' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'run_at' => 'datetime',
        'timeout_sec' => 'integer',
        'priority' => 'integer',
        'progress_total' => 'integer',
        'progress_done' => 'integer',
        'progress_failed' => 'integer',
        'cost_ms' => 'integer',
        'token_prompt' => 'integer',
        'token_completion' => 'integer',
        'token_total' => 'integer',
        'parent_id' => 'integer',
        'root_id' => 'integer',
        'sort_no' => 'integer',
        'locked_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELED = 'canceled';

    public const TYPE_CONTENT_GENERATION = 'content_generation';

    public const TYPE_CONTENT_BATCH = 'content_batch';

    public const TYPE_CONTENT_BATCH_DIRECT = 'content_batch_direct';

    public const TYPE_MOLD_SUGGEST = 'mold_suggest';

    public const TYPE_AI_AGENT_RUN = 'ai_agent_run';

    public const TYPE_MEDIA_CAPTURE = 'media_capture';

    public const TYPE_CHROME_CAPTURE_AI = 'chrome_capture_ai';

    public const TYPE_PAGE_GENERATION = 'page_generation';

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
