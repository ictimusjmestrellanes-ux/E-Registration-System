<div class="dropdown d-inline-block me-1">
    <button type="button" class="btn btn-sm btn-soft-info dropdown-toggle text-nowrap"
        data-bs-toggle="dropdown" aria-expanded="false" aria-label="Tag status for event #{{ $event->id }}">
        <i class="ri-price-tag-3-line me-1" aria-hidden="true"></i>Tag as
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach (\App\Models\TransactionEvent::STATUSES as $status)
            <li>
                <form action="{{ route('transaction-events.records.status', array_merge(request()->query(), ['event' => $event->id, 'duplicate_tab' => $tab])) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" name="status" value="{{ $status }}"
                        class="dropdown-item {{ $event->status === $status ? 'active' : '' }}">
                        Tag as {{ $status }}
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
