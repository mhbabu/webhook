<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Status\StatusUpdateRequest;
use App\Http\Resources\User\UserResource;
use App\Models\UserStatusUpdate;
use Illuminate\Support\Facades\Auth;

class UserStatusUpdateController extends Controller
{
    /**
     * Update user status and keep a tracking record.
     */
    public function updateUserStatus(StatusUpdateRequest $request)
    {
        $user   = Auth::user();
        $status = UserStatus::from($request->status);

        // --- CASE 1: AVAILABLE ---
        if ($status === UserStatus::AVAILABLE) {
            $this->saveStatus($user, $status);
        }

        // --- CASE 2: BREAK REQUEST ---
        elseif ($status === UserStatus::BREAK_REQUEST) {
            if (empty($request->reason)) return jsonResponse('Reason is required for break request', false, null, 422);

            $this->saveStatus($user, $status, ['reason' => $request->reason, 'request_at' => now()]);
        }

        // --- CASE 3: OFFLINE or BREAK ---
        elseif ($status === UserStatus::OFFLINE || $status === UserStatus::BREAK) {
            if ($user->limit !== 0) return jsonResponse('You cannot switch to this status unless your current limit is zero (0)', false, null, 403);

            $this->saveStatus($user, $status);
        } else {
            return jsonResponse('Invalid status update request', false, null, 400);
        }

        // Return the current user resource
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

        return jsonResponse('Break approved successfully', true, new UserResource($statusUpdate->user), 200); // Need to set realtime
    }

    /**
     * Save status both in users table and tracking table
     */
    private function saveStatus($user, UserStatus $status, array $extra = [])
    {
        // Update main user table
        $user->update(['status' => $status, 'changed_at' => now()]);

        // Create tracking record
        return UserStatusUpdate::create(array_merge(['user_id' => $user->id, 'status' => $status, 'changed_at' => now()], $extra));
    }
}
