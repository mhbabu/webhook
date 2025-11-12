<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Platforms\EmailService;
use Illuminate\Http\Request;
use Webklex\IMAP\Facades\Client;

class EmailController extends Controller
{
    protected $EmailService;

    public function __construct(EmailService $EmailService)
    {
        $this->EmailService = $EmailService;
    }

    // SEND EMAIL WEBHOOK
    public function send(Request $request)
    {
        \Log::info('Send Email Webhook:', $request->all());
        $request->validate([
            'to' => 'required|email',
            'subject' => 'required',
            'message' => 'required',
        ]);

        $this->sender->sendEmail(
            $request->to,
            $request->subject,
            $request->message,
            $request->attachments ?? []
        );

        return response()->json(['status' => 'sent']);
    }

    public function testGmailImapConnection()
    {
        // $client = Webklex\IMAP\Facades\Client::account('gmail');
        $client = Client::account('gmail');

        try {
            $client->connect();
            \Log::info('✅ Gmail IMAP connected successfully!');

            return true;
        } catch (\Throwable $e) {
            \Log::error('❌ Gmail IMAP connection failed: '.$e->getMessage());

            return false;
        }
    }
}
