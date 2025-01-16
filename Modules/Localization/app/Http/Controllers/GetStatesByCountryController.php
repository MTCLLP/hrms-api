<?php

namespace Modules\Localization\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Localization\Models\Country;

use Modules\Localization\Transformers\GetStatesByCountry as GetStatesByCountryResource;


class GetStatesByCountryController extends Controller
{

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Country $country)
    {
        return new GetStatesByCountryResource($country);
    }
}
