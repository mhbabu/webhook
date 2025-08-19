<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends Model
{
    protected $table    = "roles";
    protected $fillable = ['name', 'guard_name'];

    // public function childRoles(): HasMany
    // {
    //     return $this->hasMany(RoleHierarchy::class, 'parent_role_id', 'id');
    // }
}
