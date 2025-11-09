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
            \Log::info('✅ Gmail IMAP connected successfully!');
        } catch (\Throwable $e) {
            \Log::error('❌ Gmail IMAP connection failed: '.$e->getMessage());

            return false;
        }

        try {
            $inbox = $client->getFolder('INBOX');
            // $messages = $inbox->messages()->unseen()->get();
            $messages = $inbox->messages()
                ->seen()
                ->limit(2)
                ->leaveUnread() // optional (so it doesn’t change seen flag)
                ->fetchOrderDesc() // <-- this is the correct supported sort
                ->get();

            \Log::info('Folder: INBOX, read Messages: '.count($messages));

            foreach ($messages as $message) {

                $uid = $message->getUid();
                Log::info("Processing message UID: {$uid}");
                // $parentMessage = Message::where('platform_message_id', $uid)->first();

                if (Message::where('platform_message_id', $uid)->exists()) {
                    continue;
                }

                // $toMails = [];
                // foreach ($message->getTo() as $to) {
                //     $toMails[] = $to->mail;
                // }

                $messageId = (string) $message->getMessageId();
                $fromMail = (string) optional($message->getFrom()->first())->mail;
                $toMails = implode(',', array_map(fn ($t) => (string) $t->mail, $message->getTo()->all()));
                $subject = (string) $message->getSubject();
                $textBody = (string) $message->getTextBody();
                $htmlBody = (string) $message->getHTMLBody();

                // handle attachments
                $attachmentsArr = [];
                $storagePath = 'mail_attachments/'.now()->format('Ymd');

                foreach ($message->getAttachments() as $attachment) {
                    $filename = uniqid().'_'.$attachment->name;
                    $savedPath = storage_path('app/'.$storagePath.'/'.$filename);

                    if (! file_exists(dirname($savedPath))) {
                        mkdir(dirname($savedPath), 0777, true);
                    }

                    file_put_contents($savedPath, $attachment->content);

                    $attachmentsArr[] = $storagePath.'/'.$filename;
                }

                DB::transaction(function () use ($uid, $platformId, $platformName, $message, $fromMail, $subject, $textBody, $htmlBody) {
                    // 1️⃣ Find or create the customer
                    $customer = Customer::firstOrCreate(
                        // ['platform_user_id' => $senderId],
                        [
                            'email' => $fromMail,
                            'platform_id' => $platformId,
                        ]
                    );

                    // 2️⃣ Find or create active conversation
                    $conversation = Conversation::where('customer_id', $customer->id)
                        ->where('platform', $platformName)
                        ->where(function ($query) {
                            $query->whereNull('end_at')
                                ->orWhere('created_at', '>=', now()->subHours(config('services.conversation.conversation_expire_hours')));
                        })
                        ->latest()
                        ->first();

                    $isNewConversation = false;
                    if (
                        ! $conversation ||
                        $conversation->end_at ||
                        $conversation->created_at < now()->subHours(config('services.conversation.conversation_expire_hours'))
                    ) {
                        $conversation = Conversation::create([
                            'customer_id' => $customer->id,
                            'platform' => $platformName,
                            'trace_id' => 'mail-'.now()->format('YmdHis').'-'.uniqid(),
                        ]);
                        $isNewConversation = true;
                    }
                    // 3️⃣ Store the incoming message
                    $message = Message::create([
                        'conversation_id' => $conversation->id,
                        'platform_id' => $platformId,
                        'sender_id' => $customer->id,
                        'sender_type' => Customer::class,
                        // 'customer_id' => $existingCustomer ? $existingCustomer->id : null,
                        'platform_message_id' => $uid,
                        'subject' => $subject,
                        'content' => $textBody,
                        'html_content' => $htmlBody,
                        'direction' => 'incoming',
                        'status' => 'received',
                        'receiver_type' => User::class,
                        'receiver_id' => $conversation->agent_id ?? null,
                        // 'attachments' => json_encode($attachmentsArr),
                        // 'message_id' => $message->getMessageId(),
                    ]);
                    // Log::info('Stored email ID: '.$emailRecord->id);
                    // Log::info('Processed email from: '.$fromMail.' subject: '.$subject);
                });

                // $emailData = [
                //     'message_id' => (string) $message->getMessageId(),
                //     'from' => $fromMail,
                // 'subject' => (string) $message->getSubject(),
                // 'text_body' => (string) $message->getTextBody(),
                // 'html_body' => (string) $message->getHTMLBody(),
                //     'attachments' => [],
                // ];

                $payload = [
                    'source' => 'email',
                    'traceId' => $conversation->trace_id,
                    'conversationId' => $conversation->id,
                    // 'conversationType' => $isNewConversation ? 'new' : 'old',
                    'sender' => $senderId,
                    'api_key' => config('dispatcher.messenger_api_key'),
                    'timestamp' => $timestamp,
                    'subject' => (string) $message->getSubject(),
                    'text_body' => (string) $message->getTextBody(),
                    'html_body' => (string) $message->getHTMLBody(),
                    // 'attachments' => $mediaPaths,
                    // 'messageId' => $message->id,
                    // 'parentMessageId' => $parentMessageId,
                ];

                // \Log::info('📥 New unread email fetched', [
                //     'message_id' => $emailData['message_id'],
                //     'from' => $emailData['from'],
                //     'subject' => $emailData['subject'],
                //     'text_body' => $emailData['text_body'],
                //     'html_body' => $emailData['html_body'],
                // ]);

                // ProcessEmailBatch::dispatch($platform, $emailData);

                // $message->setFlag('Seen');

                DB::afterCommit(function () use ($payload) {
                    ProcessEmailBatch::dispatch($payload)->onQueue('dispatcher');
                });
            }

        } catch (\Throwable $e) {
            \Log::error('IMAP Read error: '.$e->getMessage());
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
