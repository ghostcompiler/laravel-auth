<?php

namespace GhostCompiler\LaravelAuth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;

class OtpCodeMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        protected string $viewName,
        protected string $subjectLine,
        protected array $data
    ) {
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view($this->viewName, $this->data);
    }
}
