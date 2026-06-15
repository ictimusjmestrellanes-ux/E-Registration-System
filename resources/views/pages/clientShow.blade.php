@extends('layouts.master')
@section('title', 'Client Details')
@section('content')
    @php
        $clientPhoto = $client->photo_url;
        $clientFingerprint = $client->fingerprint_url;
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
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-lg-3 text-center">
                                <img src="{{ $clientPhoto }}" alt="Client Photo" class="img-thumbnail avatar-xxl object-fit-cover rounded-3">
                                <h5 class="mt-3 mb-1">{{ $client->full_name }}</h5>
                                <p class="text-muted mb-0">{{ $client->gender ?? 'N/A' }}</p>
                            </div>

                            <div class="col-lg-9">
                                <div class="mb-3">
                                    <div class="fw-semibold mb-2">Fingerprint Record</div>
                                    <img src="{{ $clientFingerprint }}" alt="Client Fingerprint" class="img-thumbnail rounded-3 object-fit-cover" style="width: 180px; height: 180px;">
                                </div>
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
                                                <th>Suffix</th>
                                                <td>{{ $client->suffix ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Birth Date</th>
                                                <td>{{ $client->birth_date?->format('M d, Y') ?? '-' }}</td>
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
                                                <th>Birthplace</th>
                                                <td>{{ $client->birthplace ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Education</th>
                                                <td>{{ $client->education ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Course</th>
                                                <td>{{ $client->course ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Sector</th>
                                                <td>{{ $client->sector ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Position / Organization</th>
                                                <td>{{ $client->position_organization ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td>{{ $client->email ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Contact 1</th>
                                                <td>{{ $client->contact ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Contact 2</th>
                                                <td>{{ $client->contact_2 ?? '-' }}</td>
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
