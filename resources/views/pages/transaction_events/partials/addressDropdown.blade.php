<div class="dropdown w-100" id="{{ $addressId }}">
    <button class="btn btn-light border form-select text-start d-flex align-items-center justify-content-between"
        type="button" id="{{ $addressId }}Btn" data-bs-toggle="dropdown" data-bs-auto-close="outside"
        aria-expanded="false" style="padding: .5rem .75rem;">
        <span data-address-label>All addresses</span>
    </button>
    <div class="dropdown-menu w-100 p-2" aria-labelledby="{{ $addressId }}Btn" style="max-height: 280px; overflow-y: auto;">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="{{ $addressId }}All" data-address-all>
            <label class="form-check-label fw-semibold" for="{{ $addressId }}All">All addresses</label>
        </div>
        <hr class="my-2">
        @foreach ($addresses as $address)
            <div class="form-check" data-address-option>
                <input class="form-check-input" type="checkbox" name="address[]"
                    id="{{ $addressId }}_{{ $loop->index }}" value="{{ $address }}"
                    @checked(in_array($address, (array) request('address', []), true))>
                <label class="form-check-label" for="{{ $addressId }}_{{ $loop->index }}">{{ $address }}</label>
            </div>
        @endforeach
    </div>
</div>
