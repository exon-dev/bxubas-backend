<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ViolationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject($this->data['subject'])
            ->view('mail-template.send-inspection')
            ->with([
                'messageContent' => $this->data['message'], // Changed from 'message' to 'messageContent'
                'recipient_name' => $this->data['recipient_name']
            ]);
    }
}
