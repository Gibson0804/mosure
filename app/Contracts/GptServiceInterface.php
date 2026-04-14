<?php

namespace App\Contracts;

interface GptServiceInterface
{
    public function chat(array $messages);
}
