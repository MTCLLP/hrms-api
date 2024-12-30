<?php

namespace Modules\Localization\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

use Modules\Localization\Models\Language;

use Modules\Localization\Http\Requests\Country\CreateLanguageRequest;
use Modules\Localization\Http\Requests\Country\UpdateLanguageRequest;

use Modules\Localization\Transformers\Language as LanguageResource;

class LanguageController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $languages = Language::trashed(false)->get();

        return LanguageResource::collection($languages);
    }

    /**
     * Display a paginated listing of the resource.
     * @return Response
     */
    public function paginated()
    {
        $languages = Language::paginate(10);

        return LanguageResource::collection($languages);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        $language = Language::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'is_active' => 1,
            'is_trashed' => 0,
            'created_by' => auth()->user()->id
        ]);

        return new LanguageResource($language);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show(Language $language)
    {
        return new LanguageResource($language);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, Language $language)
    {
        $language->name = $request->input('name');
        $language->code = $request->input('code');
       	$language->is_active = $request->input('is_active');
        $language->is_trashed = $request->input('is_trashed');

        $language->save();

        return new LanguageResource($language);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        $language = Language::findOrFail($id);

		$is_trashed = $language->is_trashed;

		if($is_trashed == 1) {
			$language->delete(); // delete language
		}
		else{
            $language->is_trashed = '1';
            $language->deleted_at = \Carbon\Carbon::now();
            $language->save();
        }

		return response()->json([
			"message" => "language deleted"
		], 202);
    }

	/**
     * Display a listing of the trashed items.
     * @return Response
     */
    public function trash()
    {
        $languages = Language::ordered('desc')->trashed(true)->get();

        return LanguageResource::collection($languages);
    }

	/**
     * Restore item from the trash.
     */
    public function restore(Request $request, $id)
    {
        $language = Language::findOrFail($id);

        $language->is_trashed = '0';
        $language->deleted_at = null;
        $language->save();

		return response()->json([
			"message" => "language restored successfully"
		], 202);
    }
}
