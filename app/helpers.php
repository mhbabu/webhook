<?php

if (!function_exists('jsonResponse')) {
    function jsonResponse(string $message, bool $status, $data = null, int $statusCode = 200)
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
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
}