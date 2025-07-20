<?php 

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppMessageBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $waId;

    public function __construct($waId)
    {
        $this->waId = $waId;
    }

    public function handle()
    {
        $cacheKey = "whatsapp_thread_{$this->waId}";

        $batch = Cache::pull($cacheKey); // Get and remove

        if (!$batch || (empty($batch['texts']) && empty($batch['attachments']))) {
            Log::info("No data to process for: {$this->waId}");
            return;
        }

        $finalMessage = implode("\n", $batch['texts']) ?: 'No text message';

        $response = [
            "Source"       => "WHATSAPP",
            "TraceId"      => uniqid(),
            "Sender"       => $this->waId,
            "Timestamp"    => $batch['lastTimestamp'],
            "Message"      => $finalMessage,
            "AttachmentId" => $batch['attachmentIds'],
            "Attachments"  => $batch['attachments'],
            "Subject"      => "Final WhatsApp Batch",
        ];

        // Replace this with your logic: notify, save to DB, send to API, etc.
        Log::info("Processed WhatsApp Batch:", $response);
    }
}
