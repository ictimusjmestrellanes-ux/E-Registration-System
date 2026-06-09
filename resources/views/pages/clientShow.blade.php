@extends('layouts.master')
@section('title', 'Client Details')
@section('content')
    @php
        $clientPhoto = $client->photo_path ? asset('storage/' . $client->photo_path) : asset('assets/images/avatar-1.jpg');
    @endphp

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Client Details</h4>
                                <p class="text-muted mb-0">Review the selected client information.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('client.list') }}" class="btn btn-soft-secondary">Back to List</a>
                                <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary">Edit Client</a>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-3 text-center">
                                <img src="{{ $clientPhoto }}" alt="Client Photo" class="img-thumbnail avatar-xxl object-fit-cover rounded-3">
                                <h5 class="mt-3 mb-1">{{ trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name) }}</h5>
                                <p class="text-muted mb-0">{{ $client->gender ?? 'N/A' }}</p>
                            </div>

                            <div class="col-lg-9">
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <tbody>
                                            <tr>
                                                <th style="width: 220px;">First Name</th>
                                                <td>{{ $client->first_name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Middle Name</th>
                                                <td>{{ $client->middle_name ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Last Name</th>
                                                <td>{{ $client->last_name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Age</th>
                                                <td>{{ $client->age ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Gender</th>
                                                <td>{{ $client->gender ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Civil Status</th>
                                                <td>{{ $client->civil_status ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td>{{ $client->email ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Contact</th>
                                                <td>{{ $client->contact ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Address</th>
                                                <td>{{ $client->address ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Province</th>
                                                <td>{{ $client->province ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>City</th>
                                                <td>{{ $client->city ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Barangay</th>
                                                <td>{{ $client->barangay ?? '-' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
