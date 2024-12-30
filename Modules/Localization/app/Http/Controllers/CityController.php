<?php

namespace Modules\Localization\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Localization\Models\City;
use Modules\Localization\Models\State;
use Modules\Localization\Models\Country;

use Modules\Localization\Http\Requests\City\CreateCityRequest;
use Modules\Localization\Http\Requests\City\UpdateCityRequest;

use Modules\Localization\Http\Transformers\City as CityResource;
use Modules\Localization\Http\Transformers\CityDropDown as CityDropDownResource;

class CityController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $cities = QueryBuilder::for(City::class)
        ->allowedFilters(['name','state_id'])->get();

        return CityResource::collection($cities);
    }

	/**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $cities = QueryBuilder::for(City::class)
        ->allowedFilters(['name','state_id'])->paginate(10);

        return CityResource::collection($cities);
    }

    public function dropdown()
    {
        $cities = City::all();

        return CityDropDownResource::collection($cities);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateCityRequest $request)
    {
        $city = City::create([
            'name' => $request->input('name'),
            'state_id' => $request->input('state_id'),
            'country_id' => $request->input('country_id'),

        ]);

        return new CityResource($city);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(City $city)
    {
        return new CityResource($city);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateCityRequest $request, City $city)
    {
        $city->name = $request->input('name');
        $city->state_id = $request->input('state_id');
        $city->country_id = $request->input('country_id');

        $city->save();

        return new CityResource($city);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        $city = City::findOrFail($id);


			$city->delete(); // delete country

		return response()->json([
			"message" => "city deleted"
		], 202);
    }

}
