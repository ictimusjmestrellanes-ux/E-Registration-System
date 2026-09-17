<div class="modal fade" id="undoSingleTransferModal" tabindex="-1"
    aria-labelledby="undoSingleTransferModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="undoSingleTransferForm" action="#" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="undoSingleTransferModalLabel">Confirm Undo Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Undo transfer for event #<span id="undoSingleTransferEventId"></span>
                    (<span id="undoSingleTransferEventName"></span>)?</p>
                <p class="text-muted mb-0">The linked transaction record will be removed and the event will return to
                    Pending. The client record will remain. Transfers with uploaded requirements cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning" id="confirmSingleUndoTransferBtn" disabled>
                    Undo Transfer
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('undoSingleTransferModal');
            const form = document.getElementById('undoSingleTransferForm');
            const confirmButton = document.getElementById('confirmSingleUndoTransferBtn');
            if (!modal || !form || !confirmButton) return;

            modal.addEventListener('show.bs.modal', function(event) {
                const trigger = event.relatedTarget;
                const undoUrl = trigger?.dataset.undoUrl || '';
                form.setAttribute('action', undoUrl || '#');
                confirmButton.disabled = !undoUrl;
                document.getElementById('undoSingleTransferEventId').textContent = trigger?.dataset.eventId || '';
                document.getElementById('undoSingleTransferEventName').textContent = trigger?.dataset.eventName || '';
            });

            modal.addEventListener('hidden.bs.modal', function() {
                form.setAttribute('action', '#');
                confirmButton.disabled = true;
            });
        });
    </script>
@endpush
