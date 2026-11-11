@php
    $makeVal  = old('make', $vehicle->make ?? '');
    $modelVal = old('model', $vehicle->model ?? '');
    $yearVal  = old('year', $vehicle->year ?? date('Y'));
@endphp

{{-- ── UMak CET Capstone Research Scope Banner ── --}}
<div class="h-notice" style="margin-bottom:1.5rem;background:rgba(2,132,199,0.08);border:1px solid rgba(56,189,248,0.25);">
    <svg class="h-notice-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--h-teal)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    <div style="font-size:0.75rem;line-height:1.45;color:var(--h-text);">
        <strong style="color:var(--h-teal);">UMak CET Capstone Scope Guide:</strong>
        HAVEN AI acoustic and vibration classifiers are calibrated for <strong>spark-ignition gasoline passenger vehicles (Model Year 2015 and newer)</strong>.
        Diesel, hybrid, EV powertrains, and commercial vehicles fall outside primary research baseline boundaries.
    </div>
</div>

<div class="haven-form-section-label">VIN Lookup <span class="haven-optional">(optional)</span></div>
<div class="haven-form-group" style="margin-bottom:1.25rem;">
    <label class="haven-label" for="vin_lookup">Decode from VIN</label>
    <div style="display:flex;gap:0.5rem;align-items:stretch;">
        <input id="vin_lookup" type="text" maxlength="17" autocomplete="off" spellcheck="false"
               class="haven-input" placeholder="e.g. 1HGCM82633A004352"
               style="text-transform:uppercase;letter-spacing:0.04em;font-variant-numeric:tabular-nums;">
        <button type="button" id="btn-decode-vin" class="haven-btn-secondary" style="width:auto;flex-shrink:0;">
            Decode
        </button>
    </div>
    <p id="vin-status" class="haven-optional" style="margin-top:0.4rem;min-height:1em;"></p>
</div>

<div class="haven-form-section-label">Vehicle Identity</div>
<div class="haven-form-grid-2">
    <div class="haven-form-group">
        <label class="haven-label" for="year">Year <span class="haven-required">*</span></label>
        <input id="year" name="year" type="number" value="{{ $yearVal }}"
               class="haven-input @error('year') haven-input-error @enderror"
               min="1900" max="{{ date('Y') + 1 }}" required>
        <div id="scope-warning-box" style="display:none;margin-top:0.4rem;padding:0.4rem 0.6rem;border-radius:4px;background:var(--h-amber-bg);border:1px solid rgba(245,158,11,0.25);">
            <div style="display:flex;align-items:center;gap:0.4rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span id="scope-warning-text" style="font-size:0.69rem;color:var(--h-text);font-weight:500;"></span>
            </div>
        </div>
        @error('year')<p class="haven-error-msg">{{ $message }}</p>@enderror
    </div>
    <div class="haven-form-group">
        <label class="haven-label" for="nickname">Nickname <span class="haven-optional">(optional)</span></label>
        <input id="nickname" name="nickname" type="text" value="{{ old('nickname', $vehicle->nickname ?? '') }}"
               class="haven-input @error('nickname') haven-input-error @enderror"
               placeholder="e.g. My Daily Driver">
        @error('nickname')<p class="haven-error-msg">{{ $message }}</p>@enderror
    </div>
    <div class="haven-form-group">
        <label class="haven-label" for="make_select">Make <span class="haven-required">*</span></label>
        <div class="h-quick-chips" style="margin-bottom:0.45rem;">
            @foreach(['Toyota', 'Honda', 'Mitsubishi', 'Nissan', 'Ford', 'Hyundai', 'Mazda', 'Suzuki'] as $b)
                <button type="button" class="h-quick-chip" onclick="quickSelectMake('{{ $b }}')">{{ $b }}</button>
            @endforeach
        </div>
        <select id="make_select" class="haven-input haven-select @error('make') haven-input-error @enderror">
            <option value="">Loading catalog…</option>
        </select>
        <input id="make" name="make" type="text" value="{{ $makeVal }}" required
               class="haven-input" style="display:none;margin-top:0.5rem;" placeholder="Type make">
        @error('make')<p class="haven-error-msg">{{ $message }}</p>@enderror
    </div>
    <div class="haven-form-group">
        <label class="haven-label" for="model_select">Model <span class="haven-required">*</span></label>
        <select id="model_select" class="haven-input haven-select @error('model') haven-input-error @enderror" disabled>
            <option value="">Select a make first</option>
        </select>
        <input id="model" name="model" type="text" value="{{ $modelVal }}" required
               class="haven-input" style="display:none;margin-top:0.5rem;" placeholder="Type model">
        @error('model')<p class="haven-error-msg">{{ $message }}</p>@enderror
    </div>
