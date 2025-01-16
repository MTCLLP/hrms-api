<?php

namespace Modules\Localization\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

use Modules\Localization\Models\State;

use Modules\Localization\Transformers\GetCitiesByState as GetCitiesByStateResource;

class GetCitiesByStateController extends Controller
{
    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(State $state)
    {
        return new GetCitiesByStateResource($state);
    }
}
