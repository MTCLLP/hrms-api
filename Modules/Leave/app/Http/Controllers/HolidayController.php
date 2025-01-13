<?php

namespace Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Leave\Models\Holiday;
use Modules\Leave\Transformers\HolidayResource as HolidayResource;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $holidays = Holiday::where('is_trashed',false)->orderBy('created_at','asc')->get();

        return HolidayResource::collection($holidays);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $holidays = Holiday::where('is_trashed',false)->orderBy('date','asc')->paginate(10);

        return HolidayResource::collection($holidays);
    }

    /**
     * Store a newly created resource in storage.
     * @param Setting $Setting
     * @return Renderable
     */
    public function store(Request $request)
    {
        $holidays = Holiday::create([
            'name' => $request->input('name'),
            'date' => $request->input('date'),
            'description' => $request->input('description'),
            'created_by' => auth()->user()->id,
            'is_active' => 1,
            'is_trashed' => 0,

        ]);

        return new HolidayResource($holidays);
    }

    /**
     * Show the specified resource.
     */
    public function show(Holiday $holiday)
    {
        return new HolidayResource($holiday);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Holiday $holiday)
    {
        $holiday->name = $request->input('name');
        $holiday->date = $request->input('date');
        $holiday->description = $request->input('description');
        $holiday->is_active = $request->input('is_active');
        $holiday->is_trashed = $request->input('is_trashed');
        $holiday->created_by = auth()->user()->id;
        $holiday->save();

        return new HolidayResource($holiday);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);

		$is_trashed = $holiday->is_trashed;

		if($is_trashed == 1) {
			$holiday->delete(); // delete country
		}
		else{
            $holiday->is_trashed = '1';
            $holiday->deleted_at = \Carbon\Carbon::now();
            $holiday->save();
        }

		return response()->json([
			"message" => "Holiday deleted"
		], 202);
    }

    /**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $holidays = Holiday::where('is_trashed',false)->get();

        return HolidayResource::collection($holidays);
    }

    /**
     * Restore item from the trash.
     */
    public function restore(Holiday $holiday, $id)
    {
        $holiday = Holiday::findOrFail($id);

        $holiday->is_trashed = '0';
        $holiday->deleted_at = null;
        $holiday->save();

		return response()->json([
			"message" => "Holiday restored successfully"
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

     * @param \Illuminate\Http\Holiday $holiday
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyMultiple(Holiday $holiday)
    {
        $ids = $holiday->ids; // Get the array of IDs from the Setting


        if (empty($ids)) {
            return response()->json([
                "message" => "No holiday IDs provided"
            ], 400);
        }


        // Get all companies that match the IDs provided
        $holidays = Holiday::whereIn('id', $ids)->get();


        $deletedPermanently = [];
        $softDeleted = [];


        foreach ($holidays as $holiday) {
            $is_trashed = $holiday->is_trashed;


            if ($is_trashed == 1) {
                // If already trashed, permanently delete
                $holiday->delete();
                $deletedPermanently[] = $holiday->id;
            } else {
                // Otherwise, soft delete by setting is_trashed to 1
                $holiday->is_trashed = '1';
                $holiday->deleted_at = \Carbon\Carbon::now();
                $holiday->save();
                $softDeleted[] = $holiday->id;
            }
        }


        return response()->json([
            "message" => "Holiday processed",
            "deleted_permanently" => $deletedPermanently,
            "soft_deleted" => $softDeleted,
        ], 202);
    }
}
