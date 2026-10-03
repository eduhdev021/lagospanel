<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetPasswordQueued extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public function __construct($token)
    {
        parent::__construct($token);
        $this->onConnection('database')->beforeCommit();
    }
}
