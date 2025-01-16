<?php

namespace Modules\Localization\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Localization\Models\Country;

use Modules\Localization\Http\Requests\Country\CreateCountryRequest;
use Modules\Localization\Http\Requests\Country\UpdateCountryRequest;

use Modules\Localization\Transformers\Country as CountryResource;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $countries = Country::all();
        // $countries = Country::trashed(false)->get();

        return CountryResource::collection($countries);
    }


	/**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $countries = Country::QueryBuilder::for(Country::class)->allowedFilters('name')->trashed(false)->paginate(10);

        return CountryResource::collection($countries);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateCountryRequest $request)
    {
        $country = Country::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'iso3_code' => $request->input('iso3_code'),
            'numeric_code' => $request->input('numeric_code'),
            'is_active' => 1,
            'is_trashed' => 0,
            'created_by' => auth()->user()->id
        ]);

        return new CountryResource($country);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Country $country)
    {
        return new CountryResource($country);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateCountryRequest $request, Country $country)
    {
        $country->name = $request->input('name');
        $country->code = $request->input('code');
        $country->iso3_code = $request->input('iso3_code');
        $country->numeric_code = $request->input('numeric_code');
        $country->is_active = $request->input('is_active');
        $country->is_trashed = $request->input('is_trashed');

        $country->save();

        return new CountryResource($country);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    // public function destroy(Country $country)
    // {
    //     $country = Country::findOrFail($country->id);

	// 	$is_trashed = $country->is_trashed;

	// 	if($is_trashed == 1) {
	// 		$country->delete(); // delete country
	// 	}
	// 	else{
    //         $country->is_trashed = '1';
    //         $country->deleted_at = \Carbon\Carbon::now();
    //         $country->save();
    //     }

	// 	return response()->json([
	// 		"message" => "country deleted"
	// 	], 202);

    // }

	public function destroy($id)
    {
        $country = Country::findOrFail($id);

		$is_trashed = $country->is_trashed;

		if($is_trashed == 1) {
			$country->delete(); // delete country
		}
		else{
            $country->is_trashed = '1';
            $country->deleted_at = \Carbon\Carbon::now();
            $country->save();
        }

		return response()->json([
			"message" => "country deleted"
		], 202);

    }

	public function destroyMany(Request $request)
    {
        //$country = Country::findOrFail($id);

		//$is_trashed = $country->is_trashed;

		// if($is_trashed == 1) {
		// 	$country->delete(); // delete country
		// }
		// else{
        //     $country->is_trashed = '1';
        //     $country->deleted_at = \Carbon\Carbon::now();
        //     $country->save();
        // }

		//Country::destroy($ids);
		//dd($request->ids);
		// return response()->json([
		// 		"message" => "country deleted",
		// 		"count" => $request->ids
		// 	], 202);
		$selectedCountries = $request->ids;
		//return $selectedCountries[1];
		$count = 0;
		for($i=0; $i<count($request->ids); $i++){

			$count++;

			$country = Country::findOrFail($selectedCountries[$i]);

			$is_trashed = $country->is_trashed;

			if($is_trashed == 1) {
				$country->delete(); // delete country

				// return response()->json([
				// 	"message" => "country deleted",
				// 	"count" => count($request->ids)
				// ], 202);
			}
			else{
				$country->is_trashed = '1';
				$country->deleted_at = \Carbon\Carbon::now();
				$country->save();

				// return response()->json([
				// 	"message" => "country moved to trash",
				// 	"count" => count($request->ids)
				// ], 202);
			}

			return response()->json([
				"message" => $count,
			], 202);

		}

		// try {
		// 	Country::whereIn('id', $request->ids)->delete(); // $request->id MUST be an array
		// 	return response()->json('country deleted');
		// } catch (Exception $e) {
		// 	return response()->json($e->getMessage(), 500);
		// }
    }

	/**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $countries = Country::ordered('desc')->trashed(true)->get();

        return CountryResource::collection($countries);
    }

	/**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $country = Country::findOrFail($id);

        $country->is_trashed = '0';
        $country->deleted_at = null;
        $country->save();

		return response()->json([
			"message" => "country restored successfully"
		], 202);
    }
}
