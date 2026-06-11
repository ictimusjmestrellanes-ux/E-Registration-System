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
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Full Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Civil Status</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Address</th>
                                        <th>Province</th>
                                        <th>City</th>
                                        <th>Barangay</th>
                                        <th>Archived At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    @forelse ($archivedClients as $client)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>
                                                @php
                                                    $clientPhoto = $client->photo_url;
                                                @endphp
                                                <img
                                                    src="{{ $clientPhoto }}"
                                                    alt="Archived Client Photo"
                                                    class="rounded-3 border object-fit-cover"
                                                    style="width: 72px; height: 72px;"
                                                >
                                            </td>
                                            <td>{{ trim($client->first_name . ' ' . ($client->middle_name ? $client->middle_name . ' ' : '') . $client->last_name) }}</td>
                                            <td>{{ $client->age ?? '-' }}</td>
                                            <td>{{ $client->gender ?? '-' }}</td>
                                            <td>{{ $client->civil_status ?? '-' }}</td>
                                            <td>{{ $client->email ?? '-' }}</td>
                                            <td>{{ $client->contact ?? '-' }}</td>
                                            <td>{{ $client->address ?? '-' }}</td>
                                            <td>{{ $client->province ?? '-' }}</td>
                                            <td>{{ $client->city ?? '-' }}</td>
                                            <td>{{ $client->barangay ?? '-' }}</td>
                                            <td>{{ $client->archived_at ? $client->archived_at->format('M d, Y h:i A') : '-' }}</td>
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
                                            <td colspan="14" class="text-center text-muted py-4">
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
