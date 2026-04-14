<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;

class SystemMailService
{
    private SystemConfigService $configService;

    public function __construct(SystemConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function send(string|array $to, Mailable $mailable): void
    {
        $this->applySystemMailConfig();
        Mail::to($to)->send($mailable);
    }

    /**
     * 发送纯文本邮件
     *
     * @param  string|array  $to  收件人邮箱
     * @param  string  $subject  邮件主题
     * @param  string  $body  邮件内容
     */
    public function sendRaw(string|array $to, string $subject, string $body): void
    {
        $this->applySystemMailConfig();
        Mail::raw($body, function ($message) use ($to, $subject) {
            $message->to($to)->subject($subject);
        });
    }

    private function applySystemMailConfig(): void
    {
        $cfg = $this->configService->getConfigRaw();
        $mail = Arr::get($cfg, 'mail', []);

        $mailer = (string) ($mail['mailer'] ?? config('mail.default'));
        if ($mailer === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $mail['host'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => (int) ($mail['port'] ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.encryption' => ($mail['encryption'] ?? null) ?: null,
                'mail.mailers.smtp.username' => $mail['username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $mail['password'] ?? config('mail.mailers.smtp.password'),
            ]);
        } elseif ($mailer === 'sendmail') {
            config(['mail.default' => 'sendmail']);
        } elseif ($mailer === 'log') {
            config(['mail.default' => 'log']);
        }

        if (! empty($mail['from_address'])) {
            config(['mail.from.address' => $mail['from_address']]);
        }
        if (! empty($mail['from_name'])) {
            config(['mail.from.name' => $mail['from_name']]);
        }
    }
}
