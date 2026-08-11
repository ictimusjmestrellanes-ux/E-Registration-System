@extends('layouts.master')
@section('title', 'Edit Transaction')
@section('content')
    @include('pages.client_transaction.newTransaction', ['isEditMode' => true])
@endsection
