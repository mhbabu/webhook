<?php

namespace App\Services\Platforms;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Platform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\IMAP\Facades\Client;

class EmailService
{
    public function sendEmail($to, $subject, $htmlMessage, $attachments = [], $cc = [])
    {
        $response = [];

        Mail::send([], [], function ($mail) use ($to, $subject, $htmlMessage, $attachments, $cc, &$response) {

            $mail->to($to)
                ->subject($subject)
                ->html($htmlMessage);

            if (! empty($cc)) {
                $mail->cc($cc); // <-- CC ARRAY
            }

            if (! empty($attachments)) {
                foreach ($attachments as $filePath) {
                    $absolutePath = storage_path('app/public/'.$filePath);

                    if (file_exists($absolutePath)) {
                        $mail->attach($absolutePath);
                        $response['attached'][] = $filePath;
                    } else {
                        $response['missing'][] = $filePath;
                    }
                }
            }
        });

        return [
            'success' => true,
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'attachments' => $response['attached'] ?? [],
            'missing_files' => $response['missing'] ?? [],
            'sent_at' => now()->toDateTimeString(),
        ];
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
                ->limit(2)
                ->leaveUnread()
                ->fetchOrderDesc()
                ->get();

            // $messages = $inbox->messages()
            //     ->unseen()           // ✅ only unread (unseen) messages
            //     ->limit(2)           // limit to 2 emails
            //     ->leaveUnread()      // ✅ do not mark as seen after fetch
            //     ->fetchOrderDesc()   // get newest first
            //     ->get();

            foreach ($messages as $imapMsg) {
                $messageId = $imapMsg->getMessageId()->toString();

                Log::info('📥 Processing email: ', [
                    'subject' => $imapMsg->getSubject()->toString(),
                    'from' => (string) optional($imapMsg->getFrom()->first())->mail,
                    'from_name' => (string) optional($imapMsg->getFrom()->first())->personal,
                    'to' => implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getTo()->all())),
                    'cc' => implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getCc()->all())),
                    'bcc' => implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getBcc()->all())),
                    // 'date_sent' => $imapMsg->getDate()->toDateTimeString(),
                    'message_id' => $imapMsg->getMessageId()->toString(),
                    'thread_id' => $imapMsg->getThreadId()->toString(),
                    'uid' => $imapMsg->getUid(),
                    // 'flags' => $imapMsg->getFlags()->toString(),
                    'mailbox' => (string) $imapMsg->getMailbox(),
                    'source' => (string) $imapMsg->getSource(),
                    'attachments_count' => count($imapMsg->getAttachments()),
                    'body_length' => strlen((string) $imapMsg->getHTMLBody()),

                    'direction' => 'incoming',
                ]);

                if (Message::where('platform_message_id', $messageId)->exists()) {
                    continue;
                }
                // $uid = $imapMsg->getUid()->toString();
                // $threadId = $imapMsg->getThreadId();
                $fromName = (string) optional($imapMsg->getFrom()->first())->personal;
                $fromMail = (string) optional($imapMsg->getFrom()->first())->mail;
                $toMails = implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getTo()->all()));
                $ccMails = implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getCc()->all()));
                $subject = (string) $imapMsg->getSubject();
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
                $message = DB::transaction(function () use (
                    $platformId, $platformName, $fromMail, $ccMails, $subject, $htmlBody, $messageId, $fromName, &$conversation
                ) {
                    $customer = Customer::firstOrCreate([
                        'email' => $fromMail,
                        'platform_id' => $platformId,
                        'name' => $fromName,
                    ]);

                    $conversation = Conversation::firstOrCreate(
                        [
                            'customer_id' => $customer->id,
                            'platform' => $platformName,
                        ],
                        [
                            'trace_id' => 'mail-'.now()->format('YmdHis').'-'.uniqid(),
                        ]
                    );

                    return Message::create([
                        'conversation_id' => $conversation->id,
                        'platform_id' => $platformId,
                        'sender_id' => $customer->id,
                        'sender_type' => Customer::class,
                        'cc_email' => $ccMails,
                        'type' => 'text',
                        'platform_message_id' => $messageId,
                        'subject' => $subject,
                        'content' => $htmlBody,
                        'direction' => 'incoming',
                    ]);
                });

                // payload unified
                $payload = [
                    'source' => 'email',
                    'traceId' => $conversation->trace_id,
                    'conversationId' => $conversation->id,
                    // 'conversationType' => $isNewConversation ? 'new' : 'old',
                    'conversationType' => 'new',
                    'api_key' => config('dispatcher.email_api_key'),
                    'timestamp' => $timestamp,
                    'senderName' => $fromName,
                    'sender' => $fromMail,
                    'cc' => $ccMails,
                    'subject' => $subject,
                    'html_body' => $htmlBody,
                    'attachments' => $attachmentsArr,
                    'messageId' => $message->id,
                ];

                // $payload = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                Log::info('📥 New email received', ['payload' => $payload]);

                DB::afterCommit(function () use ($payload) {
                    Log::info('✅ Dispatching email Payload After Commit', ['payload' => $payload]);
                    $this->sendToDispatcher($payload);
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
