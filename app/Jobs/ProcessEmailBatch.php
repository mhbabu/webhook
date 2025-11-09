<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEmailBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5; // max attempts

    public $backoff = [10, 30, 60]; // seconds between retries

    protected $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle()
    {
        Log::info('📨 ProcessEmailBatch started', ['payload' => $this->payload]);

        try {
            app(DispatcherService::class)->send($this->payload);
            Log::info('✅ ProcessEmailBatch sent successfully', ['conversationId' => $this->payload['conversationId']]);
        } catch (\Throwable $e) {
            Log::error('❌ ProcessEmailBatch failed: '.$e->getMessage(), ['payload' => $this->payload]);
            throw $e; // rethrow to trigger retry
        }
    }
}
