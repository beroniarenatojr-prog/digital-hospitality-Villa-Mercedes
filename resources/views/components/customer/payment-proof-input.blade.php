<input type="file" id="proof" name="proof" accept=".jpg,.jpeg,.png,.pdf" required
       aria-describedby="{{ $errors->has('proof') ? 'proof-help proof-error' : 'proof-help' }}"
       @error('proof') aria-invalid="true" @enderror
       onchange="this.setCustomValidity(this.files[0]?.size > 5 * 1024 * 1024 ? 'Choose a receipt of 5 MB or less.' : '')"
       class="mt-2 block w-full min-w-0 text-sm file:mr-3 file:min-h-11 file:rounded-md file:border-0 file:bg-primary file:px-3 file:text-white focus-visible:outline-2 focus-visible:outline-primary">
<p id="proof-help" class="mt-2 text-sm text-text-muted">Choose a JPG, PNG, or PDF up to 5 MB. Make sure the amount and reference are readable.</p>
@error('proof')<p id="proof-error" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
