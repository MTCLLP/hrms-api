<?php

namespace Modules\RBAC\Http\Controllers\Api\RBAC;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\RBAC\Entities\Profile;

use Modules\RBAC\Transformers\UserProfileResource as UserProfileResource;

class UserProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $user_profiles = UserProfile::all;

        return UserProfileResource::collection($user_profiles);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        // $request->validate([
        //     'specialization' => 'required',
        //     'file' => 'required',
        //     'user_id' => 'required|unique'
        // ]);

        $user_profile = Profile::create([
            'specialization' => $request->input('specialization'),
            'qualification' => $request->input('qualification'),
            'number' => $request->input('number'),
            'clinic_name' => $request->input('clinic_name'),
            'address' => $request->input('address'),
            'alt_address' => $request->input('alt_address'),
            'user_id' => auth()->user()->id,
        ]);

        return new UserProfileResource($user_profile);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Profile $user_profile)
    {
        return new UserProfileResource($user_profile);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Profile $user_profile)
    {

        $user_profile->specialization = $request->input('specialization');
        $user_profile->qualification = $request->input('qualification');
        $user_profile->number = $request->input('number');
        $user_profile->clinic_name = $request->input('clinic_name');
       	$user_profile->address = $request->input('address');
        $user_profile->alt_address = $request->input('alt_address');
        $user_profile->user_id = auth()->user()->id;

        $user_profile->save();

        return new UserProfileResource($user_profile);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $user_profile = Profile::findOrFail($id);

		$user_profile->delete();

		return response()->json([
			"message" => "profile deleted"
		], 202);
    }

}
