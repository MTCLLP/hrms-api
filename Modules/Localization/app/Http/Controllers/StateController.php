<?php

namespace Modules\Localization\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Localization\Models\State;
use Modules\Localization\Models\Country;

use Modules\Localization\Http\Requests\State\CreateStateRequest;
use Modules\Localization\Http\Requests\State\UpdateStateRequest;

use Modules\Localization\Transformers\State as StateResource;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        $states = QueryBuilder::for(State::class)
        ->allowedFilters('name')
        ->trashed(false)
        ->get();

        return StateResource::collection($states);
    }

	/**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $states = QueryBuilder::for(State::class)
        ->allowedFilters('name')
        ->trashed(false)
        ->paginate(10);

        return StateResource::collection($states);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(CreateStateRequest $request)
    {
        $state = State::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'iso_code' => $request->input('iso_code'),
            'country_id' => $request->input('country_id'),
            'is_active' => 1,
            'is_trashed' => 0,
            'created_by' => auth()->user()->id
        ]);

        return new StateResource($state);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(State $state)
    {
        return new StateResource($state);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(UpdateStateRequest $request, State $state)
    {
        $state->name = $request->input('name');
        $state->code = $request->input('code');
        $state->iso_code = $request->input('iso_code');
        $state->country_id = $request->input('country_id');
        $state->is_active = $request->input('is_active');
        $state->is_trashed = $request->input('is_trashed');

        $state->save();

        return new StateResource($state);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        $state = State::findOrFail($id);

		$is_trashed = $state->is_trashed;

		if($is_trashed == 1) {
			$state->delete(); // delete country
		}
		else{
            $state->is_trashed = '1';
            $state->deleted_at = \Carbon\Carbon::now();
            $state->save();
        }

		return response()->json([
			"message" => "state deleted"
		], 202);

    }

	/**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $states = State::ordered('desc')->trashed(true)->get();

        return StateResource::collection($states);
    }

	/**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $state = State::findOrFail($id);

        $state->is_trashed = '0';
        $state->deleted_at = null;
        $state->save();

		return response()->json([
			"message" => "state restored successfully"
		], 202);
    }
}
