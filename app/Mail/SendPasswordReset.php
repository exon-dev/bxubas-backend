<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public string $emailMessage; // Renamed from "message" to "emailMessage"
    public string $recipient;
    public string $resetLink;

    /**
     * Create a new message instance.
     *
     * @param string $emailMessage The main content of the email
     * @param string $recipient The recipient's name
     * @param string $resetLink The password reset link
     */
    public function __construct(string $emailMessage, string $recipient, string $resetLink)
    {
        $this->emailMessage = $emailMessage;  // Updated variable name
        $this->recipient = $recipient;
        $this->resetLink = $resetLink;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('bxubas@gmail.com', 'BXU Business Authority Sentinel'),
            subject: 'Password Reset Verification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail-template.send-password-reset',
            with: [
                'emailMessage' => $this->emailMessage, // Updated key name
                'recipient' => $this->recipient,
                'resetLink' => $this->resetLink,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
