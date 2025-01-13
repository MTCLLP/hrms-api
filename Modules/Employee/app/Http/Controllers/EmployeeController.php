<?php

namespace Modules\Employee\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Employee\Models\Employee;
use Modules\Employee\Transformers\EmployeeResource as EmployeeResource;
use Modules\RBAC\Models\User;
use Modules\RBAC\Events\UserRegistered;
use Hash;
use Carbon\Carbon;
use DB;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {

        $employees = Employee::where('is_trashed', false)
            ->with('user') // Eager load the user relationship
            ->get()
            ->sortBy('user.name'); // Sort by the related user's name



        return EmployeeResource::collection($employees);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        // $employees = QueryBuilder::for(Employee::class)
        // ->allowedIncludes(['user'])->where('is_trashed',false)->orderBy('created_at','desc')->paginate(10);
        $employees = Employee::where('is_trashed', false)
            ->whereHas('user') // Ensure user exists
            ->with('user') // Eager load user to avoid N+1 queries
            ->join('users', 'employees.user_id', '=', 'users.id') // Join users table
            ->orderBy('users.name', 'asc') // Order by user's name
            ->select('employees.*') // Select employees columns
            ->paginate(10);

        return EmployeeResource::collection($employees);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {

        $validateUser = Validator::make($request->all(),
            [
                'name' => 'required',
                'email' => 'nullable|email|unique:users,email',
                'mobile' => 'required|numeric|unique:users,mobile',
                'password' => 'required|max:16',
                'hire_date' => 'required',
                'dob' => 'required',
                'departments' => 'required',
                'jobtitles' => 'required'
            ]);

            if($validateUser->fails()){
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'mobile' => $request->input('mobile')
        ]);

        $user->assignRole('Employee');

        // // Trigger user registration event
        event(new UserRegistered($user));


        $employee = Employee::create([
            'department_id' => $request->departments,
            'hire_date' => $request->input('hire_date'),
            'user_id' => $user->id,
            'jobTitle_id' => $request->jobtitles,
            'profile_image' => $request->input('profile_image'),
            'dob' => $request->input('dob'),
            'gender' => 'Male',
            'is_active' => 1,
            'is_trashed' => 0,

         ]);

        $user->email_verified_at = Carbon::now();
        $user->mobile_verified_at = Carbon::now();
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Employee Created Successfully'
            //'token' => $user->createToken("API TOKEN")->plainTextToken
        ], 200);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Employee $employee)
    {
        return new EmployeeResource($employee);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Employee $employee)
    {
        $employee->department_id = $request->input('department_id');
        $employee->branch_id = $request->input('branch_id');
        $employee->hire_date = $request->input('hire_date');
        $employee->termination_date = $request->input('termination_date');
        $employee->user_id = $request->input('user_id');
        $employee->jobtitle_id = $request->input('jobtitle_id');
        $employee->profile_image = $request->input('profile_image');
        $employee->dob = $request->input('dob');
        $employee->gender = $request->input('gender');
        $employee->is_active = $request->input('is_active');
        $employee->is_trashed = $request->input('is_trashed');

        $employee->save();

        return new EmployeeResource($employee);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);

		$is_trashed = $employee->is_trashed;

		if($is_trashed == 1) {
			$employee->delete(); // delete country
		}
		else{
            $employee->is_trashed = '1';
            $employee->deleted_at = \Carbon\Carbon::now();
            $employee->save();
        }

		return response()->json([
			"message" => "Employee deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $employees = Employee::where('is_trashed',true)->get();

        return EmployeeResource::collection($employees);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $employee->is_trashed = '0';
        $employee->deleted_at = null;
        $employee->save();

		return response()->json([
			"message" => "Employee restored successfully"
		], 202);
    }

    /**
     * Remove multiple specified resources from storage.
     *
     * This method is used to delete multiple companies from the database.
     * If the company is already marked as trashed (`is_trashed` == 1), it will be permanently deleted.
     * Otherwise, it will be soft deleted by setting the `is_trashed` flag to 1 and updating the `deleted_at` timestamp.
     *
     *

     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids; // Get the array of IDs from the request


        if (empty($ids)) {
            return response()->json([
                "message" => "No employees IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $employees = Employee::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($employees as $employee) {
            $is_trashed = $employee->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $employee->delete();
                $deletedPermanently[] = $employee->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $employee->is_trashed = '1';
                $employee->deleted_at = \Carbon\Carbon::now();
                $employee->save();
                $softDeleted[] = $employee->id;
            }
        }


        return response()->json([
            "message" => "Employees processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
