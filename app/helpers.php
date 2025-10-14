<?php

use App\Enums\PlatformTypeWiseWeightage;
use App\Models\Conversation;

if (!function_exists('jsonResponse')) {
    function jsonResponse(string $message, bool $status, $data = null, int $statusCode = 200)
    {
        return response()->json(['status' => $status, 'message' => $message, 'data' => $data], $statusCode);
    }
}


/**
 * Helper function for returning a standardized paginated response.
 */

if (!function_exists('jsonResponseWithPagination')) {
    function jsonResponseWithPagination(string $message, bool $status, $response, int $statusCode = 200)
    {
        return response()->json(['message' => $message, 'status'  => $status] + $response, $statusCode);
    }
}


if (!function_exists('isRoleCreationAuthorized')) {
    /**
     * Check if the current role is authorized to create (assign) the target role.
     *
     * @param string $currentRole - The role of the currently authenticated user.
     * @param string $targetRole - The role the user wants to create.
     * @return bool - Returns true if the target role is authorized for creation, otherwise false.
     */
    function isRoleCreationAuthorized(string $currentRole, string $targetRole): bool
    {
        // Define the role hierarchy configuration directly here
        $roleHierarchy = [
            'Super Admin' => ['Admin', 'Supervisor', 'Agent'],
            'Admin'       => ['Supervisor', 'Agent'],
            'Supervisor'  => ['Agent'],
            'Agent'       => [],
        ];

        // Check if the current role is authorized to create the target role
        $allowedRoles = $roleHierarchy[$currentRole] ?? [];

        // Return true if the target role is in the allowed roles, otherwise false
        return in_array($targetRole, $allowedRoles);
    }


    if (! function_exists('getPlatformWeight')) {

        /**
         * Get the weightage of a given platform.
         *
         * This function accepts either:
         * - A string representing the platform (e.g., 'whatsapp', 'facebook_messenger')
         * - A PlatformTypeWiseWeightage enum instance
         * - Null (defaults to UnknownSource)
         *
         * The weight indicates how much capacity/limit a conversation on this platform
         * consumes or frees for an agent.
         *
         * @param  string|PlatformTypeWiseWeightage|null  $platform
         * @return int  Returns the weight value (default 1 for unknown platforms)
         */
        function getPlatformWeight(string|PlatformTypeWiseWeightage|null $platform): int
        {
            // If already an enum instance, just return its weight directly
            if ($platform instanceof PlatformTypeWiseWeightage) {
                return $platform->weight();
            }

            // Normalize the input string (trim + lowercase)
            $normalized = strtolower(trim($platform ?? 'unknown_source'));

            // Try to convert the string into a PlatformTypeWiseWeightage enum
            // If not found, fallback to UnknownSource
            $enum = PlatformTypeWiseWeightage::tryFrom($normalized) ?? PlatformTypeWiseWeightage::UnknownSource;

            // Return the weight for this platform
            return $enum->weight();
        }
    }



     if (! function_exists('getAgentActiveConversationsCount')) {

        /**
         * Get the count of active conversations for a specific agent.
         *
         * This function checks the database for active conversations
         * associated with the given agent ID.
         *
         * @param  int  $agentId
         * @return int
         */
        function getAgentActiveConversationsCount(int $agentId): int
        {
            return Conversation::where('agent_id', $agentId)
                ->where(function ($query) {
                    $query->whereNull('end_at')
                        ->orWhere('end_at', '>=', now()->subHours(config('services.conversation_expire_hours')));
                })
                ->count();
        }
    }

}
