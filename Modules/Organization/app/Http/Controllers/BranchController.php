<?php

namespace Modules\Organization\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Organization\Models\Branch;
use Modules\Organization\Transformers\BranchResource as BranchResource;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $branches = Branch::where('is_trashed',false)->orderBy('created_at','desc')->get();


        return BranchResource::collection($branches);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $branches = Brancha::paginate(10);

        return BranchResource::collection($branches);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $branch = Branch::create([
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'contact_no' => $request->input('contact_no'),
            'city_id' => $request->input('city_id'),
            'state_id' => $request->input('state_id'),
            'country_id' => $request->input('country_id'),
            'is_active' => 1,
            'is_trashed' => 0,

        ]);
        //Insert Branch Department ids in pivot
        return new BranchResource($branch);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Branch $branch)
    {
        return new BranchResource($branch);
    }


    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Branch $branch)
    {
        $branch->name = $request->input('name');
        $branch->address = $request->input('address');
        $branch->contact_no = $request->input('contact_no');
        $branch->is_active = $request->input('is_active');
        $branch->is_trashed = $request->input('is_trashed');

        $branch->save();

        return new BranchResource($branch);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);

		$is_trashed = $branch->is_trashed;

		if($is_trashed == 1) {
			$branch->delete(); // delete country
		}
		else{
            $branch->is_trashed = '1';
            $branch->deleted_at = \Carbon\Carbon::now();
            $branch->save();
        }

		return response()->json([
			"message" => "Branch deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $branches = Branch::ordered('desc')->trashed(true)->get();

        return BranchResource::collection($branches);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);

        $branch->is_trashed = '0';
        $branch->deleted_at = null;
        $branch->save();

		return response()->json([
			"message" => "Branch restored successfully"
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
                "message" => "No Branch IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $branches = Branch::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($branches as $branch) {
            $is_trashed = $branch->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $branch->delete();
                $deletedPermanently[] = $branch->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $branch->is_trashed = '1';
                $branch->deleted_at = \Carbon\Carbon::now();
                $branch->save();
                $softDeleted[] = $branch->id;
            }
        }


        return response()->json([
            "message" => "Branches processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }

}
