<?php

namespace App\Services\Platforms;

use App\Jobs\ProcessEmailBatch;
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
        // $platform = 'email';
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
                $toMails = [];
                foreach ($message->getTo() as $to) {
                    $toMails[] = $to->mail;
                }

                $emailData = [
                    'message_id' => $message->getMessageId(),
                    'from' => $message->getFrom()[0]->mail ?? null,
                    'to' => implode(',', $toMails),
                    'subject' => $message->getSubject(),
                    'text_body' => $message->getTextBody(),
                    'html_body' => $message->getHTMLBody(),
                    'attachments' => [],
                ];

                foreach ($message->getAttachments() as $att) {
                    $emailData['attachments'][] = [
                        'name' => $att->getName(),
                        'content' => $att->getContent(),
                    ];
                }

                \Log::info('📥 New unread email fetched', [
                    'message_id' => $emailData['message_id'],
                    'from' => $emailData['from'],
                    'subject' => $emailData['subject'],
                ]);

                ProcessEmailBatch::dispatch($platform, $emailData);

                $message->setFlag('Seen');
            }

        } catch (\Throwable $e) {
            \Log::error('IMAP Read error: '.$e->getMessage());
        }

        $client->disconnect();

        return true;
    }
}
