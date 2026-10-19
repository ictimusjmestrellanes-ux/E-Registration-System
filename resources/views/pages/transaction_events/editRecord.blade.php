<div class="modal fade" id="editRecordModal" tabindex="-1" aria-labelledby="editRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRecordModalLabel">Edit Event Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editRecordForm" class="d-flex flex-column overflow-hidden" method="POST"
                action="{{ old('edit_record_id') ? route('transaction-events.records.update', array_merge(request()->query(), ['event' => old('edit_record_id')])) : '' }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="edit_record_id" value="{{ old('edit_record_id') }}">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_record_full_name" class="form-label">Full Name</label>
                            <input id="edit_record_full_name" name="full_name" type="text"
                                class="form-control @error('full_name') is-invalid @enderror"
                                value="{{ old('full_name') }}" maxlength="150" required>
                            @error('full_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @if (feature_allowed('Edit Transaction Event Record Fields'))
                            <div class="col-md-6">
                                <label for="edit_record_age" class="form-label">Age</label>
                                <input id="edit_record_age" name="age" type="number"
                                    class="form-control @error('age') is-invalid @enderror" value="{{ old('age') }}"
                                    min="0" max="150" step="1">
                                @error('age')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_birth_date" class="form-label">Birth Date</label>
                                <input id="edit_record_birth_date" name="birth_date" type="date"
                                    class="form-control @error('birth_date') is-invalid @enderror"
                                    value="{{ old('birth_date') }}">
                                @error('birth_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_contact_no" class="form-label">Contact Number</label>
                                <input id="edit_record_contact_no" name="contact_no" type="text"
                                    class="form-control @error('contact_no') is-invalid @enderror"
                                    value="{{ old('contact_no') }}" maxlength="30">
                                @error('contact_no')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_address" class="form-label">Address</label>
                                <input id="edit_record_address" name="address" type="text"
                                    class="form-control @error('address') is-invalid @enderror"
                                    value="{{ old('address') }}" maxlength="255">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_client_category" class="form-label">Client Category</label>
                                <input id="edit_record_client_category" name="client_category" type="text"
                                    class="form-control @error('client_category') is-invalid @enderror"
                                    value="{{ old('client_category') }}" maxlength="100">
                                @error('client_category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_transaction_category" class="form-label">Transaction
                                    Category</label>
                                <input id="edit_record_transaction_category" name="transaction_category" type="text"
                                    class="form-control @error('transaction_category') is-invalid @enderror"
                                    value="{{ old('transaction_category') }}" maxlength="100">
                                @error('transaction_category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_transaction_type" class="form-label">Transaction Type</label>
                                <input id="edit_record_transaction_type" name="transaction_type" type="text"
                                    class="form-control @error('transaction_type') is-invalid @enderror"
                                    value="{{ old('transaction_type') }}" maxlength="100">
                                @error('transaction_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_event_date" class="form-label">Event Date</label>
                                <input id="edit_record_event_date" name="event_date" type="date"
                                    class="form-control @error('event_date') is-invalid @enderror"
                                    value="{{ old('event_date') }}">
                                @error('event_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="edit_record_remarks" class="form-label">Remarks</label>
                                <textarea id="edit_record_remarks" name="remarks" rows="1"
                                    class="form-control @error('remarks') is-invalid @enderror" placeholder="Enter remarks (optional)">{{ old('remarks') }}</textarea>
                                @error('remarks')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="saveEditRecordBtn">
                        <span class="edit-record-save-label">Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
