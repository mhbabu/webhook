<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\Webhook\EmailWebhookController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchEmails extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email:fetch';

    /**
     * The console command description.
     */
    protected $description = 'Fetch incoming emails from IMAP and create conversations';

    protected EmailWebhookController $emailController;

    public function __construct(EmailWebhookController $emailController)
    {
        parent::__construct();
        $this->emailController = $emailController;
    }

    public function handle(): int
    {
        Log::info('⏱ Email fetch cron started');

        try {
            $this->emailController->receiveEmailData(request());
            Log::info('✅ Email fetch cron completed');
        } catch (\Throwable $e) {
            Log::error('❌ Email fetch cron failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return Command::SUCCESS;
    }
}
