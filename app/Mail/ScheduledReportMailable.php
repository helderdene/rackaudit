<?php

namespace App\Mail;

use App\Models\ReportSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for scheduled report delivery.
 *
 * Sends the generated report as an email attachment to distribution list members.
 * Includes the report name, generation timestamp, and applied filters summary.
 */
class ScheduledReportMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public ReportSchedule $schedule,
        public string $filePath,
        public string $filterDescription
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $date = now()->format('Y-m-d');

        return new Envelope(
            subject: "{$this->schedule->name} - Generated {$date}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.scheduled-report',
            with: [
                'reportName' => $this->schedule->name,
                'reportType' => $this->schedule->report_type->label(),
                'generatedAt' => now()->format('F j, Y g:i A'),
                'filterDescription' => $this->filterDescription,
                'format' => $this->schedule->format->label(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $filename = $this->buildAttachmentFilename();

        return [
            Attachment::fromPath($this->filePath)
                ->as($filename)
                ->withMime($this->schedule->format->mimeType()),
        ];
    }

    /**
     * Build a descriptive filename for the attachment.
     */
    protected function buildAttachmentFilename(): string
    {
        $baseName = str($this->schedule->name)->slug()->toString();
        $date = now()->format('Y-m-d');
        $extension = $this->schedule->format->extension();

        return "{$baseName}-{$date}.{$extension}";
    }
}
