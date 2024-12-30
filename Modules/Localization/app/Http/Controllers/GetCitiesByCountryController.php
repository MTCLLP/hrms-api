<?php

namespace Modules\Localization\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Localization\Models\Country;

use Modules\Localization\Transformers\GetCitiesByCountry as GetCitiesByCountryResource;

class GetCitiesByCountryController extends Controller
{

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Country $country)
    {
        return new GetCitiesByCountryResource($country);
    }
}
