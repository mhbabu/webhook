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

    protected $platform;

    protected $emailData;

    public function __construct($platform, $emailData)
    {
        $this->platform = $platform;
        $this->emailData = $emailData;
    }

    public function handle()
    {
        Log::info('Processing email batch for platform: '.$this->platform, [
            'emailData' => $this->emailData,
        ]);
        $platform = Platform::where('name', $this->platform)->firstOrFail();
        // here will be parsing, store to DB etc.
        // this logic will run from IMAP service for each incoming email
    }
}
