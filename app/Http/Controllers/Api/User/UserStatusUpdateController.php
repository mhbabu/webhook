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
        $status = UserStatus::tryFrom($request->status)->value;

        if ($user->current_status === $status) {
            $breakRequest = $status === 'BREAK REQUEST' && $user->userStatusInfo->break_request_status === 'PENDING' ? ' and your request is PENDING.' : '';
            return jsonResponse("You are already in {$status} status" . $breakRequest, false, null, 400);
        }

        if ($status === UserStatus::AVAILABLE->value) {
            $this->saveStatus($user, $status);
        } elseif ($status === UserStatus::BREAK_REQUEST->value) {
            $this->saveStatus($user, $status, ['reason' => $request->reason, 'request_at' => now()]);
        } elseif ($status === UserStatus::OFFLINE->value) {
            if ($user->current_limit !== 0) return jsonResponse('You cannot switch to this status unless your current limit is zero (0)', false, null, 403);

            $this->saveStatus($user, $status);
        } elseif ($user->current_limit === 0 && $status === UserStatus::BREAK->value) {
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
            "AVAILABLE_SCOPE"  => $user->available_limit ?? $user->max_limit,
            "CONTACT_TYPE"     => json_encode($user->contact_type ?? []),
            "SKILL"            => json_encode($user->platforms()->pluck('name')->map(fn($name) => strtolower($name))->toArray()),
            // "BUSYSINCE"        => optional($user->changed_at)->format('Y-m-d H:i:s') ?? '',
        ];

        // Save as Redis Hash (one key per agent)
        Redis::hMSet($redisKey, $agentData);
    }
}
