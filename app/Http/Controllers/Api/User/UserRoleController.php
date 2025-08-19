<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\User\UserRoleResource;
use App\Models\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function index()
    {
        $userRole     = auth()->user()->role;
        $childRoleIds =  RoleHierarchy::where('parent_role_id', $userRole->id)->pluck('child_role_id') ?? [];
        $roles        = Role::whereIn('id', $childRoleIds)->get();
        return jsonResponse('Roles retrieved successfully', true, UserRoleResource::collection($roles));
    }

    public function store(StoreRoleRequest $request)
    {
        DB::beginTransaction();  // Start the transaction

        try {
            // Validate and process the data
            $data = $request->validated();

            // Create the role
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'sanctum']);

            // If there are child roles, prepare data for insertion
            if (!empty($data['role_ids'])) {
                $roleHierarchyData = [];
                foreach ($data['role_ids'] as $childRoleId) {
                    $roleHierarchyData[] = [
                        'parent_role_id' => $role->id,   // Link the child to the parent role
                        'child_role_id'  => $childRoleId,  // Assign the child role
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                // Bulk insert child roles
                RoleHierarchy::insert($roleHierarchyData);
            }

            DB::commit(); // Commit the transaction

            // Return success response
            return jsonResponse('Role created successfully', true, new UserRoleResource($role));
        } catch (\Exception $e) {
            DB::rollBack();  // Rollback the transaction in case of error
            return jsonResponse('Failed to create role. Please try again later.', false, $e->getMessage());
        }
    }


    public function show(Role $role)
    {
        if (!$role) {
            return jsonResponse('Role not found', false);
        }

        return jsonResponse('Role retrieved successfully', true, new UserRoleResource($role));
    }

    public function update(UpdateRoleRequest $request, $roleId)
    {
        $role = Role::findOrFail($roleId);
        if (!$role) {
            return jsonResponse('Role not found', false);
        }

        DB::beginTransaction();

        try {
            $data = $request->validated();
            $role->update(['name' => $data['name'], 'status' => $data['status']]);

            // If there are child roles, prepare data for insertion
            if (!empty($data['role_ids'])) {
                RoleHierarchy::where('parent_role_id', $role->id)->delete(); // Clear existing child roles
                $roleHierarchyData = [];
                foreach ($data['role_ids'] as $childRoleId) {
                    $roleHierarchyData[] = [
                        'parent_role_id' => $role->id,   // Link the child to the parent role
                        'child_role_id'  => $childRoleId,  // Assign the child role
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                // Bulk insert child roles
                RoleHierarchy::insert($roleHierarchyData);
            }

            DB::commit(); // Commit the transaction

            // $role->load()
            // Return success response
            return jsonResponse('Role updated successfully', true, new UserRoleResource($role));
        } catch (\Exception $e) {
            DB::rollBack();  // Rollback the transaction in case of error
            return jsonResponse('Failed to update role. Please try again later.', false, $e->getMessage());
        }
    }

    public function destroy(Role $role)
    {
        if(empty($role)) {
            return jsonResponse('Role not found', false);
        }

        DB::beginTransaction();

        try {
            RoleHierarchy::where('parent_role_id', $role->id)->delete();
            $role->delete();
            DB::commit();
            return jsonResponse('Role deleted successfully', true);
        } catch (\Exception $e) {
            DB::rollBack();
            return jsonResponse('Failed to delete role. Please try again later.', false, $e->getMessage());
        }
    }
}
