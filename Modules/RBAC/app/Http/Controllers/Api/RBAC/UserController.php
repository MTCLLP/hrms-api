<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\RBAC\Models\User;

use Spatie\Permission\Models\Role; // import laravel spatie permission models

use Modules\RBAC\Transformers\User as UserResource;

use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function paginated()
    {

        $users = User::orderBy('created_at','desc')->paginate(10);

        return UserResource::collection($users);
    }

    public function index(Request $request)
    {

        $users = User::all();

        return UserResource::collection($users);

    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        // $activation_token = str_random(60);

        $roles = $request['roles']; // retrieving the roles field

        $user = User::create([
            'name'  =>  $request->name,
            'email' =>  $request->email,
            'mobile' => $request->mobile,
            'password'  =>  $request->password,
            // 'activation_token'  =>  $activation_token,
        ]);

        $token = $user->createToken("API TOKEN")->plainTextToken;

        /* send email notification on email provided by user
         * create a "RegisterActive" class for sending an email
         * run commnand php artisan make:notification RegisterActive
         */
        //$user->notify(new RegisterActive($user));

        // check if a role was selected
        if (isset($roles)) {
            foreach ($roles as $role) {
                $role_r = Role::where('id', '=', $role)->firstOrFail();

                $user->assignRole($role_r); // assign role to user
            }
        }

        return new UserResource($user);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, User $user)
    {

        $input = $request->only(['name', 'email', 'mobile', 'password']); // retreive the name, email & password fields

        $roles = $request['roles']; // retreive all roles

        $user->fill($input)->save();

        if (isset($roles)) {
            $user->roles()->sync($roles); // if one or more role is selected associate user to roles
        }
        else {
            $user->roles()->detach(); // if no role is selected remove exisiting role associated to a user
        }

        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        $user->delete();

        return response()->json(['success'=>'User deleted!']);
    }
}
