@props([
    'type' => 'sedan',
    'color' => '#38bdf8',
    'class' => 'h-car-silhouette',
    'width' => '100%',
    'height' => '100%',
])

@php
    $type = strtolower($type ?? 'sedan');
@endphp

<svg class="{{ $class }}" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:{{ $width }};height:{{ $height }};overflow:visible;">
    <defs>
        {{-- Studio Underglow & Rim Lighting Gradient --}}
        <linearGradient id="body-grad-{{ $type }}" x1="0%" y1="0%" x2="100%" y2="80%">
            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.18"/>
            <stop offset="50%" stop-color="#64748b" stop-opacity="0.12"/>
            <stop offset="100%" stop-color="#0f172a" stop-opacity="0.4"/>
        </linearGradient>

        <linearGradient id="accent-glow-{{ $type }}" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="{{ $color }}" stop-opacity="0.9"/>
            <stop offset="100%" stop-color="#38bdf8" stop-opacity="0.4"/>
        </linearGradient>

        <radialGradient id="wheel-rim" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#94a3b8"/>
            <stop offset="60%" stop-color="#334155"/>
            <stop offset="100%" stop-color="#0f172a"/>
        </radialGradient>
    </defs>

    {{-- Ground Shadow --}}
    <ellipse cx="160" cy="126" rx="142" ry="7" fill="#000000" fill-opacity="0.45"/>

    @if($type === 'suv')
        {{-- SUV / CROSSOVER SILHOUETTE --}}
        <!-- Roof Rail -->
        <path d="M96 28 L206 28" stroke="#64748b" stroke-width="2.5" stroke-linecap="round"/>
        <line x1="104" y1="28" x2="104" y2="33" stroke="#64748b" stroke-width="2"/>
        <line x1="198" y1="28" x2="198" y2="33" stroke="#64748b" stroke-width="2"/>

        <!-- Main Body Hull -->
        <path d="M26 102 
                 L34 82 Q42 66 64 63 
                 L108 52 
                 L132 32 Q142 29 162 29 
                 L216 29 Q228 30 234 38 
                 L264 68 
                 L284 74 Q298 77 302 90 
                 L304 104 Q304 110 296 112 
                 L262 112 
                 A 24 24 0 0 0 216 112 
                 L112 112 
                 A 24 24 0 0 0 66 112 
                 L32 112 Q24 112 26 102 Z" 
              fill="url(#body-grad-{{ $type }})" stroke="rgba(255,255,255,0.3)" stroke-width="1.8"/>

        <!-- Greenhouse / Cabin Glass -->
        <path d="M136 36 L116 54 L170 54 L170 36 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.25)" stroke-width="1.2"/>
        <path d="M176 36 L176 54 L222 54 L212 36 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.25)" stroke-width="1.2"/>
        <path d="M228 39 L254 66 L228 66 Z" fill="rgba(56,189,248,0.18)" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>

        <!-- Shoulder Line & Body Crease -->
        <path d="M42 78 Q160 76 294 82" stroke="url(#accent-glow-{{ $type }})" stroke-width="2.2" stroke-linecap="round"/>

        <!-- Headlight & Tail Light -->
        <path d="M288 81 L303 86 L298 92 Z" fill="{{ $color }}" opacity="0.95"/>
        <path d="M28 85 L35 84 L34 94 L27 92 Z" fill="#ef4444" opacity="0.95"/>

    @elseif($type === 'coupe')
        {{-- SPORT COUPE SILHOUETTE --}}
        <!-- Fastback Low Roof Body Hull -->
        <path d="M22 104 
                 L34 88 Q48 76 72 74 
                 L132 58 
                 L166 42 Q182 38 214 42 
                 L248 64 
                 L282 76 Q302 81 306 93 
                 L308 106 Q308 112 298 112 
                 L264 112 
                 A 24 24 0 0 0 218 112 
                 L114 112 
                 A 24 24 0 0 0 68 112 
                 L28 112 Q20 112 22 104 Z" 
              fill="url(#body-grad-{{ $type }})" stroke="rgba(255,255,255,0.3)" stroke-width="1.8"/>

        <!-- Sleek Coupe Glass -->
        <path d="M142 60 L170 46 L216 46 L242 64 L180 64 Z" fill="rgba(56,189,248,0.25)" stroke="rgba(255,255,255,0.25)" stroke-width="1.2"/>

        <!-- Aerodynamic Side Skirt Crease -->
        <path d="M38 88 Q165 80 298 88" stroke="url(#accent-glow-{{ $type }})" stroke-width="2.4" stroke-linecap="round"/>
        <path d="M72 106 L214 106" stroke="#38bdf8" stroke-width="1.5" stroke-opacity="0.6"/>

        <!-- Headlight & Tail Light -->
        <polygon points="296,87 307,91 301,96" fill="{{ $color }}"/>
        <polygon points="23,90 32,89 31,98 22,96" fill="#ef4444"/>

    @elseif($type === 'hatchback')
        {{-- HATCHBACK SILHOUETTE --}}
        <path d="M26 102 
                 L32 76 Q42 56 60 52 
                 L118 42 Q138 38 178 38 
                 L222 40 Q232 44 240 54 
                 L268 74 Q288 78 296 90 
                 L298 104 Q298 112 290 112 
                 L256 112 
                 A 24 24 0 0 0 210 112 
                 L110 112 
                 A 24 24 0 0 0 64 112 
                 L30 112 Q24 112 26 102 Z" 
              fill="url(#body-grad-{{ $type }})" stroke="rgba(255,255,255,0.3)" stroke-width="1.8"/>

        <!-- Cabin Windows -->
        <path d="M124 44 L70 54 L62 70 L136 70 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.2)" stroke-width="1.2"/>
        <path d="M142 44 L142 70 L226 70 L216 44 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.2)" stroke-width="1.2"/>

        <path d="M38 78 Q150 74 288 82" stroke="url(#accent-glow-{{ $type }})" stroke-width="2.2" stroke-linecap="round"/>
        <polygon points="286,81 297,85 292,91" fill="{{ $color }}"/>
        <polygon points="27,82 34,81 33,92 26,90" fill="#ef4444"/>

    @elseif($type === 'pickup')
        {{-- PICKUP / TRUCK SILHOUETTE --}}
        <path d="M24 102 
                 L28 78 L126 78 
                 L134 50 Q144 36 172 36 
                 L222 36 Q232 40 238 52 
                 L268 72 
                 L294 76 Q304 80 306 94 
                 L306 104 Q306 112 298 112 
                 L264 112 
                 A 25 25 0 0 0 216 112 
                 L114 112 
                 A 25 25 0 0 0 66 112 
                 L28 112 Q22 112 24 102 Z" 
              fill="url(#body-grad-{{ $type }})" stroke="rgba(255,255,255,0.3)" stroke-width="1.8"/>

        <!-- Truck Cab Glass -->
        <path d="M142 42 L136 72 L180 72 L180 42 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.2)" stroke-width="1.2"/>
        <path d="M186 42 L186 72 L234 72 L224 42 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.2)" stroke-width="1.2"/>

        <!-- Cargo Bed Partition -->
        <line x1="128" y1="78" x2="128" y2="108" stroke="#64748b" stroke-width="2"/>
        <path d="M30 84 Q160 80 298 84" stroke="url(#accent-glow-{{ $type }})" stroke-width="2.2" stroke-linecap="round"/>
        <polygon points="296,82 305,86 300,92" fill="{{ $color }}"/>
        <polygon points="24,84 31,83 30,94 23,92" fill="#ef4444"/>

    @else
        {{-- SEDAN / DEFAULT PASSENGER CAR --}}
        <!-- Sleek 3-Box Executive Sedan Silhouette -->
        <path d="M24 102 
                 L34 82 Q44 72 68 70 
                 L116 54 
                 L146 36 Q158 34 196 34 
                 L230 40 Q240 48 248 58 
                 L272 72 
                 L292 76 Q304 80 306 92 
                 L306 104 Q306 112 298 112 
                 L262 112 
                 A 24 24 0 0 0 216 112 
                 L112 112 
                 A 24 24 0 0 0 66 112 
                 L28 112 Q22 112 24 102 Z" 
              fill="url(#body-grad-{{ $type }})" stroke="rgba(255,255,255,0.32)" stroke-width="1.8"/>

        <!-- Sedan Windows -->
        <path d="M124 56 L150 40 L182 40 L182 66 L118 66 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.22)" stroke-width="1.2"/>
        <path d="M188 40 L224 44 L240 66 L188 66 Z" fill="rgba(56,189,248,0.22)" stroke="rgba(255,255,255,0.22)" stroke-width="1.2"/>

        <!-- Flowing Dynamic Shoulder Line -->
        <path d="M38 78 Q160 72 298 80" stroke="url(#accent-glow-{{ $type }})" stroke-width="2.3" stroke-linecap="round"/>

        <!-- Aerodynamic Lower Crease -->
        <path d="M72 104 Q162 100 212 104" stroke="rgba(255,255,255,0.2)" stroke-width="1.4"/>

        <!-- Projector Headlight & LED Taillight -->
        <polygon points="296,80 305,84 300,90" fill="{{ $color }}"/>
        <polygon points="25,84 34,83 33,94 24,92" fill="#ef4444"/>
    @endif

    {{-- Universal High-Detail Alloy Wheels --}}
    <!-- Rear Wheel Assembly (Left) -->
    <g transform="translate(89, 112)">
        <circle cx="0" cy="0" r="22" fill="#090d16" stroke="#475569" stroke-width="3"/>
        <circle cx="0" cy="0" r="16" fill="url(#wheel-rim)" stroke="#94a3b8" stroke-width="1.2"/>
        <!-- Multi-spoke alloy -->
        <circle cx="0" cy="0" r="5" fill="#0f172a" stroke="{{ $color }}" stroke-width="1.4"/>
        <line x1="-14" y1="0" x2="14" y2="0" stroke="#cbd5e1" stroke-width="1.8"/>
        <line x1="0" y1="-14" x2="0" y2="14" stroke="#cbd5e1" stroke-width="1.8"/>
        <line x1="-10" y1="-10" x2="10" y2="10" stroke="#cbd5e1" stroke-width="1.8"/>
        <line x1="-10" y1="10" x2="10" y2="-10" stroke="#cbd5e1" stroke-width="1.8"/>
        <!-- Brake Caliper -->
        <rect x="7" y="-8" width="5" height="12" rx="2" fill="{{ $color }}" opacity="0.9"/>
    </g>

    <!-- Front Wheel Assembly (Right) -->
    <g transform="translate(239, 112)">
        <circle cx="0" cy="0" r="22" fill="#090d16" stroke="#475569" stroke-width="3"/>
        <circle cx="0" cy="0" r="16" fill="url(#wheel-rim)" stroke="#94a3b8" stroke-width="1.2"/>
        <!-- Multi-spoke alloy -->
        <circle cx="0" cy="0" r="5" fill="#0f172a" stroke="{{ $color }}" stroke-width="1.4"/>
        <line x1="-14" y1="0" x2="14" y2="0" stroke="#cbd5e1" stroke-width="1.8"/>
        <line x1="0" y1="-14" x2="0" y2="14" stroke="#cbd5e1" stroke-width="1.8"/>
        <line x1="-10" y1="-10" x2="10" y2="10" stroke="#cbd5e1" stroke-width="1.8"/>
        <line x1="-10" y1="10" x2="10" y2="-10" stroke="#cbd5e1" stroke-width="1.8"/>
        <!-- Brake Caliper -->
        <rect x="7" y="-8" width="5" height="12" rx="2" fill="{{ $color }}" opacity="0.9"/>
    </g>
</svg>

