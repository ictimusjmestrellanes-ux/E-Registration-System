@extends('layouts.master')
@section('title', 'Archive')
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                            <div>
                                <h4 class="mb-1">Archived Clients</h4>
                                <p class="text-muted mb-0">View clients that have been moved to the archive.</p>
                            </div>
                            <a href="{{ route('client.list') }}" class="btn btn-primary">Back to Client List</a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Client ID</th>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Civil Status</th>
                                        <th>Birthplace</th>
                                        <th>Contact 1</th>
                                        <th>Address</th>
                                        <th>Archived At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    @forelse ($archivedClients as $client)
                                        <tr>
                                            <td>{{ $client->client_id ?? '-' }}</td>
                                            <td>
                                                @php
                                                    $clientPhoto = $client->photo_url;
                                                    $defaultClientPhoto = asset('images/default-client.png');
                                                @endphp
                                                <button type="button" class="btn p-0 border-0 bg-transparent"
                                                    data-bs-toggle="modal" data-bs-target="#clientPhotoModal"
                                                    data-client-photo="{{ $clientPhoto }}"
                                                    data-client-name="{{ trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name) }}">
                                                    <img src="{{ $clientPhoto }}" alt="Client Photo"
                                                        onerror="this.onerror=null;this.src='{{ $defaultClientPhoto }}';"
                                                        class="rounded-3 border object-fit-cover"
                                                        style="width: 72px; height: 72px;">
                                                </button>
                                            </td>
                                            <td>
                                                {{ $client->full_name }}
                                            </td>
                                            <td>{{ $client->suffix ?? '-' }}</td>
                                            <td>{{ $client->gender ?? '-' }}</td>
                                            <td>{{ $client->civil_status ?? '-' }}</td>
                                            <td>{{ $client->birthplace ?? '-' }}</td>
                                            <td>{{ $client->contact ?? '-' }}</td>
                                            <td class="text-start">
                                                <div class="small lh-sm">
                                                    <div>{{ $client->address ?? '-' }}</div>
                                                    <div class="text-muted">
                                                        {{ collect([$client->barangay, $client->city, $client->province])->filter()->implode(', ') ?:'-' }}
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{{ $client->archived_at ? $client->archived_at->format('m/d/Y h:i A') : '-' }}</td>
                                            <td>
                                                <form action="{{ route('archive.restore', $client) }}" method="POST" onsubmit="return confirm('Restore this client back to the active list?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success">
                                                        Restore
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="23" class="text-center text-muted py-4">
                                                No archived clients found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
