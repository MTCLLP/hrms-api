<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use Spatie\Permission\Models\Role; // import laravel spatie permission models
use Spatie\Permission\Models\Permission; // import laravel spatie permission models

use Modules\RBAC\Transformers\Permission as PermissionResource;

use Spatie\QueryBuilder\QueryBuilder;

class PermissionController extends Controller
{
    /**
     * isAdmin middleware lets only users with a specific permission to access these resources
     */
    // public function __construct() {
    //     $this->middleware(['auth', 'isAdmin']);
    // }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function paginated()
    {
        $permissions = Permission::paginate(10);

        return PermissionResource::collection($permissions);
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $permissions = Permission::all();

        return PermissionResource::collection($permissions);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $name = $request['name'];
        $guard_name = 'api';
        $permission = new Permission();
        $permission->name = $name;
        $permission->guard_name = $guard_name;

        $roles = $request['roles'];

        $permission->save();

        if (!empty($request['roles'])) {

            // if one or more role is selected
            foreach ($roles as $role) {
                $r = Role::where('id', '=', $role)->firstOrFail(); // match input role to database record

                $permission = Permission::where('name', '=', $name)->first(); // match input permission to database record

                $r->givePermissionTo($permission);
            }
        }

        return new PermissionResource($permission);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Permission $permission)
    {
        $permission->load('roles');
        return new PermissionResource($permission);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Permission $permission)
    {
        $input = $request->all();
        $permission->fill($input)->save();

        if (!empty($input['roles'])) {
            // if one or more role is selected
            foreach ($input['roles'] as $role) {
                $r = Role::where('id', '=', $role)->firstOrFail(); // match input role to database record
                $r->givePermissionTo($permission);
            }
        }

        return new PermissionResource($permission);
    }


    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        $permission->delete();

        return response()->json(['success'=>'Permission deleted!']);
    }

    public function assignPermission()
    {
        // Find the role by name and guard
        $role = Role::where('name', 'Employee')->where('guard_name', 'api')->first();

        // Check if the role exists
        if (!$role) {
            return response()->json(['error' => 'Role not found'], 404);
        }

        // Fetch the permissions by IDs and guard
        $permissions = Permission::whereIn('id', [
            104,106,107,108,109,110,111,112,113,114,115,116,117,118,118,120,121,122,123,124,125,126,140,144,148,150,151,152,153,154,161,169,173
        ])->where('guard_name', 'api')->get();

        // Sync permissions with the role
        $role->syncPermissions($permissions);

        return response()->json(['success' => 'Permissions assigned!']);
    }
}
