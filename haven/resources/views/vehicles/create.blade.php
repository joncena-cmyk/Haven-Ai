<x-app-layout title="Register Vehicle — Showroom Configurator">

    <div class="haven-breadcrumb" style="margin-bottom:1.25rem;">
        <a href="{{ route('vehicles.index') }}" class="haven-breadcrumb-link">My Vehicles</a>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        <span style="color:var(--h-text2);">Register Vehicle</span>
    </div>

    {{-- Split-Screen Automotive Configurator --}}
    <div class="h-configurator-wrapper">
        
        {{-- Left Column: Configurator Steps & Form Controls --}}
        <div class="h-configurator-form-col">
            <div class="h-configurator-card">
                
                <div class="h-configurator-header">
                    <div class="h-configurator-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="h-configurator-title">Register Vehicle</h1>
                        <p class="h-configurator-sub">Configure your vehicle specs, connect sensor telemetry, and preview its showroom digital twin.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('vehicles.store') }}" id="vehicle-form" enctype="multipart/form-data">
                    @csrf

                    {{-- Step 1: NHTSA Catalog & VIN Decoding --}}
                    @include('vehicles.partials.catalog-fields')

                    {{-- Step 2: Body Style & Silhouette Selection --}}
                    <div class="h-cfg-section-title" style="margin-top:2rem;">
                        <span class="h-cfg-step-num">2</span>
                        <span>Body Style & Silhouette</span>
                    </div>
                    <p class="h-cfg-section-desc">Select the aerodynamic body profile used for dashboard telematics and blueprint diagnostics.</p>

                    <input type="hidden" name="body_type" id="body_type" value="{{ old('body_type', 'sedan') }}">
                    <div class="h-body-type-grid">
                        @php
                            $bodyStyles = [
                                'sedan'     => ['label' => 'Sedan', 'sub' => '4-Door Notchback'],
                                'suv'       => ['label' => 'SUV / Crossover', 'sub' => 'High Clearance'],
                                'hatchback' => ['label' => 'Hatchback', 'sub' => '2-Box Compact'],
                                'coupe'     => ['label' => 'Coupe / Sport', 'sub' => 'Fastback Aero'],
                                'pickup'    => ['label' => 'Pickup / Truck', 'sub' => 'Dual Cab with Bed'],
                            ];
                            $activeBody = old('body_type', 'sedan');
                        @endphp
                        @foreach($bodyStyles as $key => $meta)
                            <div class="h-body-type-card {{ $activeBody === $key ? 'active' : '' }}" 
                                 data-body="{{ $key }}" 
                                 onclick="selectBodyType('{{ $key }}')">
                                <div class="h-body-thumbnail-wrap">
                                    <img src="{{ asset('images/vehicles/' . $key . '.jpg') }}" alt="{{ $meta['label'] }}" class="h-body-thumbnail-img">
                                </div>
                                <div class="h-body-card-title">{{ $meta['label'] }}</div>
                                <div class="h-body-card-sub">{{ $meta['sub'] }}</div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Step 3: Vehicle Photograph (Optional) --}}
                    <div class="h-cfg-section-title" style="margin-top:2rem;">
                        <span class="h-cfg-step-num">3</span>
                        <span>Vehicle Photograph <span class="haven-optional">(optional)</span></span>
                    </div>
                    <p class="h-cfg-section-desc">Upload a high-resolution photo of your car for the showroom pedestal (JPG, PNG, WebP up to 4MB).</p>

                    <div class="h-photo-dropzone" id="photo-dropzone">
                        <input type="file" name="photo" id="photo-input" accept="image/png,image/jpeg,image/webp" style="display:none;">
                        <div class="h-dropzone-content" id="dropzone-empty" onclick="document.getElementById('photo-input').click();">
                            <div class="h-dropzone-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            <div style="font-size:0.85rem;font-weight:600;color:var(--h-text);margin-bottom:0.25rem;">
                                Click or drag vehicle photo here
                            </div>
                            <div style="font-size:0.72rem;color:var(--h-text3);">
                                If omitted, the precision studio SVG silhouette will be rendered.
                            </div>
                        </div>

                        <div class="h-dropzone-preview" id="dropzone-preview" style="display:none;">
                            <img id="photo-preview-img" src="" alt="Vehicle Preview">
                            <div class="h-preview-overlay">
                                <button type="button" class="h-preview-btn-remove" onclick="removePhotoUpload();" title="Remove Photo">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                    <span>Remove Photo</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    @error('photo')<p class="haven-error-msg" style="margin-top:0.4rem;">{{ $message }}</p>@enderror

                    {{-- Step 4: Appearance & Paint Swatches --}}
                    <div class="h-cfg-section-title" style="margin-top:2rem;">
                        <span class="h-cfg-step-num">4</span>
                        <span>Appearance & Registration</span>
                    </div>

                    <div class="haven-form-grid-2">
                        <div class="haven-form-group">
                            <label class="haven-label" for="color">Exterior Color</label>
                            <input id="color" name="color" type="text" value="{{ old('color') }}"
                                   class="haven-input @error('color') haven-input-error @enderror"
                                   placeholder="e.g. Cobalt Blue or Pearl White">
                            
                            {{-- Clickable Paint Swatches --}}
                            <div class="h-color-swatches" style="margin-top:0.6rem;">
                                @php
                                    $swatches = [
                                        ['name' => 'Pearl White',     'hex' => '#f8fafc', 'border' => '#cbd5e1'],
                                        ['name' => 'Obsidian Black',  'hex' => '#0f172a', 'border' => '#475569'],
                                        ['name' => 'Silver Metallic', 'hex' => '#94a3b8', 'border' => '#64748b'],
                                        ['name' => 'Cobalt Blue',     'hex' => '#0284c7', 'border' => '#38bdf8'],
                                        ['name' => 'Rally Red',       'hex' => '#dc2626', 'border' => '#f87171'],
                                        ['name' => 'Gunmetal Gray',   'hex' => '#475569', 'border' => '#64748b'],
                                        ['name' => 'Solar Yellow',    'hex' => '#eab308', 'border' => '#fde047'],
                                        ['name' => 'Sunset Orange',   'hex' => '#ea580c', 'border' => '#fb923c'],
                                        ['name' => 'Emerald Green',   'hex' => '#059669', 'border' => '#34d399'],
                                    ];
                                @endphp
                                @foreach($swatches as $s)
                                    <button type="button" class="h-swatch-dot" 
                                            style="background:{{ $s['hex'] }};border:1.5px solid {{ $s['border'] }};" 
                                            title="{{ $s['name'] }}" 
                                            onclick="selectColorSwatch('{{ $s['name'] }}', '{{ $s['hex'] }}')"></button>
                                @endforeach
                            </div>
                            @error('color')<p class="haven-error-msg">{{ $message }}</p>@enderror
                        </div>

                        <div class="haven-form-group">
                            <label class="haven-label" for="plate_number">Plate Number</label>
                            <div class="h-plate-input-wrap">
                                <span class="h-plate-ph-ribbon">PH</span>
                                <input id="plate_number" name="plate_number" type="text" value="{{ old('plate_number') }}"
                                       class="haven-input h-plate-input @error('plate_number') haven-input-error @enderror"
                                       placeholder="ABC 1234">
                            </div>
                            @error('plate_number')<p class="haven-error-msg">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Step 5: Engine & Drivetrain --}}
                    <div class="h-cfg-section-title" style="margin-top:2rem;">
                        <span class="h-cfg-step-num">5</span>
                        <span>Engine & Drivetrain Specs</span>
                    </div>

                    <div class="haven-form-grid-2">
                        <div class="haven-form-group">
                            <label class="haven-label" for="engine_type">Engine Type</label>
                            <input id="engine_type" name="engine_type" type="text" value="{{ old('engine_type') }}"
                                   class="haven-input @error('engine_type') haven-input-error @enderror"
                                   placeholder="e.g. 1.8L 2ZR-FE or 2.0L SkyActiv">
                            
                            {{-- Quick suggestion tags --}}
                            <div class="h-quick-chips" style="margin-top:0.45rem;">
                                @foreach(['1.3L VVT-i', '1.5L i-VTEC', '1.8L Dual VVT-i', '2.0L SkyActiv-G', '2.5L Dynamic Force'] as $eng)
                                    <button type="button" class="h-quick-chip" onclick="document.getElementById('engine_type').value='{{ $eng }}';document.getElementById('engine_type').dispatchEvent(new Event('input'));">{{ $eng }}</button>
                                @endforeach
                            </div>
                            @error('engine_type')<p class="haven-error-msg">{{ $message }}</p>@enderror
                        </div>

                        <div class="haven-form-group">
                            <label class="haven-label" for="mileage_km">Current Mileage (km)</label>
                            <div style="position:relative;">
                                <input id="mileage_km" name="mileage_km" type="number" value="{{ old('mileage_km') }}"
                                       class="haven-input @error('mileage_km') haven-input-error @enderror"
                                       placeholder="e.g. 35000" min="0" style="padding-right:3rem;">
                                <span style="position:absolute;right:0.85rem;top:50%;transform:translateY(-50%);font-size:0.75rem;color:var(--h-text3);font-weight:600;">KM</span>
                            </div>
                            @error('mileage_km')<p class="haven-error-msg">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Transmission 4-Choice Visual Cards --}}
                    <div class="haven-form-group" style="margin-top:1.25rem;">
                        <label class="haven-label">Transmission</label>
                        <input type="hidden" name="transmission" id="transmission" value="{{ old('transmission', 'Automatic') }}">
                        <div class="h-trans-grid">
                            @foreach([
                                'Automatic' => 'Standard AT with Torque Converter',
                                'Manual'    => 'Stick Shift 5/6-Speed MT',
                                'CVT'       => 'Continuously Variable Transmission',
                                'Other'     => 'Dual Clutch / Automated MT',
                            ] as $tName => $tSub)
                                <div class="h-trans-card {{ old('transmission', 'Automatic') === $tName ? 'active' : '' }}"
                                     data-trans="{{ $tName }}"
                                     onclick="selectTransmission('{{ $tName }}')">
                                    <div class="h-trans-card-title">{{ $tName }}</div>
                                    <div class="h-trans-card-sub">{{ $tSub }}</div>
                                </div>
                            @endforeach
                        </div>
                        @error('transmission')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    </div>

                    {{-- Notes --}}
                    <div class="haven-form-group" style="margin-top:1.5rem;">
                        <label class="haven-label" for="notes">Fleet Maintenance Notes <span class="haven-optional">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="haven-input @error('notes') haven-input-error @enderror"
                                  placeholder="Maintenance schedule, aftermarket oil filter, modified intake, etc.">{{ old('notes') }}</textarea>
                        @error('notes')<p class="haven-error-msg">{{ $message }}</p>@enderror
                    </div>

                    {{-- Form Footer Actions --}}
                    <div class="haven-form-actions" style="margin-top:2rem;padding-top:1.25rem;border-top:1px solid var(--h-border);">
                        <a href="{{ route('vehicles.index') }}" class="haven-btn-ghost">Cancel</a>
                        <button type="submit" class="haven-nav-cta" style="padding:0.6rem 1.6rem;font-size:0.88rem;border:none;cursor:pointer;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                            <span>Save & Register Vehicle</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right Column: Live Sticky Showroom Preview Card --}}
        <div class="h-configurator-preview-col">
            <div class="h-preview-sticky-wrap">
                <div class="h-preview-top-badge">
                    <span class="live-dot-pulse"></span>
                    <span>LIVE SHOWROOM PREVIEW</span>
                </div>

                {{-- Real-Time Rendered Showroom Card --}}
                <div class="h-clean-car-card h-preview-card" id="preview-showroom-card" style="cursor:default;">
                    
                    {{-- Top Status Pill Row --}}
                    <div class="h-card-header-clean">
                        <div style="display:flex;align-items:center;gap:0.4rem;flex-wrap:wrap;">
                            <span id="preview-badge-scope" class="h-clean-badge h-badge-scope">In Scope</span>
                            <span id="preview-badge-3d" class="h-clean-badge" style="display:none;background:rgba(14,165,233,0.18);color:#38bdf8;border:1px solid rgba(56,189,248,0.4);">★ 3D Digital Twin</span>
                        </div>
                        <button type="button" id="preview-3d-toggle" onclick="toggleShowroom3dMode()" style="display:none;background:rgba(15,23,42,0.85);color:#38bdf8;border:1px solid rgba(56,189,248,0.4);border-radius:6px;padding:0.2rem 0.55rem;font-size:0.68rem;font-family:var(--h-mono);font-weight:700;cursor:pointer;">
                            3D LIVE
                        </button>
                    </div>

                    {{-- Visual Stage: Seamless 3D Studio Car Render or Interactive 3D Sketchfab Twin --}}
                    <div class="h-clean-stage" id="preview-stage" style="position:relative;overflow:hidden;">
                        <img id="preview-live-img" src="{{ asset('images/vehicles/' . $activeBody . '.jpg') }}" alt="Live Car Photo" class="h-clean-car-img">
                        <div id="preview-3d-wrap" style="display:none;width:100%;height:220px;border-radius:10px;overflow:hidden;background:#090d16;">
                            <iframe id="preview-3d-iframe" title="3D Digital Twin Preview" style="width:100%;height:100%;border:0;" allow="autoplay; fullscreen; xr-spatial-tracking"></iframe>
                        </div>
                    </div>

                    {{-- Identity --}}
                    <div class="h-clean-body">
                        <h2 class="h-clean-title" id="preview-car-title">2024 Vehicle Model</h2>
                        <div class="h-clean-subtitle">
                            <span id="preview-car-plate">Unassigned Plate</span>
                            <span style="opacity:0.4;">·</span>
                            <span id="preview-car-body">Sedan</span>
                        </div>
                    </div>

                    {{-- Specs Row --}}
                    <div class="h-clean-specs-row">
                        <div class="h-clean-spec-item" title="Engine">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span id="preview-spec-engine">Gasoline ICE</span>
                        </div>
                        <div class="h-clean-spec-item" title="Transmission">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            <span id="preview-spec-trans">Automatic</span>
                        </div>
                        <div class="h-clean-spec-item" title="Mileage">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span id="preview-spec-mileage">0 km</span>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="h-clean-footer">
                        <div class="h-clean-rating">
                            <span class="h-gold-star" id="preview-footer-star" style="opacity:0.4;">★</span>
                            <span class="h-status-text-empty" id="preview-footer-status">Ready for Baseline</span>
                        </div>
                        <div class="h-clean-diagnose-btn" style="pointer-events:none;opacity:0.8;">
                            <span>Diagnose</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </div>
                </div>

                {{-- Live Hardware DAQ Node Pairing Notice --}}
                <div class="h-hardware-pairing-note" style="margin-top:1.25rem;">
                    <div style="display:flex;align-items:center;gap:0.45rem;margin-bottom:0.35rem;">
                        <span class="live-dot-pulse" style="background:#0284c7;"></span>
                        <strong style="font-size:0.75rem;color:var(--h-text);text-transform:uppercase;letter-spacing:0.04em;">Automatic ESP32 Node Key</strong>
                    </div>
                    <p style="font-size:0.72rem;color:var(--h-text3);margin:0;line-height:1.45;">
                        Upon registration, HAVEN AI will generate an immutable hardware key (<code style="font-family:var(--h-mono);color:var(--h-teal);font-size:0.7rem;">hvn_dev_...</code>) for flashing to your ESP32 DAQ Node.
                    </p>
                </div>

            </div>
        </div>

    </div>

    {{-- Interactive Live Configurator Script --}}
    <script>
    const bodyImages = {
        'sedan': '{{ asset("images/vehicles/sedan.jpg") }}',
        'suv': '{{ asset("images/vehicles/suv.jpg") }}',
        'hatchback': '{{ asset("images/vehicles/hatchback.jpg") }}',
        'coupe': '{{ asset("images/vehicles/coupe.jpg") }}',
        'pickup': '{{ asset("images/vehicles/pickup.jpg") }}'
    };
    let hasCustomPhoto = false;

    function selectBodyType(type) {
        document.getElementById('body_type').value = type;
        document.querySelectorAll('.h-body-type-card').forEach(c => {
            c.classList.toggle('active', c.dataset.body === type);
        });

        const labels = {
            'sedan': 'Sedan',
            'suv': 'SUV / Crossover',
            'hatchback': 'Hatchback',
            'coupe': 'Coupe / Sport',
            'pickup': 'Pickup / Truck'
        };
        const bodyLabel = labels[type] || 'Sedan';
        const pBody = document.getElementById('preview-car-body');
        if (pBody) pBody.textContent = bodyLabel;

        // Switch live 3D studio render if no custom user photo uploaded
        if (!hasCustomPhoto && bodyImages[type]) {
            const liveImg = document.getElementById('preview-live-img');
            if (liveImg) liveImg.src = bodyImages[type];
        }
    }

    function selectTransmission(trans) {
        document.getElementById('transmission').value = trans;
        document.querySelectorAll('.h-trans-card').forEach(c => {
            c.classList.toggle('active', c.dataset.trans === trans);
        });
        const pTrans = document.getElementById('preview-spec-trans');
        if (pTrans) pTrans.textContent = trans;
    }

    function selectColorSwatch(name, hex) {
        const cInput = document.getElementById('color');
        if (cInput) {
            cInput.value = name;
            cInput.dispatchEvent(new Event('input'));
        }
    }

    // Photo Dropzone and Live Preview
    const photoInput = document.getElementById('photo-input');
    const dropzone = document.getElementById('photo-dropzone');
    const emptyView = document.getElementById('dropzone-empty');
    const prevView = document.getElementById('dropzone-preview');
    const prevImg = document.getElementById('photo-preview-img');
    const liveImg = document.getElementById('preview-live-img');

    if (photoInput) {
        photoInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    hasCustomPhoto = true;
                    prevImg.src = e.target.result;
                    emptyView.style.display = 'none';
                    prevView.style.display = 'block';

                    if (liveImg) {
                        liveImg.src = e.target.result;
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }

    function removePhotoUpload() {
        hasCustomPhoto = false;
        if (photoInput) photoInput.value = '';
        if (emptyView) emptyView.style.display = 'block';
        if (prevView) prevView.style.display = 'none';
        const activeType = document.getElementById('body_type') ? document.getElementById('body_type').value : 'sedan';
        if (liveImg && bodyImages[activeType]) {
            liveImg.src = bodyImages[activeType];
        }
    }

    // Drag and drop events
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.add('drag-over');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, e => {
                e.preventDefault();
                dropzone.classList.remove('drag-over');
            });
        });
        dropzone.addEventListener('drop', e => {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                photoInput.files = e.dataTransfer.files;
                photoInput.dispatchEvent(new Event('change'));
            }
        });
    }

    let active3dUid = null;
    let show3dInPreview = true;

    function toggleShowroom3dMode() {
        show3dInPreview = !show3dInPreview;
        window.dispatchEvent(new CustomEvent('haven:catalog-updated'));
    }

    // Real-Time Live Preview Synchronization
    document.addEventListener('DOMContentLoaded', function () {
        const yearEl = document.getElementById('catalog_year') || document.getElementById('year');
        const makeSelect = document.getElementById('catalog_make_select') || document.getElementById('make_select');
        const makeInput = document.getElementById('catalog_make_input') || document.getElementById('make');
        const modelSelect = document.getElementById('catalog_model_select') || document.getElementById('model_select');
        const modelInput = document.getElementById('catalog_model_input') || document.getElementById('model');
        const nicknameEl = document.getElementById('nickname');
        const plateEl = document.getElementById('plate_number');
        const colorEl = document.getElementById('color');
        const engineEl = document.getElementById('engine_type');
        const mileageEl = document.getElementById('mileage_km');

        const pTitle = document.getElementById('preview-car-title');
        const pPlate = document.getElementById('preview-car-plate');
        const pEngine = document.getElementById('preview-spec-engine');
        const pMileage = document.getElementById('preview-spec-mileage');
        const pBadgeScope = document.getElementById('preview-badge-scope');
        const pBadge3d = document.getElementById('preview-badge-3d');
        const pToggle3d = document.getElementById('preview-3d-toggle');
        const pWrap3d = document.getElementById('preview-3d-wrap');
        const pIframe3d = document.getElementById('preview-3d-iframe');
        const pFooterStar = document.getElementById('preview-footer-star');
        const pFooterStatus = document.getElementById('preview-footer-status');

        function syncLivePreview() {
            const yr = (yearEl && yearEl.value) ? yearEl.value : '2024';
            const mk = (makeSelect && makeSelect.value && makeSelect.value !== '__custom__') 
                        ? makeSelect.value 
                        : (makeInput ? makeInput.value : '');
            const md = (modelSelect && modelSelect.value && modelSelect.value !== '__custom__')
                        ? modelSelect.value 
                        : (modelInput ? modelInput.value : '');
            
            const carName = [yr, mk, md].filter(Boolean).join(' ') || '2024 Registered Vehicle';
            if (pTitle) pTitle.textContent = carName;

            const nick = nicknameEl ? nicknameEl.value.trim() : '';
            const plt = plateEl ? plateEl.value.trim() : '';
            if (pPlate) pPlate.textContent = nick || plt || 'Unassigned Plate';

            const eng = engineEl ? engineEl.value.trim() : '';
            if (pEngine) pEngine.textContent = eng ? eng.substring(0, 15) : 'Gasoline ICE';

            const mil = mileageEl ? parseInt(mileageEl.value, 10) : 0;
            if (pMileage) pMileage.textContent = isNaN(mil) || mil === 0 ? '0 km' : Number(mil).toLocaleString() + ' km';

            // Scope Check
            const yNum = parseInt(yr, 10);
            const isDieselEv = /diesel|tdi|crdi|cummins|duramax|hybrid|phev|ev|electric/i.test(eng);
            const inScope = (yNum >= 2015) && !isDieselEv;

            if (pBadgeScope) {
                if (inScope) {
                    pBadgeScope.className = 'h-clean-badge h-badge-scope';
                    pBadgeScope.textContent = 'In Scope';
                } else {
                    pBadgeScope.className = 'h-clean-badge h-badge-warn';
                    pBadgeScope.textContent = 'Advisory';
                }
            }

            // 3D Digital Twin Check & Automatic Showroom Prompt
            const uid = (typeof window.resolveHaven3dUid === 'function')
                ? window.resolveHaven3dUid(mk, md)
                : null;

            if (uid) {
                if (pBadge3d) pBadge3d.style.display = 'inline-flex';
                if (pToggle3d) {
                    pToggle3d.style.display = 'inline-block';
                    pToggle3d.textContent = (show3dInPreview && !hasCustomPhoto) ? 'SWITCH TO 2D' : 'VIEW 3D TWIN';
                }
                if (pFooterStar) pFooterStar.style.opacity = '1';
                if (pFooterStatus) pFooterStatus.textContent = '3D Digital Twin Ready';

                if (show3dInPreview && !hasCustomPhoto) {
                    if (liveImg) liveImg.style.display = 'none';
                    if (pWrap3d) pWrap3d.style.display = 'block';
                    if (pIframe3d && active3dUid !== uid) {
                        active3dUid = uid;
                        pIframe3d.src = `https://sketchfab.com/models/${uid}/embed?autospin=0.2&autostart=1&ui_infos=0&ui_watermark=0&ui_controls=1&transparent=1&dnt=1`;
                    }
                } else {
                    if (pWrap3d) pWrap3d.style.display = 'none';
                    if (liveImg) liveImg.style.display = 'block';
                }
            } else {
                if (pBadge3d) pBadge3d.style.display = 'none';
                if (pToggle3d) pToggle3d.style.display = 'none';
                if (pWrap3d) pWrap3d.style.display = 'none';
                if (liveImg) liveImg.style.display = 'block';
                if (pFooterStar) pFooterStar.style.opacity = '0.4';
                if (pFooterStatus) pFooterStatus.textContent = 'Ready for Baseline (2D)';
            }
        }

        [yearEl, nicknameEl, plateEl, colorEl, engineEl, mileageEl].forEach(el => {
            if (el) {
                el.addEventListener('input', syncLivePreview);
                el.addEventListener('change', syncLivePreview);
            }
        });
        if (makeSelect) makeSelect.addEventListener('change', () => setTimeout(syncLivePreview, 100));
        if (makeInput) makeInput.addEventListener('input', syncLivePreview);
        if (modelSelect) modelSelect.addEventListener('change', syncLivePreview);
        if (modelInput) modelInput.addEventListener('input', syncLivePreview);
        window.addEventListener('haven:catalog-updated', syncLivePreview);

        setTimeout(syncLivePreview, 350);
    });
    </script>

</x-app-layout>
