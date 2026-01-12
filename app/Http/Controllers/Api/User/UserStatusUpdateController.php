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
    /**
     * Update user status and keep a tracking record.
     */
    public function updateUserStatus(StatusUpdateRequest $request)
    {
        $user   = Auth::user();
        $status = UserStatus::tryFrom($request->status)?->value;

        if (! $status) {
            return jsonResponse('Invalid status value.', false, null, 400);
        }

        $activeConversations = getAgentActiveConversationsCount($user->id);

        /**
         * Same status guard
         */
        if ($user->current_status === $status) {
            if ($status === UserStatus::BREAK_REQUEST->value && $user->userStatusInfo?->break_request_status === 'PENDING') {
                return jsonResponse('Your break request is already pending.', false, null, 400);
            }
            return jsonResponse("You are already in {$status} status.", false, null, 400);
        }

        /**
         * AVAILABLE (always allowed)
         */
        if ($status === UserStatus::AVAILABLE->value) {
            $this->saveStatus($user, $status);
        }

        /**
         * BREAK (blocked if active conversations exist)
         */
        elseif ($status === UserStatus::BREAK->value) {

            if ($activeConversations > 0) {
                return jsonResponse("Resolve {$activeConversations} active conversations before taking a break.", false, null, 400);
            }

            $this->saveStatus($user, $status);
        }

        /**
         * BREAK REQUEST (allowed only if conversations exist)
         */
        elseif ($status === UserStatus::BREAK_REQUEST->value) {

            if ($activeConversations === 0) {
                return jsonResponse('You have no active conversations. You can directly take a break.', false,  null, 400);
            }

            if ($user->userStatusInfo?->break_request_status === 'PENDING') {
                return jsonResponse('Your break request is already pending.', false, null, 400);
            }

            $this->saveStatus($user, $status, ['reason' => $request->reason, 'request_at' => now()]);
        }

        /**
         * OFFLINE (same logic as logout)
         */
        elseif ($status === UserStatus::OFFLINE->value) {

            if ($activeConversations > 0) {
                return jsonResponse("Resolve {$activeConversations} active conversations before going offline.", false, null, 400);
            }

            $this->saveStatus($user, $status);
            Redis::del("agent:{$user->id}");
        } else {
            return jsonResponse('Invalid status update request.', false, null, 400);
        }

        // --- Redis Sync ---
        $this->updateUserInRedis($user->fresh());

        return jsonResponse('Status updated successfully.', true, new UserResource($user->fresh()), 200);
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
            'status'      => UserStatus::BREAK->value,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'changed_at'  => now(),
        ]);

        // Update main user status
        $statusUpdate->user->update(['status' => UserStatus::BREAK->value]);

        // --- Redis Update ---
        $this->updateUserInRedis($statusUpdate->user);

        return jsonResponse('Break approved successfully', true, new UserResource($statusUpdate->user), 200);
    }

    /**
     * Save status both in users table and tracking table
     */
    public function saveStatus($user, $status)
    {
        $user->update(['current_status' => $status]);

        return UserStatusUpdate::create([
            'user_id'    => $user->id,
            'status'     => $status,
            'break_request_status' => $status === UserStatus::BREAK_REQUEST->value ? 'PENDING' : null,
            'changed_at' => now()
        ]);
    }

    public function getStatuses()
    {
        $statuses = array_filter(UserStatus::cases(), function ($status) {
            return !in_array($status->value, ['OFFLINE', 'OCCUPIED']);
        });

        $statuses = array_map(function ($status) {
            return [
                'key'   => $status->value,
                'value' => $status->value,
            ];
        }, $statuses);

        return jsonResponse('User status history fetched successfully', true, $statuses, 200);
    }

    /**
     * Update or add user data in Redis
     */
    private function updateUserInRedis($user)
    {
        $redisKey = "agent:{$user->id}";

        // Prepare agent data in the new pattern
        $agentData = [
            "AGENT_ID"         => $user->id,
            "AGENT_TYPE"       => $user->agent_type ?? 'NORMAL', // Default to NORMAL if not set
            "STATUS"           => $user->current_status,
            "MAX_SCOPE"        => $user->max_limit,
            "AVAILABLE_SCOPE"  => $user->current_limit,
            "CONTACT_TYPE"     => json_encode($user->contact_type ?? []),
            "SKILL"            => json_encode($user->platforms()->pluck('name')->map(fn($name) => strtolower($name))->toArray()),
            "BUSYSINCE"        => optional($user->changed_at)->format('Y-m-d H:i:s') ?? '',
        ];

        // Save as Redis Hash (one key per agent)
        Redis::hMSet($redisKey, $agentData);
    }
}