</div>

<script>
(function () {
    const CUSTOM = '__custom__';
    const urls = {
        makes:  @json(route('vehicles.catalog.makes')),
        models: @json(route('vehicles.catalog.models')),
        vin:    @json(route('vehicles.catalog.vin')),
    };
    const initial = {
        make:  @json($makeVal),
        model: @json($modelVal),
        year:  @json((string) $yearVal),
    };

    const yearEl       = document.getElementById('year');
    const scopeWarnBox = document.getElementById('scope-warning-box');
    const scopeWarnTxt = document.getElementById('scope-warning-text');
    const makeSelect   = document.getElementById('make_select');
    const modelSelect  = document.getElementById('model_select');
    const makeInput    = document.getElementById('make');
    const modelInput   = document.getElementById('model');
    const vinInput     = document.getElementById('vin_lookup');
    const vinBtn       = document.getElementById('btn-decode-vin');
    const vinStatus    = document.getElementById('vin-status');
    const engineEl     = document.getElementById('engine_type');
    const transEl      = document.getElementById('transmission');

    function checkScopeValidation() {
        if (!yearEl || !scopeWarnBox) return;
        const y = parseInt(yearEl.value, 10);
        const engineVal = (engineEl ? engineEl.value : '').toLowerCase();
        let warnings = [];

        if (!isNaN(y) && y < 2015) {
            warnings.push(`Model year ${y} is pre-2015 (study scope: 2015+ gasoline passenger cars).`);
        }
        if (/diesel|tdi|crdi|cummins|duramax|hybrid|phev|ev|electric/.test(engineVal)) {
            warnings.push(`Non-gasoline powertrain detected. Diesel/EV/Hybrid are excluded.`);
        }

        if (warnings.length > 0) {
            scopeWarnTxt.textContent = 'Advisory: ' + warnings.join(' ');
            scopeWarnBox.style.display = 'block';
        } else {
            scopeWarnBox.style.display = 'none';
        }
    }

    if (yearEl) {
        yearEl.addEventListener('input', checkScopeValidation);
        yearEl.addEventListener('change', checkScopeValidation);
    }
    if (engineEl) {
        engineEl.addEventListener('input', checkScopeValidation);
        engineEl.addEventListener('change', checkScopeValidation);
    }
    setTimeout(checkScopeValidation, 300);

    function setStatus(text, color) {
        vinStatus.textContent = text;
        vinStatus.style.color = color || 'var(--h-text3)';
    }

    function showCustom(kind, show) {
        const select = kind === 'make' ? makeSelect : modelSelect;
        const input  = kind === 'make' ? makeInput  : modelInput;
        input.style.display = show ? 'block' : 'none';
        if (show) {
            input.focus();
        }
    }

    function syncFromSelect(kind) {
        const select = kind === 'make' ? makeSelect : modelSelect;
        const input  = kind === 'make' ? makeInput  : modelInput;
        if (select.value === CUSTOM) {
            showCustom(kind, true);
            if (!input.value) input.value = '';
        } else {
            showCustom(kind, false);
            input.value = select.value;
        }
        window.dispatchEvent(new CustomEvent('haven:catalog-updated'));
    }

    const POPULAR_MAKES = [
        'Toyota', 'Honda', 'Mitsubishi', 'Nissan', 'Ford', 'Hyundai',
        'Kia', 'Suzuki', 'Mazda', 'Subaru', 'Chevrolet', 'Isuzu',
        'Volkswagen', 'BMW', 'Mercedes-Benz', 'Audi', 'Lexus',
        'Geely', 'MG', 'Changan', 'Chery', 'BYD', 'Peugeot', 'Volvo',
    ];

    const HAVEN_3D_CATALOG = @json(\App\Models\Vehicle::SKETCHFAB_CATALOG);
    window.HAVEN_3D_CATALOG = HAVEN_3D_CATALOG;

    window.resolveHaven3dUid = function (make, model) {
        const mk = (make || '').trim().toLowerCase();
        const md = (model || '').trim().toLowerCase();
        if (!md) return null;
        const normMd = md.replace(/[- ]+/g, '');

        for (const [catMake, models] of Object.entries(HAVEN_3D_CATALOG)) {
            if (!mk || catMake.toLowerCase() === mk || mk.includes(catMake.toLowerCase())) {
                for (const [catModel, uid] of Object.entries(models)) {
                    const cm = catModel.toLowerCase();
                    const normCm = cm.replace(/[- ]+/g, '');
                    if (md === cm || normMd === normCm || md.includes(cm) || (normCm && normMd.includes(normCm))) {
                        return uid;
                    }
                }
            }
        }
        for (const models of Object.values(HAVEN_3D_CATALOG)) {
            for (const [catModel, uid] of Object.entries(models)) {
                const cm = catModel.toLowerCase();
                const normCm = cm.replace(/[- ]+/g, '');
                if (normCm.length >= 3 && (md === cm || normMd === normCm || md.includes(cm))) {
                    return uid;
                }
            }
        }
        return null;
    };

    function getLocal3dModelsForMake(make) {
        const mk = (make || '').trim().toLowerCase();
        for (const [catMake, models] of Object.entries(HAVEN_3D_CATALOG)) {
            if (catMake.toLowerCase() === mk) {
                return Object.keys(models);
            }
        }
        return [];
    }

    function fillSelect(select, items, placeholder, selected, isMakeSelect = false) {
        const current = (selected || '').trim();
        select.innerHTML = '';
        const ph = document.createElement('option');
        ph.value = '';
        ph.textContent = placeholder;
        select.appendChild(ph);

        let matched = false;

        if (isMakeSelect) {
            // Group 1: Popular Passenger Vehicles
            const popGroup = document.createElement('optgroup');
            popGroup.label = '⭐ Popular Passenger Vehicles';

            const availablePop = [];

            POPULAR_MAKES.forEach(popName => {
                const found = items.find(item => item.toLowerCase() === popName.toLowerCase());
                availablePop.push(found || popName);
            });

            availablePop.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                const count3d = getLocal3dModelsForMake(name).length;
                opt.textContent = count3d > 0 ? `${name} (${count3d} 3D Models)` : name;
                if (current && name.toLowerCase() === current.toLowerCase()) {
                    opt.selected = true;
                    matched = true;
                }
                popGroup.appendChild(opt);
            });
            select.appendChild(popGroup);

            // Note: Only popular makes are displayed as per project scope; the full A–Z list has been removed.
            // The full A–Z list has been removed per project scope; only popular makes are displayed.

        } else {
            const activeMake = (makeSelect && makeSelect.value !== CUSTOM) ? makeSelect.value : (makeInput ? makeInput.value : '');
            const local3d = getLocal3dModelsForMake(activeMake);
            const allNamesMap = new Map();
            local3d.forEach(m => allNamesMap.set(m.toLowerCase(), m));
            items.forEach(m => {
                if (!allNamesMap.has(m.toLowerCase())) {
                    allNamesMap.set(m.toLowerCase(), m);
                }
            });
            const mergedItems = Array.from(allNamesMap.values());

            const twinGroup = document.createElement('optgroup');
            twinGroup.label = '★ 3D Digital Twin Ready';
            const stdGroup = document.createElement('optgroup');
            stdGroup.label = 'Standard Catalog (2D Blueprint)';

            let hasTwin = false;
            let hasStd = false;

            mergedItems.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                const uid = window.resolveHaven3dUid(activeMake, name);
                if (uid) {
                    opt.textContent = `★ ${name} — 3D Twin Ready`;
                    twinGroup.appendChild(opt);
                    hasTwin = true;
                } else {
                    opt.textContent = name;
                    stdGroup.appendChild(opt);
                    hasStd = true;
                }
                if (current && name.toLowerCase() === current.toLowerCase()) {
                    opt.selected = true;
                    matched = true;
                }
            });

            if (hasTwin) select.appendChild(twinGroup);
            if (hasStd) select.appendChild(stdGroup);
        }

        const other = document.createElement('option');
        other.value = CUSTOM;
        other.textContent = 'Other (type manually)';
        if (current && !matched) other.selected = true;
        select.appendChild(other);

        return matched;
    }

    async function fetchJson(url) {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.error || 'Lookup failed.');
        }
        return data;
    }

    async function loadMakes(preferred) {
        makeSelect.disabled = true;
        try {
            const data = await fetchJson(urls.makes);
            const matched = fillSelect(makeSelect, data.makes || [], '— Select make —', preferred, true);
            makeSelect.disabled = false;
            if (preferred && !matched) {
                showCustom('make', true);
                makeInput.value = preferred;
            } else {
                syncFromSelect('make');
            }
        } catch (e) {
            const matched = fillSelect(makeSelect, POPULAR_MAKES, '— Select make —', preferred, true);
            makeSelect.disabled = false;
            if (preferred && !matched) {
                showCustom('make', true);
                makeInput.value = preferred;
            } else {
                syncFromSelect('make');
            }
        }
    }

    async function loadModels(make, preferred) {
        if (!make || make === CUSTOM) {
            fillSelect(modelSelect, [], 'Type model', preferred);
            modelSelect.value = CUSTOM;
            modelSelect.disabled = false;
            showCustom('model', true);
            if (preferred) modelInput.value = preferred;
            window.dispatchEvent(new CustomEvent('haven:catalog-updated'));
            return;
        }

        modelSelect.disabled = true;
        modelSelect.innerHTML = '<option>Loading models…</option>';
        const year = yearEl.value;
        const qs = new URLSearchParams({ make });
        if (year) qs.set('year', year);

        try {
            const data = await fetchJson(urls.models + '?' + qs.toString());
            const matched = fillSelect(modelSelect, data.models || [], '— Select model —', preferred);
            modelSelect.disabled = false;
            if (preferred && !matched) {
                showCustom('model', true);
                modelInput.value = preferred;
            } else {
                syncFromSelect('model');
            }
        } catch (e) {
            const fallbackModels = getLocal3dModelsForMake(make);
            if (fallbackModels.length > 0) {
                const matched = fillSelect(modelSelect, fallbackModels, '— Select model —', preferred);
                modelSelect.disabled = false;
                if (preferred && !matched) {
                    showCustom('model', true);
                    modelInput.value = preferred;
                } else {
                    syncFromSelect('model');
                }
            } else {
                fillSelect(modelSelect, [], 'Catalog unavailable — type manually', preferred);
                modelSelect.value = CUSTOM;
                modelSelect.disabled = false;
                showCustom('model', true);
                if (preferred) modelInput.value = preferred;
            }
        }
        window.dispatchEvent(new CustomEvent('haven:catalog-updated'));
    }

    window.quickSelectMake = function (brand) {
        if (!makeSelect) return;
        makeSelect.value = brand;
        if (makeSelect.value !== brand) {
            showCustom('make', true);
            makeInput.value = brand;
        } else {
            showCustom('make', false);
            makeInput.value = brand;
        }
        modelInput.value = '';
        showCustom('model', false);
        loadModels(brand, '');
        makeSelect.dispatchEvent(new Event('change'));
    };

    makeSelect.addEventListener('change', () => {
        syncFromSelect('make');
        modelInput.value = '';
        showCustom('model', false);
        loadModels(makeSelect.value === CUSTOM ? '' : makeSelect.value, '');
    });

    modelSelect.addEventListener('change', () => syncFromSelect('model'));
    makeInput.addEventListener('input', () => { if (makeSelect.value === CUSTOM) makeInput.value = makeInput.value; });
    yearEl.addEventListener('change', () => {
        const make = makeSelect.value === CUSTOM ? makeInput.value : makeSelect.value;
        if (make) loadModels(make, modelInput.value);
    });

    async function decodeVin() {
        const vin = (vinInput.value || '').replace(/[^A-Za-z0-9]/g, '');
        vinInput.value = vin.toUpperCase();
        if (vin.length !== 17) {
            setStatus('Enter a 17-character VIN.', 'var(--h-amber)');
            return;
        }
        vinBtn.disabled = true;
        setStatus('Decoding VIN via NHTSA vPIC…');
        try {
            const data = await fetchJson(urls.vin + '?vin=' + encodeURIComponent(vin));
            if (data.year) yearEl.value = data.year;
            if (data.engine_type && engineEl) engineEl.value = data.engine_type;
            if (data.transmission && transEl) transEl.value = data.transmission;

            await loadMakes(data.make || '');
            await loadModels(data.make || '', data.model || '');

            const bits = [data.year, data.make, data.model].filter(Boolean).join(' ');
            setStatus(bits ? 'Filled from VIN: ' + bits : 'VIN decoded.', 'var(--h-teal)');
        } catch (e) {
            setStatus(e.message || 'Could not decode this VIN.', 'var(--h-red)');
        } finally {
            vinBtn.disabled = false;
        }
    }

    vinBtn.addEventListener('click', decodeVin);
    vinInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            decodeVin();
        }
    });

    loadMakes(initial.make).then(() => {
        const make = makeSelect.value === CUSTOM ? makeInput.value : makeSelect.value;
        if (make) loadModels(make, initial.model);
    });
})();
</script>
