<x-app-layout title="Add Vehicle">

    <div class="haven-breadcrumb">
        <a href="{{ route('vehicles.index') }}" class="haven-breadcrumb-link">My Vehicles</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span style="color:var(--h-text2);">Add Vehicle</span>
    </div>

    <div class="haven-form-page">
        <div class="haven-form-card">

            <div class="haven-form-card-header">
                <div class="h-form-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                        <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                </div>
                <div>
                    <h1 class="haven-form-title">Register a Vehicle</h1>
                    <p class="haven-form-subtitle">Lookup make and model from the NHTSA catalog, or decode a VIN.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('vehicles.store') }}" id="vehicle-form">
                @csrf

                @include('vehicles.partials.catalog-fields')

                <div class="haven-form-section-label" style="margin-top:1.5rem;">Registration & Appearance</div>
                <div class="haven-form-grid-2">
                    <div class="haven-form-group">
                        <label class="haven-label" for="plate_number">Plate Number</label>
                        <input id="plate_number" name="plate_number" type="text" value="{{ old('plate_number') }}"
                               class="haven-input @error('plate_number') haven-input-error @enderror"
                               placeholder="e.g. ABC-1234">
                        @error('plate_number')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    </div>
                    <div class="haven-form-group">
                        <label class="haven-label" for="color">Color</label>
                        <input id="color" name="color" type="text" value="{{ old('color') }}"
                               class="haven-input @error('color') haven-input-error @enderror"
                               placeholder="e.g. Pearl White">
                        @error('color')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="haven-form-section-label" style="margin-top:1.5rem;">Engine & Drivetrain</div>
                <div class="haven-form-grid-2">
                    <div class="haven-form-group">
                        <label class="haven-label" for="engine_type">Engine Type</label>
                        <input id="engine_type" name="engine_type" type="text" value="{{ old('engine_type') }}"
                               class="haven-input @error('engine_type') haven-input-error @enderror"
                               placeholder="e.g. 2NZ-FE 1.3L">
                        @error('engine_type')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    </div>
                    <div class="haven-form-group">
                        <label class="haven-label" for="transmission">Transmission</label>
                        <select id="transmission" name="transmission" class="haven-input haven-select @error('transmission') haven-input-error @enderror">
                            <option value="">— Select —</option>
                            @foreach(['Manual','Automatic','CVT','Other'] as $t)
                                <option value="{{ $t }}" {{ old('transmission') === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('transmission')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    </div>
                    <div class="haven-form-group">
                        <label class="haven-label" for="mileage_km">Current Mileage (km)</label>
                        <input id="mileage_km" name="mileage_km" type="number" value="{{ old('mileage_km') }}"
                               class="haven-input @error('mileage_km') haven-input-error @enderror"
                               placeholder="e.g. 45000" min="0">
                        @error('mileage_km')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="haven-form-group" style="margin-top:1.5rem;">
                    <label class="haven-label" for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="haven-input @error('notes') haven-input-error @enderror"
                              placeholder="Any additional info…">{{ old('notes') }}</textarea>
                    @error('notes')<p class="haven-error-msg">{{ $message }}</p>@enderror
                </div>

                <div class="haven-form-actions">
                    <a href="{{ route('vehicles.index') }}" class="haven-btn-ghost">Cancel</a>
                    <button type="submit" class="haven-btn-primary" style="width:auto;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/>
                        </svg>
                        Save Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>

</x-app-layout>
