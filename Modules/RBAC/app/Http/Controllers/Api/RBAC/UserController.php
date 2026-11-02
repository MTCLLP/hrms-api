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

    public function getUserByRole(Request $request, $roleName)
    {
        $users = QueryBuilder::for(User::class)
            ->role($roleName)
            ->get();

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

        $userData = [
            'name'  =>  $request->name,
            'email' =>  $request->email,
            'mobile' => $request->mobile,
            'password'  =>  $request->password,
        ];

        if ($request->has('is_active')) {
            $userData['is_active'] = $request->boolean('is_active');
        }

        $user = User::create($userData);

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

        if ($request->has('is_active')) {
            $input['is_active'] = $request->boolean('is_active');
        }

        $roles = $request['roles']; // retreive all roles

        $user->fill($input)->save();

        if (isset($input['is_active']) && $user->employee) {
            $user->employee->is_active = $input['is_active'];
            $user->employee->save();
        }

        if (isset($roles)) {
            $user->roles()->sync($roles); // if one or more role is selected associate user to roles
        }
        else {
            $user->roles()->detach(); // if no role is selected remove exisiting role associated to a user
        }

        return new UserResource($user);
    }

    /**
     * Activate or deactivate a user account.
     * Only Administrator or Superadmin can perform this action.
     *
     * @param Request $request
     * @param User $user
     * @return Response
     */
    public function toggleStatus(Request $request, User $user)
    {
        $currentUser = auth()->user();

        if (!$currentUser->hasRole(['Administrator', 'Superadmin'])) {
            return response()->json(['message' => 'Unauthorized. Only administrators can change account status.'], 403);
        }

        if ($currentUser->id === $user->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 400);
        }

        $newStatus = $request->has('is_active')
            ? $request->boolean('is_active')
            : !$user->is_active;

        $user->is_active = $newStatus;
        $user->save();

        // Also sync the associated employee active status if present
        if ($user->employee) {
            $user->employee->is_active = $newStatus;
            $user->employee->save();
        }

        $statusText = $newStatus ? 'activated' : 'deactivated';
        return response()->json([
            'status' => true,
            'message' => "User account has been {$statusText} successfully.",
            'data' => new UserResource($user),
        ], 200);
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
