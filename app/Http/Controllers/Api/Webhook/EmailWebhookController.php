<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Platform;
use App\Models\User;
use App\Services\Platforms\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webklex\IMAP\Facades\Client;

class EmailWebhookController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function receiveEmailData(Request $request)
    {
        $platform = Platform::whereRaw('LOWER(name) = ?', ['email'])->first();

        if (! $platform) {
            Log::error('❌ Email platform not found in DB');

            return false;
        }

        $platformId = $platform->id;
        $platformName = strtolower($platform->name);

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

            // $messages = $inbox->messages()
            //     ->unseen()           // fetch only unread messages
            //     ->limit(5)           // adjust limit as needed
            //     ->leaveUnread()      // do not mark as seen yet
            //     ->fetchOrderDesc()
            //     ->get();

            $messages = $inbox->messages()
                ->seen()
                ->limit(2)
                ->leaveUnread()
                ->fetchOrderDesc()
                ->get();

            foreach ($messages as $imapMsg) {
                try {
                    $this->processImapMessage($imapMsg, $platformId, $platformName);
                } catch (\Throwable $e) {
                    Log::error('⚠️ Error processing email: '.$e->getMessage(), [
                        'subject' => (string) $imapMsg->getSubject(),
                        'from' => (string) optional($imapMsg->getFrom()->first())->mail,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('IMAP read error: '.$e->getMessage());
        }

        $client->disconnect();

        return true;
    }

    /**
     * Process single IMAP message
     */
    private function processImapMessage($imapMsg, $platformId, $platformName)
    {
        $messageId = $imapMsg->getMessageId()->toString();

        // Skip duplicate messages
        if (Message::where('platform_message_id', $messageId)->exists()) {
            return;
        }

        $from = $imapMsg->getFrom()->first();
        $fromMail = (string) ($from->mail ?? '');
        $fromName = (string) ($from->personal ?? '');
        $toMails = implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getTo()->all()));
        $ccMails = implode(',', array_map(fn ($t) => (string) $t->mail, $imapMsg->getCc()->all()));
        $subject = (string) $imapMsg->getSubject();
        $htmlBody = (string) $imapMsg->getHTMLBody();
        $emailDate = (string) $imapMsg->getDate();
        Log::info('📥 Processing email: ', [
            'subject' => $subject,
            'emailDate' => $emailDate,
        ]);

        DB::transaction(function () use (
            $platformId,
            $platformName,
            $fromMail,
            $fromName,
            $ccMails,
            $subject,
            $htmlBody,
            $messageId,
            $imapMsg,
            &$conversation,
            &$attachmentsArr
        ) {
            // Customer
            $customer = Customer::firstOrCreate(
                ['email' => $fromMail, 'platform_id' => $platformId],
                ['name' => $fromName]
            );

            // Conversation
            // $conversation = Conversation::firstOrCreate(
            //     ['customer_id' => $customer->id, 'platform' => $platformName],
            //     ['trace_id' => 'mail-'.now()->format('YmdHis').'-'.uniqid()]
            // );

            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'platform' => $platformName,
                'trace_id' => 'mail-'.now()->format('YmdHis').'-'.uniqid(),
            ]);

            // Message
            $message = Message::create([
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

            // Attachments
            $attachmentsArr = $this->saveAttachments($imapMsg, $message);

            // Log info
            Log::info('📥 New email processed', [
                'subject' => $subject,
                'from' => $fromMail,
                'attachments_count' => count($attachmentsArr),
            ]);

            // Dispatch payload after commit
            $payload = [
                'source' => 'email',
                'traceId' => $conversation->trace_id,
                'conversationId' => $conversation->id,
                'InteractionType' => 'new',
                'api_key' => config('dispatcher.email_api_key'),
                // 'timestamp' => $timestamp,
                'timestamp' => now()->timestamp,
                'senderName' => $fromName,
                'sender' => $fromMail,
                'cc' => $ccMails,
                'subject' => $subject,
                'html_body' => $htmlBody,
                // 'emailDate' => $emailDate->toDateTimeString(),
                'attachments' => $attachmentsArr,
                'messageId' => $message->id,
            ];

            // Log::info('📤 Dispatching email payload', ['payload' => $payload]);

            DB::afterCommit(function () use ($payload) {
                $this->sendToDispatcher($payload);
            });

            // Mark email as seen
            $imapMsg->setFlag('Seen');
        });
    }

    /**
     * Save email attachments and return array for payload
     */
    private function saveAttachments($imapMsg, Message $message)
    {
        $attachmentsArr = [];

        foreach ($imapMsg->getAttachments() as $att) {

            $originalName = $att->name ?? 'file';
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            // Generate safe unique filename
            // $filename = Str::uuid().'_'.$att->name.'.'.$extension;
            $filename = Str::uuid().'.'.$extension;

            // Storage folder: storage/app/public/mail_attachments/YYYYMMDD/
            $storagePath = 'mail_attachments/'.now()->format('Ymd');
            $fullPath = $storagePath.'/'.$filename;

            // put file using Laravel Storage
            Storage::disk('public')->put($fullPath, $att->content);

            // Correct MIME detection
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                default => 'application/octet-stream',
            };

            $attachmentsArr[] = [
                'message_id' => $message->id,
                'type' => $mime,
                'path' => $fullPath,
                'mime' => $mime,
                'size' => strlen($att->content),
                'attachment_id' => $att->id ?? null,
                'is_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($attachmentsArr)) {
            DB::transaction(function () use ($attachmentsArr) {
                MessageAttachment::insert($attachmentsArr);
            });
        }

        return $attachmentsArr;
    }

    public function download(MessageAttachment $attachment)
    {
        $relativePath = $attachment->path;
        $absolutePath = storage_path('app/public/'.$relativePath);

        if (! file_exists($absolutePath)) {
            return abort(404, 'Attachment file not found.');
        }

        // Use original file name if you want to give user-friendly name
        $filename = basename($absolutePath);

        // Correct MIME type
        $mime = $attachment->mime ?? 'application/octet-stream';

        return response()->download($absolutePath, $filename, [
            'Content-Type' => $mime,
        ]);
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
