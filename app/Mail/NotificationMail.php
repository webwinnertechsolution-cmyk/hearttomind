<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $messageText;

    public function __construct($title, $messageText)
    {
        $this->title = $title;
        $this->messageText = $messageText;
    }

    public function build()
    {
        return $this->subject($this->title)
                    ->view('email.notification');
    }
}