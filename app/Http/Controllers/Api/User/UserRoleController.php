<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\User\UserRoleResource;
use App\Models\RoleHierarchy;
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
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // Create the role
            $role = Role::create([
                'name'       => $data['name'],
                'guard_name' => 'sanctum',
            ]);

            $roleHierarchyData = [];

            // 1️⃣ Assign child roles provided by request
            if (!empty($data['role_ids'])) {
                foreach (array_unique($data['role_ids']) as $childRoleId) {
                    $roleHierarchyData[] = [
                        'parent_role_id' => $role->id,
                        'child_role_id'  => $childRoleId,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }
            }

            // 2️⃣ Assign this new role under its parent(s) automatically
            $currentUserRoleId = auth()->user()->role->id;

            $roleHierarchyData[] = [
                'parent_role_id' => $currentUserRoleId,
                'child_role_id'  => $role->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            // 3️⃣ Optional: assign to all higher-level ancestors (recursive)
            $parentIds = RoleHierarchy::where('child_role_id', $currentUserRoleId)->pluck('parent_role_id');
            foreach ($parentIds as $parentId) {
                $roleHierarchyData[] = [
                    'parent_role_id' => $parentId,
                    'child_role_id'  => $role->id,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            // Bulk insert all hierarchy relationships
            RoleHierarchy::insert($roleHierarchyData);

            DB::commit();

            return jsonResponse('Role created successfully', true, new UserRoleResource($role));
        } catch (\Exception $e) {
            DB::rollBack();
            return jsonResponse('Failed to create role. Please try again later.', false, $e->getMessage());
        }
    }



    public function show($roleId)
    {
        $role = Role::find($roleId);
        if (!$role) {
            return jsonResponse('Role not found', false);
        }

        return jsonResponse('Role retrieved successfully', true, new UserRoleResource($role));
    }

    public function update(UpdateRoleRequest $request, $roleId)
    {
        $role = Role::find($roleId);
        if (!$role) {
            return jsonResponse('Role not found', false);
        }

        DB::beginTransaction();

        try {
            $data       = $request->validated();
            $role->name = $data['name'];
            $role->save();

            // Update child roles
            if (!empty($data['role_ids'])) {
                // Remove existing hierarchy
                RoleHierarchy::where('parent_role_id', $role->id)->delete();

                // Insert new hierarchy
                $roleHierarchyData = [];
                foreach (array_unique($data['role_ids']) as $childRoleId) {
                    $roleHierarchyData[] = [
                        'parent_role_id' => $role->id,
                        'child_role_id'  => $childRoleId,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }
                if (!empty($roleHierarchyData)) {
                    RoleHierarchy::insert($roleHierarchyData);
                }
            }

            // Clear Spatie cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            DB::commit();
            return jsonResponse('Role updated successfully', true, new UserRoleResource($role));
        } catch (\Exception $e) {
            DB::rollBack();
            return jsonResponse('Failed to update role. Please try again later.', false, $e->getMessage());
        }
    }


    public function destroy($roleId)
    {
        $role = Role::find($roleId);

        if (!$role) {
            return jsonResponse('Role not found', false);
        }

        $role->delete();
        return jsonResponse('Role deleted successfully', true);
    }
}
