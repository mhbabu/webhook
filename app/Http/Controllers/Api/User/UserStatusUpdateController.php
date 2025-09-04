<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Status\StatusUpdateRequest;
use App\Http\Resources\User\UserResource;
use App\Models\UserStatusUpdate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class UserStatusUpdateController extends Controller
{
    /**
     * Update user status and keep a tracking record.
     */
    public function updateUserStatus(StatusUpdateRequest $request)
    {
        $user   = Auth::user();
        $status = UserStatus::from($request->status);

        if ($status === UserStatus::AVAILABLE) {
            $this->saveStatus($user, $status);
        } elseif ($status === UserStatus::BREAK_REQUEST) {
            if (empty($request->reason)) return jsonResponse('Reason is required for break request', false, null, 422);

            $this->saveStatus($user, $status, ['reason' => $request->reason, 'request_at' => now()]);
        } elseif ($status === UserStatus::OFFLINE || $status === UserStatus::BREAK) {
            if ($user->limit !== 0) return jsonResponse('You cannot switch to this status unless your current limit is zero (0)', false, null, 403);

            $this->saveStatus($user, $status);
        } else {
            return jsonResponse('Invalid status update request', false, null, 400);
        }

        // --- Redis Update ---
        $this->updateUserInRedis($user);

        return jsonResponse('Status updated successfully', true, new UserResource($user), 200);
    }

    /**
     * Approve a break request and sync with user table
     */
    public function approve($id)
    {
        $statusUpdate = UserStatusUpdate::findOrFail($id);

        if ($statusUpdate->status !== UserStatus::BREAK_REQUEST) {
            return jsonResponse('This request is not a break request', false, null, 400);
        }

        // Update tracking table
        $statusUpdate->update([
            'status'      => UserStatus::BREAK,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'changed_at'  => now(),
        ]);

        // Update main user status
        $statusUpdate->user->update(['status' => UserStatus::BREAK]);

        // --- Redis Update ---
        $this->updateUserInRedis($statusUpdate->user);

        return jsonResponse('Break approved successfully', true, new UserResource($statusUpdate->user), 200);
    }

    /**
     * Save status both in users table and tracking table
     */
    private function saveStatus($user, UserStatus $status, array $extra = [])
    {
        $user->update(['status' => $status, 'changed_at' => now()]);

        return UserStatusUpdate::create(array_merge([
            'user_id'    => $user->id,
            'status'     => $status,
            'changed_at' => now()
        ], $extra));
    }

    /**
     * Update or add user data in Redis
     */
    private function updateUserInRedis($user)
    {
        $redisKey = 'omnitrix_agents_list';

        // Get existing agents from Redis
        $existing = Redis::get($redisKey);
        $agents = $existing ? json_decode($existing, true) : [];

        // Prepare user data for Redis
        $userData = [
            "AGENT_ID"         => (string) $user->id,
            "Status"           => $user->status,
            "AVAILABLE_SCOPE"  => $user->max_limit,
            "CURRENT_CONTACTS" => $user->current_limit ?? 0,
            // "CONTACT_TYPE"     => $user->contact_type ?? [], // If multiple, make it array
            "SKILL"            => $user->platforms()->pluck('name')->toArray(), // Fetch all platforms
            // "BUSYSINCE"        => $user->changed_at->format('Y-m-d H:i:s'),
        ];

        // Update existing agent or add new
        $found = false;
        foreach ($agents as &$agent) {
            if ($agent['AGENT_ID'] === $userData['AGENT_ID']) {
                $agent = $userData;
                $found = true;
                break;
            }
        }
        if (!$found) $agents[] = $userData;

        // Save back to Redis
        Redis::set($redisKey, json_encode($agents));
    }
}
