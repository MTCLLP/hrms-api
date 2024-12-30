<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use Spatie\Permission\Models\Role; // import laravel spatie permission models
use Spatie\Permission\Models\Permission; // import laravel spatie permission models

use Modules\RBAC\Transformers\Role as RoleResource;

use Spatie\QueryBuilder\QueryBuilder;

class RoleController extends Controller
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
        // $roles = Role::all(); // get all roles
        $roles = Role::paginate(10);

        return RoleResource::collection($roles);
    }

    public function index()
    {
        
        $roles = Role::all();
        return RoleResource::collection($roles);
        
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $name = $request['name'];
        $role = new Role();
        $role->name = $name;//Role Name should be unique
        $role->guard_name = 'api';

        $permissions = $request['permissions'];

        $role->save();

        // looping through selected permissions
        foreach ($permissions as $permission) {
            $p = Permission::where('id', '=', $permission)->firstOrFail();

            // fetch the newly created role & assign permission
            $role = Role::where('name', '=', $name)->first();
            $role->givePermissionTo($p);
        }

        return new RoleResource($role);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Role $role)
    {
        return new RoleResource($role);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Role $role)
    {
        $input = $request->except(['permissions']);

        $permissions = $request['permissions'];

        $role->fill($input)->save();

        $p_all = Permission::all(); // get all permissions

        foreach ($p_all as $p) {
            // remove all permissions associated with role
            $role->revokePermissionTo($p);
        }

        foreach ($permissions as $permission) {
            $p = Permission::where('id', '=', $permission)->firstOrFail(); // get corresponding form permission in db

            $role->givePermissionTo($p); // assign permission to role
        }

        return new RoleResource($role);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        $role->delete();

        return response()->json(['success'=>'Role deleted!']);
    }
}
