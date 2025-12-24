<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class FacebookPostMockController extends Controller
{
    public function index()
    {
        return jsonResponse('Facebook posts data retrieved successfully', true, $this->getPostsData());
    }

    public function socialPageConversations()
    {
        $conversations = [
            // Conversation 1: Comment on Post 1
            [
                "id" => 1,
                "post_id" => 101,
                "platform" => "facebook",
                "trace_id" => "C-101a",
                "customer" => [
                    "id" => 1,
                    "name" => "Rumana Begum",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "comment",
                "info" => "Rumana commented on post 101: 'Great workshop, learned a lot!'",
                "started_at" => "2025-12-18T01:28:09.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],

            // Conversation 2: Reply to Comment on Post 1
            [
                "id" => 2,
                "post_id" => 101,
                "platform" => "facebook",
                "trace_id" => "C-101b",
                "customer" => [
                    "id" => 2,
                    "name" => "Tarek Hassan",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "reply",
                "info" => "Tarek replied on post 101: 'Totally agree, it was very insightful!'",
                "started_at" => "2025-12-18T02:15:09.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],

            // Conversation 3: Comment on Post 2
            [
                "id" => 3,
                "post_id" => 102,
                "platform" => "facebook",
                "trace_id" => "C-102a",
                "customer" => [
                    "id" => 3,
                    "name" => "Rafi Ahmed",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "comment",
                "info" => "Rafi commented on post 102: 'Congratulations! Great initiative.'",
                "started_at" => "2025-12-18T03:05:00.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],

            // Conversation 4: Reply to Comment on Post 3
            [
                "id" => 4,
                "post_id" => 103,
                "platform" => "facebook",
                "trace_id" => "C-103a",
                "customer" => [
                    "id" => 4,
                    "name" => "Nusrat Jahan",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "reply",
                "info" => "Nusrat replied on post 103: 'Loved the practical approach, very helpful!'",
                "started_at" => "2025-12-18T04:10:00.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ],

            // Conversation 5: Comment on Post 4
            [
                "id" => 5,
                "post_id" => 104,
                "platform" => "facebook",
                "trace_id" => "C-104a",
                "customer" => [
                    "id" => 5,
                    "name" => "Hasan Mahmud",
                    "email" => null,
                    "phone" => "",
                    "type" => "customer",
                    "profile_photo" => null
                ],
                "type" => "comment",
                "info" => "Hasan commented on post 104: 'Next workshop will be even better!'",
                "started_at" => "2025-12-18T04:15:00.000000Z",
                "end_at" => null,
                "wrap_up_info" => null,
                "is_ended" => false
            ]
        ];

        return jsonResponse('Social page conversations retrieved successfully', true, ['conversations' => $conversations]);
    }


    /**
     * Return details of a single post by post ID
     */
    public function details($postId)
    {
        $posts = $this->getPostsData()['posts'];

        foreach ($posts as $post) {
            if ($post['conversation_id'] == $postId) {
                return jsonResponse('Post details retrieved successfully', true, $post);
            }
        }
    }

    public function getPostsData(): array
    {
        return [
            "posts" => [

                // POST 1
                [
                    "id" => 101,
                    "conversation_id" => 1,
                    "content" => "Alhamdulillah! Successfully completed our Flutter workshop today. Proud of the team and students.",
                    "created_time" => "2025-12-09T08:00:00Z",
                    "from" => ["id" => 11, "name" => "Mahadi Hassan"],
                    "attachments" => [
                        ["type" => "image", "url" => "https://picsum.photos/id/101/600/400"],
                        ["type" => "image", "url" => "https://picsum.photos/id/102/600/400"]
                    ],
                    "comments" => [
                        [
                            "id" => 101,
                            "content" => "Congratulations bhai! Great initiative for Bangladeshi developers.",
                            "created_time" => "2025-12-09T08:05:00Z",
                            "from" => ["id" => 201, "name" => "Rafi Ahmed"],
                            "replies" => [
                                [
                                    "id" => 1011,
                                    "content" => "Thanks Rafi bhai! Your support means a lot.",
                                    "created_time" => "2025-12-09T08:06:00Z",
                                    "from" => ["id" => 11, "name" => "Mahadi Hassan"]
                                ],
                                [
                                    "id" => 1012,
                                    "content" => "InshaAllah more sessions coming soon for everyone.",
                                    "created_time" => "2025-12-09T08:07:00Z",
                                    "from" => ["id" => 12, "name" => "Hasan Mahmud"]
                                ]
                            ]
                        ],
                        [
                            "id" => 102,
                            "content" => "Loved the practical approach, very helpful for beginners.",
                            "created_time" => "2025-12-09T08:10:00Z",
                            "from" => ["id" => 202, "name" => "Nusrat Jahan"],
                            "replies" => [
                                [
                                    "id" => 1021,
                                    "content" => "Glad you found it useful, Nusrat apu!",
                                    "created_time" => "2025-12-09T08:11:00Z",
                                    "from" => ["id" => 11, "name" => "Mahadi Hassan"]
                                ],
                                [
                                    "id" => 1022,
                                    "content" => "We’ll keep organizing more practical workshops soon.",
                                    "created_time" => "2025-12-09T08:12:00Z",
                                    "from" => ["id" => 13, "name" => "Saiful Islam"]
                                ]
                            ]
                        ]
                    ]
                ],

                // POST 2
                [
                    "id" => 102,
                    "conversation_id" => 2,
                    "content" => "Team outing after a long sprint. Work-life balance matters!",
                    "created_time" => "2025-12-08T18:00:00Z",
                    "from" => ["id" => 12, "name" => "Hasan Mahmud"],
                    "attachments" => [
                        ["type" => "image", "url" => "https://picsum.photos/id/103/600/400"],
                        ["type" => "image", "url" => "https://picsum.photos/id/104/600/400"],
                        ["type" => "image", "url" => "https://picsum.photos/id/105/600/400"]
                    ],
                    "comments" => [
                        [
                            "id" => 201,
                            "content" => "Much needed break! Great to see you all enjoying.",
                            "created_time" => "2025-12-08T18:05:00Z",
                            "from" => ["id" => 204, "name" => "Shakil Khan"],
                            "replies" => [
                                [
                                    "id" => 2011,
                                    "content" => "Absolutely! We deserved this after a hard sprint.",
                                    "created_time" => "2025-12-08T18:06:00Z",
                                    "from" => ["id" => 12, "name" => "Hasan Mahmud"]
                                ],
                                [
                                    "id" => 2012,
                                    "content" => "Fun times also boost productivity!",
                                    "created_time" => "2025-12-08T18:07:00Z",
                                    "from" => ["id" => 208, "name" => "Ruhul Amin"]
                                ]
                            ]
                        ],
                        [
                            "id" => 202,
                            "content" => "I wish I could join next time!",
                            "created_time" => "2025-12-08T18:10:00Z",
                            "from" => ["id" => 205, "name" => "Sumaiya Khanam"],
                            "replies" => [
                                [
                                    "id" => 2021,
                                    "content" => "Next outing, you’ll definitely join us!",
                                    "created_time" => "2025-12-08T18:11:00Z",
                                    "from" => ["id" => 12, "name" => "Hasan Mahmud"]
                                ],
                                [
                                    "id" => 2022,
                                    "content" => "We missed you this time, but see you soon!",
                                    "created_time" => "2025-12-08T18:12:00Z",
                                    "from" => ["id" => 11, "name" => "Rafi Ahmed"]
                                ]
                            ]
                        ]
                    ]
                ],

                // POST 3
                [
                    "id" => 103,
                    "conversation_id" => 3,
                    "content" => "Celebrating International Mother Language Day with our team. Language is identity.",
                    "created_time" => "2025-02-21T09:00:00Z",
                    "from" => ["id" => 13, "name" => "Farzana Akter"],
                    "attachments" => [
                        ["type" => "image", "url" => "https://picsum.photos/id/106/600/400"],
                        ["type" => "image", "url" => "https://picsum.photos/id/107/600/400"]
                    ],
                    "comments" => [
                        [
                            "id" => 301,
                            "content" => "Beautiful initiative! Proud of our language.",
                            "created_time" => "2025-02-21T09:05:00Z",
                            "from" => ["id" => 207, "name" => "Sadia Islam"],
                            "replies" => [
                                [
                                    "id" => 3011,
                                    "content" => "Thank you! Glad you support the cause.",
                                    "created_time" => "2025-02-21T09:06:00Z",
                                    "from" => ["id" => 13, "name" => "Farzana Akter"]
                                ],
                                [
                                    "id" => 3012,
                                    "content" => "Language is our identity, we should protect it.",
                                    "created_time" => "2025-02-21T09:07:00Z",
                                    "from" => ["id" => 11, "name" => "Mahadi Hassan"]
                                ]
                            ]
                        ],
                        [
                            "id" => 302,
                            "content" => "Glad to see team participation.",
                            "created_time" => "2025-02-21T09:10:00Z",
                            "from" => ["id" => 208, "name" => "Ruhul Amin"],
                            "replies" => [
                                [
                                    "id" => 3021,
                                    "content" => "Yes, it’s important to celebrate our roots.",
                                    "created_time" => "2025-02-21T09:11:00Z",
                                    "from" => ["id" => 13, "name" => "Farzana Akter"]
                                ],
                                [
                                    "id" => 3022,
                                    "content" => "Hope next year more people join.",
                                    "created_time" => "2025-02-21T09:12:00Z",
                                    "from" => ["id" => 12, "name" => "Hasan Mahmud"]
                                ]
                            ]
                        ]
                    ]
                ],

                // POST 4
                [
                    "id" => 104,
                    "conversation_id" => 4,
                    "content" => "Distributed food and clothes to underprivileged families in Dhaka today. Small gestures create big smiles.",
                    "created_time" => "2025-12-10T12:00:00Z",
                    "from" => ["id" => 14, "name" => "Rafi Ahmed"],
                    "attachments" => [
                        ["type" => "image", "url" => "https://picsum.photos/id/108/600/400"],
                        ["type" => "image", "url" => "https://picsum.photos/id/109/600/400"]
                    ],
                    "comments" => [
                        [
                            "id" => 401,
                            "content" => "MashAllah, very inspiring work!",
                            "created_time" => "2025-12-10T12:05:00Z",
                            "from" => ["id" => 210, "name" => "Sakib Hasan"],
                            "replies" => [
                                [
                                    "id" => 4011,
                                    "content" => "Alhamdulillah! Small gestures matter a lot.",
                                    "created_time" => "2025-12-10T12:06:00Z",
                                    "from" => ["id" => 14, "name" => "Rafi Ahmed"]
                                ],
                                [
                                    "id" => 4012,
                                    "content" => "Thanks Sakib bhai for your encouragement.",
                                    "created_time" => "2025-12-10T12:07:00Z",
                                    "from" => ["id" => 13, "name" => "Farzana Akter"]
                                ]
                            ]
                        ],
                        [
                            "id" => 402,
                            "content" => "Feeling motivated to help others too!",
                            "created_time" => "2025-12-10T12:10:00Z",
                            "from" => ["id" => 211, "name" => "Nusrat Jahan"],
                            "replies" => [
                                [
                                    "id" => 4021,
                                    "content" => "Every little help counts. Join us next time!",
                                    "created_time" => "2025-12-10T12:11:00Z",
                                    "from" => ["id" => 14, "name" => "Rafi Ahmed"]
                                ],
                                [
                                    "id" => 4022,
                                    "content" => "Your support is appreciated, Nusrat apu.",
                                    "created_time" => "2025-12-10T12:12:00Z",
                                    "from" => ["id" => 11, "name" => "Mahadi Hassan"]
                                ]
                            ]
                        ]
                    ]
                ],

                // POST 5
                [
                    "id" => 105,
                    "conversation_id" => 5,
                    "content" => "Celebrated Eid with friends and family. Moments like these make life beautiful.",
                    "created_time" => "2025-04-21T10:00:00Z",
                    "from" => ["id" => 15, "name" => "Tanjil Rahman"],
                    "attachments" => [
                        ["type" => "image", "url" => "https://picsum.photos/id/110/600/400"],
                        ["type" => "image", "url" => "https://picsum.photos/id/111/600/400"],
                        ["type" => "image", "url" => "https://picsum.photos/id/112/600/400"]
                    ],
                    "comments" => [
                        [
                            "id" => 501,
                            "content" => "Happy Eid to everyone!",
                            "created_time" => "2025-04-21T10:05:00Z",
                            "from" => ["id" => 213, "name" => "Mahadi Hassan"],
                            "replies" => [
                                [
                                    "id" => 5011,
                                    "content" => "Thanks bhai! Eid Mubarak 😊",
                                    "created_time" => "2025-04-21T10:06:00Z",
                                    "from" => ["id" => 15, "name" => "Tanjil Rahman"]
                                ],
                                [
                                    "id" => 5012,
                                    "content" => "Wishing you and your family happiness.",
                                    "created_time" => "2025-04-21T10:07:00Z",
                                    "from" => ["id" => 13, "name" => "Farzana Akter"]
                                ]
                            ]
                        ],
                        [
                            "id" => 502,
                            "content" => "Beautiful moments captured!",
                            "created_time" => "2025-04-21T10:10:00Z",
                            "from" => ["id" => 214, "name" => "Sadia Islam"],
                            "replies" => [
                                [
                                    "id" => 5021,
                                    "content" => "Thank you Sadia! Glad you liked them.",
                                    "created_time" => "2025-04-21T10:11:00Z",
                                    "from" => ["id" => 15, "name" => "Tanjil Rahman"]
                                ],
                                [
                                    "id" => 5022,
                                    "content" => "Hope you had a wonderful Eid too!",
                                    "created_time" => "2025-04-21T10:12:00Z",
                                    "from" => ["id" => 11, "name" => "Mahadi Hassan"]
                                ]
                            ]
                        ]
                    ]
                ]

            ]
        ];
    }
}
