<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRecordModalLabel">Edit Event Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editRecordForm" class="d-flex flex-column overflow-hidden" method="POST" action="{{ old('edit_record_id') ? route('transaction-events.records.update', array_merge(request()->query(), ['event' => old('edit_record_id')])) : '' }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="edit_record_id" value="{{ old('edit_record_id') }}">
                <div class="modal-body">
                        <div class="row g-3">
                            @foreach ([
                                'full_name' => ['Full Name', 'text', 150],
                                'age' => ['Age', 'number', null],
                                'birth_date' => ['Birth Date', 'date', null],
                                'contact_no' => ['Contact Number', 'text', 30],
                                'address' => ['Address', 'text', 255],
                                'client_category' => ['Client Category', 'text', 100],
                                'transaction_category' => ['Transaction Category', 'text', 100],
                                'transaction_type' => ['Transaction Type', 'text', 100],
                                'event_date' => ['Event Date', 'date', null],
                            ] as $field => [$label, $type, $maxLength])
                                <div class="col-md-6">
                                    <label for="edit_record_{{ $field }}" class="form-label">{{ $label }}</label>
                                    <input id="edit_record_{{ $field }}" name="{{ $field }}" type="{{ $type }}"
                                        class="form-control @error($field) is-invalid @enderror"
                                        value="{{ old($field) }}"
                                        @if ($field === 'full_name') required @endif
                                        @if ($maxLength) maxlength="{{ $maxLength }}" @endif
                                        @if ($type === 'number') min="0" max="150" step="1" @endif>
                                    @error($field)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
