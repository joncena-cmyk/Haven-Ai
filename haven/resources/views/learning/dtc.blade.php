<x-app-layout title="OBD-II DTC Directory">

    {{-- Breadcrumb --}}
    <div class="h-breadcrumb">
        <a href="{{ route('dashboard') }}" class="h-breadcrumb-link">Dashboard</a>
        <span class="h-breadcrumb-sep">/</span>
        <a href="{{ route('learning.index') }}" class="h-breadcrumb-link">Learning Modules</a>
        <span class="h-breadcrumb-sep">/</span>
        <span class="h-breadcrumb-current">OBD-II DTC Directory</span>
    </div>

    {{-- Header --}}
    <div class="h-card" style="padding:1.75rem;margin-bottom:1.5rem;background:var(--h-surface);">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
                <div style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.2rem 0.6rem;background:var(--h-teal-bg);border:1px solid var(--h-teal-ring);border-radius:999px;font-size:0.68rem;font-weight:600;color:var(--h-teal);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.5rem;">
                    Diagnostic Reference Manual
                </div>
                <h1 style="font-size:1.5rem;font-weight:800;color:var(--h-text);letter-spacing:-0.03em;margin:0 0 0.35rem;">
                    OBD-II Diagnostic Trouble Codes (DTC)
                </h1>
                <p style="font-size:0.875rem;color:var(--h-text2);margin:0;">
                    Quick technical reference for powertrain trouble codes, ignition misfires, knock sensor circuits, and thermal management.
                </p>
            </div>

            <form method="GET" action="{{ route('learning.dtc') }}" class="lrn-dtc-search-form">
                <div style="position:relative;flex:1 1 180px;min-width:0;">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search code or symptom..." class="haven-input" style="font-size:0.85rem;padding:0.5rem 0.85rem 0.5rem 2.2rem;min-height:42px;box-sizing:border-box;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--h-text3)" stroke-width="2" style="position:absolute;left:0.75rem;top:50%;transform:translateY(-50%);">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>
                <button type="submit" class="haven-btn-primary" style="width:auto;padding:0.55rem 1.1rem;flex-shrink:0;min-height:42px;">Filter</button>
                @if($search)
                    <a href="{{ route('learning.dtc') }}" class="h-btn-sm h-btn-sm-default" style="align-self:center;flex-shrink:0;min-height:42px;display:inline-flex;align-items:center;padding:0.55rem 0.85rem;">Reset</a>
                @endif
            </form>
        </div>

        {{-- Result count --}}
        <div style="margin-top:0.75rem;font-size:0.78rem;color:var(--h-text3);">
            Showing <strong style="color:var(--h-text);">{{ count($dtcs) }}</strong> trouble code{{ count($dtcs) !== 1 ? 's' : '' }}
            @if($search) matching "<strong style="color:var(--h-teal);">{{ $search }}</strong>" @endif
        </div>
    </div>

    {{-- DTC List Cards --}}
    <div style="display:flex;flex-direction:column;gap:0.75rem;">
        @forelse($dtcs as $index => $item)
            <div class="h-card dtc-card" id="dtc-card-{{ $index }}" style="background:var(--h-surface);border:1px solid var(--h-border);overflow:hidden;">

                {{-- Card Header (always visible / click to expand) --}}
                <button type="button" class="dtc-card-header" onclick="toggleDtc({{ $index }})"
                        style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.25rem;background:transparent;border:none;cursor:pointer;font-family:inherit;text-align:left;gap:0.75rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;flex:1;min-width:0;">
                        {{-- Code badge --}}
                        <span style="font-family:monospace;font-size:1rem;font-weight:800;color:var(--h-teal);background:var(--h-surface2);padding:0.2rem 0.55rem;border-radius:var(--h-radius);border:1px solid var(--h-border);flex-shrink:0;white-space:nowrap;">
                            {{ $item['code'] }}
                        </span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:0.9rem;font-weight:700;color:var(--h-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                {{ $item['title'] }}
                            </div>
                            <div style="font-size:0.7rem;color:var(--h-text3);text-transform:uppercase;letter-spacing:0.05em;font-weight:600;margin-top:0.1rem;">
                                {{ $item['system'] }}
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.6rem;flex-shrink:0;">
                        @if($item['severity_color'] === 'red')
                            <span class="h-badge h-badge-red">{{ $item['severity'] }}</span>
                        @elseif($item['severity_color'] === 'amber')
                            <span class="h-badge h-badge-amber">{{ $item['severity'] }}</span>
                        @else
                            <span class="h-badge h-badge-teal">{{ $item['severity'] }}</span>
                        @endif
                        <svg id="dtc-chevron-{{ $index }}" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--h-text3)" stroke-width="2" style="flex-shrink:0;transition:transform 0.2s;">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                </button>

                {{-- Expandable body --}}
                <div id="dtc-body-{{ $index }}" style="display:none;padding:0 1.25rem 1.25rem;">
                    <div style="border-top:1px solid var(--h-border);padding-top:1rem;display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:1rem;">
                        <div>
                            <div style="font-size:0.7rem;font-weight:600;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.4rem;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:0.2rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Observable Symptoms
                            </div>
                            <div style="font-size:0.84rem;color:var(--h-text2);line-height:1.55;">{{ $item['symptoms'] }}</div>
                        </div>
                        <div>
                            <div style="font-size:0.7rem;font-weight:600;color:var(--h-text3);text-transform:uppercase;margin-bottom:0.4rem;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:0.2rem;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                Probable Root Causes
                            </div>
                            <div style="font-size:0.84rem;color:var(--h-text2);line-height:1.55;">{{ $item['causes'] }}</div>
                        </div>
                    </div>
                    {{-- Copy code button --}}
                    <div style="margin-top:0.85rem;display:flex;justify-content:flex-end;">
                        <button type="button" onclick="copyDtcCode('{{ $item['code'] }}', this)"
                                style="display:inline-flex;align-items:center;gap:0.35rem;padding:0.3rem 0.65rem;border-radius:var(--h-radius);font-size:0.72rem;font-weight:600;border:1px solid var(--h-border);background:var(--h-surface2);color:var(--h-text2);cursor:pointer;font-family:inherit;transition:all 0.15s;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            Copy Code
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="h-card" style="padding:3rem 1.5rem;text-align:center;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--h-text3)" stroke-width="1.5" style="margin:0 auto 1rem;display:block;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p style="color:var(--h-text2);font-size:0.9rem;margin:0 0 0.75rem;">No OBD-II codes matched your search "<strong>{{ $search }}</strong>".</p>
                <a href="{{ route('learning.dtc') }}" class="h-btn-sm h-btn-sm-teal">View All Trouble Codes</a>
            </div>
        @endforelse
    </div>

    <script>
        function toggleDtc(index) {
            const body = document.getElementById('dtc-body-' + index);
            const chevron = document.getElementById('dtc-chevron-' + index);
            const isOpen = body.style.display !== 'none';
            body.style.display = isOpen ? 'none' : 'block';
            chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
        }

        function copyDtcCode(code, btn) {
            navigator.clipboard.writeText(code).then(() => {
                const orig = btn.innerHTML;
                btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
                btn.style.color = 'var(--h-green)';
                btn.style.borderColor = 'rgba(34,197,94,0.4)';
                setTimeout(() => {
                    btn.innerHTML = orig;
                    btn.style.color = '';
                    btn.style.borderColor = '';
                }, 1800);
            });
        }

        // Auto-expand if only one result
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.dtc-card');
            if (cards.length === 1) toggleDtc(0);
        });
    </script>

</x-app-layout>
