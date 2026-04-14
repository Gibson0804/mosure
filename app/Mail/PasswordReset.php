<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $token;

    public $resetLink;

    /**
     * Create a new message instance.
     *
     * @param  mixed  $user
     * @param  string  $token
     * @param  string  $resetLink
     * @return void
     */
    public function __construct($user, $token, $resetLink)
    {
        $this->user = $user;
        $this->token = $token;
        $this->resetLink = $resetLink;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            from: new Address('bov_wuziqi@163.com', 'Mosure'),
            subject: 'Mosure 密码重置',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'emails.password-reset',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
