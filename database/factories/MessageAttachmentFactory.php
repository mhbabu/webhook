<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\MessageAttachment;
use App\Models\Message;

class MessageAttachmentFactory extends Factory
{
    protected $model = MessageAttachment::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['image', 'video', 'audio', 'file']);

        $data = $this->getRealAttachmentData($type);

        return [
            'message_id' => Message::inRandomOrder()->first()?->id ?? Message::factory(),
            'type' => $type,
            'path' => $data['url'],
            'mime' => $data['mime'],
            'size' => $data['size'], // You may convert to bytes if needed
            'attachment_id' => $this->faker->uuid(),
            'is_available' => $this->faker->boolean(90),
        ];
    }

    protected function getRealAttachmentData(string $type): array
    {
        $attachments = [
            'image' => [
                [
                    'url' => "https://images.pexels.com/photos/814499/pexels-photo-814499.jpeg?auto=compress&cs=tinysrgb&w=800",
                    'mime' => 'image/jpeg',
                    'size' => '2.3 MB',
                ],
                [
                    'url' => "https://images.pexels.com/photos/1486222/pexels-photo-1486222.jpeg?auto=compress&cs=tinysrgb&w=800",
                    'mime' => 'image/jpeg',
                    'size' => '1.8 MB',
                ],
                [
                    'url' => "https://images.pexels.com/photos/1109197/pexels-photo-1109197.jpeg?auto=compress&cs=tinysrgb&w=800",
                    'mime' => 'image/jpeg',
                    'size' => '1.5 MB',
                ],
                [
                    'url' => "https://images.pexels.com/photos/546819/pexels-photo-546819.jpeg?auto=compress&cs=tinysrgb&w=800",
                    'mime' => 'image/jpeg',
                    'size' => '2.1 MB',
                ],
            ],
            'video' => [
                [
                    'url' => "https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4",
                    'mime' => 'video/mp4',
                    'size' => '1.0 MB',
                ],
                [
                    'url' => "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4",
                    'mime' => 'video/mp4',
                    'size' => '5.3 MB',
                ],
            ],
            'audio' => [
                [
                    'url' => "https://www.soundjay.com/misc/sounds/bell-ringing-05.mp3",
                    'mime' => 'audio/mpeg',
                    'size' => '0.5 MB',
                ],
                [
                    'url' => "https://file-examples.com/storage/fef1f2c4b8e3c86a70e75c2/2017/11/file_example_MP3_700KB.mp3",
                    'mime' => 'audio/mpeg',
                    'size' => '0.7 MB',
                ],
            ],
            'file' => [
                [
                    'url' => "https://file-examples.com/wp-content/uploads/2017/10/file-sample_150kB.pdf",
                    'mime' => 'application/pdf',
                    'size' => '245 KB',
                ],
                [
                    'url' => "https://file-examples.com/wp-content/uploads/2017/08/file_example_PPT_1MB.ppt",
                    'mime' => 'application/vnd.ms-powerpoint',
                    'size' => '1.2 MB',
                ],
                [
                    'url' => "https://file-examples.com/wp-content/uploads/2017/02/file_example_XLS_10.xls",
                    'mime' => 'application/vnd.ms-excel',
                    'size' => '89 KB',
                ],
            ],
        ];

        return $this->faker->randomElement($attachments[$type]);
    }
}
