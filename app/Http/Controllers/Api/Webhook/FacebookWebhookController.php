<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Platforms\FacebookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FacebookWebhookController extends Controller
{
    protected $facebookService;

    public function __construct(FacebookService $facebookService)
    {
        // $this->facebookService = $facebookService;
    }

    /**
     * Verify webhook token when Facebook sends GET request
     */
    public function verifyFacebookPage(Request $request)
    {
        $verify_token = env('FACEBOOK_VERIFY_TOKEN');

        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === $verify_token) {
                Log::info('✅ Facebook webhook verified successfully.');

                return response($challenge, 200);
            } else {
                Log::warning('❌ Facebook webhook verification failed.');

                return response('Forbidden', 403);
            }
        }

        return response('Bad Request', 400);
    }

    /**
     * Receive webhook POST events from Facebook
     */
    // public function receiveFacebookPage(Request $request)
    public function receiveFacebookPageEventData(Request $request)
    {
        // 1️⃣ Log raw payload
        $rawPayload = $request->getContent();
        Log::info('📩 Facebook Webhook Payload: '.$rawPayload);

        // 2️⃣ Decode safely
        $data = json_decode($rawPayload, true);

        // 3️⃣ Validate structure
        if (empty($data['entry'])) {
            Log::warning('⚠️ Webhook received with no entries.');

            return response('EVENT_RECEIVED', 200);
        }

        // 4️⃣ Process entries
        foreach ($data['entry'] as $entry) {
            if (empty($entry['changes'])) {
                continue;
            }

            foreach ($entry['changes'] as $change) {
                $field = $change['field'] ?? '';
                $value = $change['value'] ?? [];

                if ($field === 'feed') {
                    $item = $value['item'] ?? '';
                    $verb = $value['verb'] ?? '';

                    switch ($item) {
                        // 📝 New or updated post/status
                        case 'post':
                        case 'status':
                            $action = $verb === 'remove' ? '🗑️ Post Deleted' : '📝 New/Updated Post';
                            Log::info($action.': '.json_encode($value));
                            break;

                            // 💬 New, edited, or deleted comment
                        case 'comment':
                            $action = match ($verb) {
                                'add' => '💬 New Comment',
                                'edited' => '✏️ Comment Edited',
                                'remove' => '🗑️ Comment Deleted',
                                default => '💬 Comment Event',
                            };

                            // Detect comment reply (has parent_id)
                            if (! empty($value['parent_id'])) {
                                $action .= ' (↩️ Reply)';
                            }

                            Log::info($action.': '.json_encode($value));
                            break;

                            // ❤️ Reaction added or removed
                        case 'reaction':
                            $action = $verb === 'remove' ? '💔 Reaction Removed' : '❤️ New Reaction';
                            Log::info($action.': '.json_encode($value));
                            break;

                            // 🔹 Fallback for other feed events
                        default:
                            Log::info('🔹 Other Feed Event: '.json_encode($value));
                            break;
                    }
                }

                // 📣 Page Mention events
                elseif ($field === 'mention') {
                    Log::info('📣 New Mention: '.json_encode($value));
                }

                // 🧩 Other non-feed fields
                else {
                    Log::info('🧩 Other Field ('.$field.'): '.json_encode($value));
                }
            }
        }

        // 5️⃣ Always acknowledge to Facebook
        return response('EVENT_RECEIVED', 200);
    }

    public function postMessage(Request $request)
    {
        // dd('Request received', $request->all());
        $request->validate(['message' => 'required|string']);
        $response = $this->facebookService->postToPage($request->message);

        Log::info('📣 New Post Created: '.json_encode($response));

        return response()->json($response);
    }

    /**
     * Post a message with an image to the Facebook Page.
     */
    public function postWithImage(Request $request)
    {
        // ✅ Validate the input
        $validated = $request->validate([
            'message' => 'required|string',
            'image_url' => 'required|url', // must be a valid public URL
        ]);

        try {
            // ✅ Call the Facebook service
            $response = $this->facebookService->postWithImage(
                $validated['message'],
                $validated['image_url']
            );

            // ✅ Handle success
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);

        } catch (\Exception $e) {
            // ❌ Log and return error
            Log::error('❌ Error posting image to Facebook: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post a comment on a Facebook post
     */
    public function postComment(Request $request)
    {

        $validated = $request->validate([
            'post_id' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $response = $this->facebookService->postComment(
                $validated['message'],
                $validated['post_id']
            );

            Log::info('📣 New Comment Posted: '.json_encode($response));

            return response()->json([
                'success' => true,
                'data' => $response,
            ], 200);

        } catch (\Exception $e) {
            Log::error('❌ Error posting comment: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post a reply to an existing Facebook comment (nested comment)
     */
    public function replyComment(Request $request)
    {
        $validated = $request->validate([
            'comment_id' => 'required|string',  // this time we really mean comment_id
            'message' => 'required|string',
        ]);

        try {
            $response = $this->facebookService->replyToComment(
                $validated['message'],
                $validated['comment_id']
            );
            Log::info('↩️ Reply Posted: '.json_encode($response));

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error replying to comment: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
