<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Log;

class PageNoticeException extends \Exception
{
    public $message;

    public function __construct(string $message = '')
    {
        $this->message = $message;
    }

    public function render()
    {
        Log::info('PageNoticeException'.$this->message);

        return back()->withInput()->withErrors(['message' => $this->message]);
    }
}
