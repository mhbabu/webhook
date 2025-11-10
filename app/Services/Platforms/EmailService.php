<?php

namespace App\Services\Platforms;

use App\Jobs\ProcessEmailBatch;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Platform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\IMAP\Facades\Client;

class EmailService
{
    public function sendEmail($to, $subject, $message, $attachments = [])
    {
        Mail::send([], [], function ($mail) use ($to, $subject, $message, $attachments) {
            $mail->to($to)
                ->subject($subject)
                ->html($message);

            if (! empty($attachments)) {
                foreach ($attachments as $filePath) {
                    $mail->attach($filePath);
                }
            }
        });

        return true;
    }

    public function fetchUnreadEmails()
    {
        \Log::info('IMAP CRON Triggered');

        $client = Webklex\IMAP\Facades\Client::account('gmail');

        try {
            $client->connect();
            \Log::info('✅ Gmail IMAP connected successfully!');
        } catch (\Throwable $e) {
            \Log::error('❌ Gmail IMAP connection failed: '.$e->getMessage());

            return false;
        }

        // Get all folders (INBOX, Sent, etc.)
        $folders = $client->getFolders();

        foreach ($folders as $folder) {
            // Fetch unseen messages in this folder
            $messages = $folder->messages()->unseen()->get();
            \Log::info("Folder: {$folder->name}, Unread Messages: ".count($messages));

            foreach ($messages as $message) {
                $emailData = [
                    'message_id' => $message->getMessageId(),
                    'from' => $message->getFrom()[0]->mail ?? null,
                    'to' => implode(',', $message->getTo()->pluck('mail')->toArray()),
                    'subject' => $message->getSubject(),
                    'text_body' => $message->getTextBody(),
                    'html_body' => $message->getHTMLBody(),
                    'attachments' => [],
                ];

                // Process attachments
                foreach ($message->getAttachments() as $att) {
                    $emailData['attachments'][] = [
                        'name' => $att->getName(),
                        'content' => $att->getContent(), // optionally save to storage
                    ];
                }

                // Log each unread email
                \Log::info('📥 New unread email fetched', [
                    'message_id' => $emailData['message_id'],
                    'from' => $emailData['from'],
                    'subject' => $emailData['subject'],
                    'folder' => $folder->name,
                ]);

                // Dispatch job to store/process email
                ProcessEmailBatch::dispatch($emailData);

                // Mark as read
                $message->setFlag('Seen');
            }
        }

        return true;
    }

    public function receiveEmail()
    {
        $platform = Platform::whereRaw('LOWER(name) = ?', ['email'])->first();
        $platformId = $platform->id ?? null;
        $platformName = strtolower($platform->name ?? 'email');

        $client = Client::account('gmail');

        try {
            $client->connect();
            Log::info('✅ Gmail IMAP connected successfully!');
        } catch (\Throwable $e) {
            Log::error('❌ Gmail IMAP connection failed: '.$e->getMessage());

            return false;
        }

        try {
            $inbox = $client->getFolder('INBOX');

            $messages = $inbox->messages()
                ->seen()
                ->limit(5)
                ->leaveUnread()
                ->fetchOrderDesc()
                ->get();

            foreach ($messages as $imapMsg) {

                $uid = $imapMsg->getUid();

                if (Message::where('platform_message_id', $uid)->exists()) {
                    continue;
                }

                $fromMail = (string) optional($imapMsg->getFrom()->first())->mail;
                $toMails = implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getTo()->all()));
                $subject = (string) $imapMsg->getSubject();
                $textBody = (string) $imapMsg->getTextBody();
                $htmlBody = (string) $imapMsg->getHTMLBody();
                $timestamp = now()->timestamp;

                // attachments safe
                $attachmentsArr = [];
                $storagePath = 'mail_attachments/'.now()->format('Ymd');

                foreach ($imapMsg->getAttachments() as $att) {
                    $filename = uniqid().'_'.$att->name;
                    $path = storage_path('app/'.$storagePath.'/'.$filename);

                    if (! is_dir(dirname($path))) {
                        mkdir(dirname($path), 0777, true);
                    }

                    file_put_contents($path, $att->content);
                    $attachmentsArr[] = $storagePath.'/'.$filename;
                }

                // persist + we need conversation outside closure
                DB::transaction(function () use (
                    $uid, $platformId, $platformName, $fromMail, $subject, $textBody, $htmlBody,
                    &$conversation, &$payload
                ) {
                    $customer = Customer::firstOrCreate([
                        'email' => $fromMail,
                        'platform_id' => $platformId,
                    ]);

                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        ->where(function ($q) {
                            $q->whereNull('end_at')
                                ->orWhere('created_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
                        })
                        ->latest()
                        ->first();

                    if (! $conversation) {
                        $conversation = Conversation::create([
                            'customer_id' => $customer->id,
                            'platform' => $platformName,
                            'trace_id' => 'mail-'.now()->format('YmdHis').'-'.uniqid(),
                        ]);
                    }

                    Message::create([
                        'conversation_id' => $conversation->id,
                        'platform_id' => $platformId,
                        'sender_id' => $customer->id,
                        'sender_type' => Customer::class,
                        'platform_message_id' => $uid,
                        'subject' => $subject,
                        'content' => $textBody,
                        'html_content' => $htmlBody,
                        'direction' => 'incoming',
                        'status' => 'received',
                    ]);
                });

                // payload unified
                $payload = [
                    'source' => 'email',
                    'traceId' => $conversation->trace_id ?? null,
                    'conversationId' => $conversation->id ?? null,
                    // 'conversationType' => $isNewConversation ? 'new' : 'old',
                    'senderEmail' => $fromMail,
                    'subject' => $subject,
                    // 'text_body' => $textBody,
                    // 'html_body' => $htmlBody,
                    // 'attachments' => $attachmentsArr,
                ];

                // Log::info('📥 New email received', [
                //     'platform_message_id' => $uid,
                //     'from' => $fromMail,
                //     'subject' => $subject,
                // ]);

                Log::info('📥 New email received', ['payload' => $payload]);

                DB::afterCommit(function () use ($payload) {
                    ProcessEmailBatch::dispatch($payload)->onQueue('dispatcher');
                });

                $imapMsg->setFlag('Seen');
            }

        } catch (\Throwable $e) {
            Log::error('IMAP Read error: '.$e->getMessage());
        }

        $client->disconnect();

        return true;
    }

    /**
     * Send payload to dispatcher API
     */
    private function sendToDispatcher(array $payload): void
    {
        try {
            $response = Http::acceptJson()->post(config('dispatcher.url').config('dispatcher.endpoints.handler'), $payload);

            if ($response->ok()) {
                Log::info('[CUSTOMER MESSAGE FORWARDED]', $payload);
            } else {
                Log::error('[CUSTOMER MESSAGE FORWARDED] FAILED', ['payload' => $payload, 'response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('[CUSTOMER MESSAGE FORWARDED] ERROR', ['exception' => $e->getMessage()]);
        }
    }
}
