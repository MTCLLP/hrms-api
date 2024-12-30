@extends('localization::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('localization.name') !!}</p>
@endsection
