@extends('organization::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('organization.name') !!}</p>
@endsection
