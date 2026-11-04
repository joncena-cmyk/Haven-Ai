<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningController extends Controller
{
    /**
     * Learning Modules repository.
     */
    protected function getModules(): array
    {
        return [
            'valve-clearance' => [
                'slug'           => 'valve-clearance',
                'title'          => 'Improper Valve Clearance',
                'category'       => 'Mechanical Faults',
                'severity'       => 'Critical',
                'severity_color' => 'red',
                'read_time'      => '6 min read',
                'summary'        => 'Understand abnormal valvetrain mechanical impacts, excessive lash causing high-frequency tapping, high Crest Factor vibration spikes, and camshaft lobe–rocker arm wear patterns in gasoline ICE.',
                'icon'           => 'alert-triangle',
                'acoustic_signature' => [
                    'frequency_range'  => '2.5 kHz – 6.5 kHz',
                    'characteristics'  => 'Rapid, sharp metallic tapping or clicking at camshaft speed (half engine RPM). Loudest during cold start; may soften as oil reaches operating temp.',
                    'zcr'              => '0.18 – 0.30 (Elevated zero-crossing rate from repetitive mechanical impact)',
                    'spectral_centroid'=> 'Elevated (>2,600 Hz) due to sharp metallic impact waveforms at valve closure.',
                ],
                'vibration_signature' => [
                    'peak_g'      => '> 3.6 g (Repetitive sharp impact spikes at camshaft frequency)',
                    'rms_g'       => '0.30 g – 0.70 g',
                    'crest_factor'=> '> 4.2 (High impulsiveness — repetitive sharp impulse-to-steady energy ratio)',
                    'pattern'     => 'Periodic transient impact spikes at half-engine-speed frequency, representing valve opening and closing events.',
                ],
                'dtc_codes'        => ['P0011', 'P0012', 'P0014', 'P0016', 'P0017'],
                'root_causes'      => [
                    'Insufficient valve lash adjustment allowing metal-to-metal contact between camshaft lobe and rocker arm/tappet.',
                    'Worn camshaft lobes or rocker arm pads reducing lift profile.',
                    'Hydraulic lash adjuster (HLA) oil pressure starvation or collapsed lifter.',
                    'Contaminated engine oil or extended oil change intervals causing hydraulic HLA sludge.',
                    'Stretched timing chain causing camshaft phasing error and incorrect valve event timing.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Measure valve lash with feeler gauges against manufacturer clearance specification (typically 0.15–0.25 mm intake, 0.20–0.30 mm exhaust).',
                    'Step 2: Inspect camshaft lobes under borescope for flat spots, scoring, or reduced lift profile.',
                    'Step 3: Check hydraulic lash adjuster bleed-down: remove and manually compress the HLA — it should resist compression for at least 20 seconds.',
                    'Step 4: Perform oil pressure test at idle (minimum 1.5 bar / 22 PSI required to feed HLA passages).',
                    'Step 5: Inspect timing chain for stretch using camshaft position vs crankshaft position correlation.',
                ],
                'preventive_actions' => [
                    'Perform valve lash inspection every 40,000–60,000 km on solid-tappet engines.',
                    'Use manufacturer-specified full-synthetic oil to maintain HLA hydraulic pressure.',
                    'Replace timing chain kit (chain, tensioner, guides) at recommended mileage to prevent phasing drift.',
                ],
            ],

            'misfire' => [
                'slug' => 'misfire',
                'title' => 'Engine Misfire',
                'category' => 'Combustion & Ignition',
                'severity' => 'High',
                'severity_color' => 'amber',
                'read_time' => '5 min read',
                'summary' => 'Detect unburnt fuel mixtures, rhythmic acoustic power dropouts, cyclic crankshaft deceleration, and ignition coil degradation.',
                'icon' => 'zap-off',
                'acoustic_signature' => [
                    'frequency_range' => '80 Hz – 1.8 kHz',
                    'characteristics' => 'Rhythmic rhythmic "chuffing", stumbling cadence, and periodic acoustic energy dropouts.',
                    'zcr' => '0.14 – 0.24 (Erratic fluctuations)',
                    'spectral_centroid' => 'Lowered overall energy with sporadic high-frequency popping from unburnt fuel in exhaust.',
                ],
                'vibration_signature' => [
                    'peak_g' => '1.8 g – 3.2 g',
                    'rms_g' => '0.40 g – 0.75 g',
                    'crest_factor' => '3.0 – 4.2',
                    'pattern' => 'Cyclic torque deficit causing crankshaft rotational velocity drop every 720° (4-stroke cycle).',
                ],
                'dtc_codes' => ['P0300', 'P0301', 'P0302', 'P0303', 'P0304'],
                'root_causes' => [
                    'Worn spark plugs with excessive electrode gap or carbon tracking.',
                    'Failed ignition coil pack (COP) or damaged secondary insulation.',
                    'Clogged or leaking fuel injector delivering incorrect fuel volume.',
                    'Low cylinder compression due to worn rings, burned valves, or head gasket breach.',
                    'Intake manifold vacuum leak affecting individual cylinder runners.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Read live OBD-II misfire counters to isolate specific cylinder (P0301 = Cyl 1).',
                    'Step 2: Swap the suspected ignition coil with an adjacent cylinder; re-test to see if misfire follows the coil.',
                    'Step 3: Remove and inspect spark plug condition, gap, and fouling.',
                    'Step 4: Perform a relative compression test using starter current draw or manual compression gauge.',
                    'Step 5: Test fuel injector pulse with noid light and measure injector coil resistance.',
                ],
                'preventive_actions' => [
                    'Replace iridium/platinum spark plugs at specified intervals (typically 80,000–100,000 km).',
                    'Use quality fuel detergent additives to prevent injector spray nozzle varnish.',
                ],
            ],

            'loose-component' => [
                'slug' => 'loose-component',
                'title' => 'Loose or Worn Component Vibration',
                'category' => 'Mechanical Faults',
                'severity' => 'Medium',
                'severity_color' => 'amber',
                'read_time' => '4 min read',
                'summary' => 'Differentiate structural mechanical vibration transfer from degraded engine/transmission mounts, worn accessory brackets, and loose heat shields from combustion or ignition faults.',
                'icon' => 'activity',
                'acoustic_signature' => [
                    'frequency_range' => '40 Hz – 600 Hz',
                    'characteristics' => 'Deep, rhythmic low-frequency droning or structural chassis resonance audible inside the passenger cabin. May include intermittent rattling from loose heat shields or accessory brackets.',
                    'zcr' => '< 0.12 (Dominant low-frequency structural resonance band)',
                    'spectral_centroid' => 'Low (< 1,500 Hz), characteristic of low-RPM structural harmonic transfer.',
                ],
                'vibration_signature' => [
                    'peak_g' => '1.2 g – 2.8 g (Steady broadband, no isolated impulse peaks)',
                    'rms_g' => '> 0.55 g (Elevated continuous structural energy)',
                    'crest_factor' => '2.1 – 3.2 (Low impulsiveness — continuous, not impact-type)',
                    'pattern' => 'Continuous harmonic resonance transferred into chassis structure; high RMS-to-Peak ratio distinguishes from valve_clearance impacts.',
                ],
                'dtc_codes' => ['P0505', 'P0506', 'P0507', 'P0171', 'P0174'],
                'root_causes' => [
                    'Collapsed hydraulic or torn rubber engine/transmission mounts allowing excessive engine rock.',
                    'Loose or corroded accessory bracket (alternator, power steering pump, A/C compressor).',
                    'Worn or cracked serpentine belt tensioner causing belt slap and high-frequency chirp.',
                    'Loose exhaust heat shield resonating at specific RPM harmonics.',
                    'Degraded stabilizer bar end-link bushings transferring road shock into engine bay.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Visual and pry-bar inspection of all engine/transmission mounts for hydraulic fluid leakage, rubber tearing, or excessive play.',
                    'Step 2: Brake-torque test — with parking brake set and foot on service brake, briefly blip throttle to 1,500 RPM while a helper watches engine rock from the side.',
                    'Step 3: Inspect all accessory bracket bolts with torque wrench (aluminium brackets: 22–28 Nm).',
                    'Step 4: Shake each belt tensioner pulley to detect worn bearing.',
                    'Step 5: Listen with an automotive stethoscope at each mount location to isolate the resonant source.',
                ],
                'preventive_actions' => [
                    'Inspect all engine mounts during every major service interval (every 40,000 km).',
                    'Re-torque accessory bracket bolts annually in high-vibration applications.',
                    'Replace serpentine belt and tensioner assembly at manufacturer-specified interval.',
                ],
            ],

            'normal-operation' => [
                'slug'           => 'normal-operation',
                'title'          => 'Normal Engine Operation',
                'category'       => 'Baseline Reference',
                'severity'       => 'Normal',
                'severity_color' => 'teal',
                'read_time'      => '4 min read',
                'summary'        => 'Understand the acoustic and vibration telemetry signatures of a healthy gasoline ICE under both baseline idling (700–900 RPM) and controlled revving (2,000–2,500 RPM) conditions.',
                'icon'           => 'check-circle',
                'acoustic_signature' => [
                    'frequency_range'  => '80 Hz – 2,000 Hz',
                    'characteristics'  => 'Smooth, periodic combustion rhythm with consistent 4-stroke firing cadence. No sharp metallic impacts or cyclic amplitude dropouts.',
                    'zcr'              => '0.08 – 0.14 (Steady low-frequency combustion harmonics)',
                    'spectral_centroid'=> 'Low-to-mid (1,000–2,200 Hz) — dominated by fundamental firing frequency and harmonics.',
                ],
                'vibration_signature' => [
                    'peak_g'      => '≤ 1.8 g (No isolated impact spikes)',
                    'rms_g'       => '≤ 0.45 g (Low continuous mechanical energy)',
                    'crest_factor'=> '≤ 3.6 (No impulsive anomalies — smooth rotational energy)',
                    'pattern'     => 'Even, periodic vibration harmonics corresponding to engine firing order. Idle: 750–900 RPM. Revving baseline: 2,000–2,500 RPM.',
                ],
                'dtc_codes'        => ['None — all systems within normal parameters'],
                'root_causes'      => [
                    'This is the reference baseline condition for HAVEN AI classification.',
                    'Engine at idle: 750–900 RPM, temperature 85–95°C, 14.0–14.4V alternator charging.',
                    'Engine during revving: 2,000–2,500 RPM, smooth acceleration without torque dips or vibration spikes.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Confirm RPM within normal idle range (750–900 RPM in Park/Neutral with A/C OFF).',
                    'Step 2: Verify coolant temperature gauge stabilized between 85°C–100°C at operating temp.',
                    'Step 3: Confirm charging voltage at 13.8V–14.4V on a healthy alternator system.',
                    'Step 4: Listen for consistent 4-cylinder firing cadence without stumble or acoustic anomaly.',
                    'Step 5: Vibration magnitude should feel smooth at idle; no harsh mechanical impacts perceptible through the body panel.',
                ],
                'preventive_actions' => [
                    'Maintain scheduled oil and filter service to sustain oil film and reduce baseline mechanical noise.',
                    'Keep air filter clean to maintain consistent air-fuel ratio at idle.',
                    'Inspect spark plugs and coil packs at 80,000–100,000 km to sustain consistent combustion rhythm.',
                ],
            ],

            'acoustic-signal-analysis' => [
                'slug' => 'acoustic-signal-analysis',
                'title' => 'Acoustic Signal Processing (MFCC & DSP)',
                'category' => 'Signal Processing',
                'severity' => 'Info',
                'severity_color' => 'teal',
                'read_time' => '7 min read',
                'summary' => 'Deep dive into Mel-Frequency Cepstral Coefficients (MFCCs), Spectral Centroids, Zero Crossing Rate, and Fast Fourier Transforms (FFT).',
                'icon' => 'radio',
                'acoustic_signature' => [
                    'frequency_range' => '20 Hz – 22.05 kHz (Full Nyquist band)',
                    'characteristics' => 'Decomposition of complex sound waves into perceptual Mel scale filterbanks.',
                    'zcr' => 'Measures noise density and rate of signal sign transitions per second.',
                    'spectral_centroid' => 'Represents the "center of mass" of sound spectrum, correlating to sound brightness.',
                ],
                'vibration_signature' => [
                    'peak_g' => 'Correlated with high-amplitude acoustic pressure waves.',
                    'rms_g' => 'Direct mechanical analog of Root-Mean-Square acoustic power.',
                    'crest_factor' => 'Signal impulsiveness metric used in acoustic and vibration feature fusion.',
                    'pattern' => 'Time-domain to frequency-domain transformation via short-time FFT (STFT).',
                ],
                'dtc_codes' => ['N/A (AI Pipeline)'],
                'root_causes' => [
                    'Audio clipping when microphone gain is set too high near exhaust/intake.',
                    'Ambient wind or road noise contamination requiring bandpass filtering.',
                    'Inconsistent microphone distance skewing amplitude-based features.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Microphone captures raw 44.1 kHz PCM audio through Web Audio API.',
                    'Step 2: Signal windowing applied (Hamming window with 2048 sample frame size).',
                    'Step 3: FFT generates power spectrum of audio frequencies.',
                    'Step 4: Mel-scale triangular filterbank maps linear frequencies to human psychoacoustic scale.',
                    'Step 5: Discrete Cosine Transform (DCT) extracts 13 MFCC coefficients.',
                ],
                'preventive_actions' => [
                    'Position recording device approximately 30–50 cm from engine valve cover.',
                    'Record with engine at steady idle (700–900 RPM) with A/C compressor switched OFF.',
                ],
            ],

            'esp32-vibration-telemetry' => [
                'slug' => 'esp32-vibration-telemetry',
                'title' => 'ESP32 & Accelerometer Dynamics',
                'category' => 'Hardware & IoT',
                'severity' => 'Info',
                'severity_color' => 'teal',
                'read_time' => '6 min read',
                'summary' => 'Hardware guide for mounting MPU6050/ADXL345 accelerometers, calculating Peak G and RMS G, and structuring CSV telemetry files.',
                'icon' => 'cpu',
                'acoustic_signature' => [
                    'frequency_range' => 'N/A (Mechanical Telemetry)',
                    'characteristics' => 'Direct solid-state piezoelectric or capacitive MEMS displacement measurement.',
                    'zcr' => 'Correlated with vibration frequency oscillations across the zero-G baseline.',
                    'spectral_centroid' => 'Correlated with high-frequency harmonic mechanical resonance.',
                ],
                'vibration_signature' => [
                    'peak_g' => 'Max magnitude: max(√(x² + y² + z²)) across entire sampling run.',
                    'rms_g' => '√( (1/N) * Σ(x² + y² + z²) ) — true continuous energy baseline.',
                    'crest_factor' => 'Peak G / RMS G — mathematical ratio detecting impact anomalies.',
                    'pattern' => 'Periodic 3-axis acceleration sampled at 50 Hz to 200 Hz.',
                ],
                'dtc_codes' => ['N/A (Telemetry Protocol)'],
                'root_causes' => [
                    'Loose sensor mounting causing spurious resonant bouncing.',
                    'Sensor cable vibration strain introducing false reading spikes.',
                    'Sampling rate jitter or buffer overrun in ESP32 micro-controller loop.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Securely mount accelerometer to engine cylinder head or intake runner using magnetic base or high-temp adhesive.',
                    'Step 2: Connect sensor via I2C (SDA to GPIO 21, SCL to GPIO 22 on ESP32).',
                    'Step 3: Set sensor full-scale range to ±8g or ±16g to avoid measurement saturation during knock.',
                    'Step 4: Stream data as CSV with schema: `timestamp,x,y,z` or `timestamp,magnitude`.',
                    'Step 5: Upload CSV file directly to HAVEN Stage 4 diagnostic wizard.',
                ],
                'preventive_actions' => [
                    'Ensure sensor operating temperature does not exceed 85°C (use thermal isolation pad).',
                    'Validate calibration on flat surface to verify 1.0g static gravitational vector.',
                ],
            ],

            // ── NEW MODULES ────────────────────────────────────────────────

            'pre-diagnostic-safety' => [
                'slug'           => 'pre-diagnostic-safety',
                'title'          => 'Pre-Diagnostic Safety Checklist',
                'category'       => 'Safety & Preparation',
                'severity'       => 'Important',
                'severity_color' => 'amber',
                'read_time'      => '4 min read',
                'summary'        => 'Essential safety procedures and vehicle conditions to verify before running a HAVEN AI diagnostic session — protecting both the user and the integrity of acoustic and vibration data.',
                'icon'           => 'shield',
                'acoustic_signature' => [
                    'frequency_range'   => 'N/A (Pre-capture phase)',
                    'characteristics'   => 'Environmental noise sources (traffic, A/C compressor, cooling fan) must be minimized before recording to prevent data contamination.',
                    'zcr'               => 'Background noise floor should be below ZCR 0.05 to ensure clean signal baseline.',
                    'spectral_centroid' => 'Aim for ambient spectral centroid below 800 Hz — indicating a quiet recording environment.',
                ],
                'vibration_signature' => [
                    'peak_g'       => '< 0.3 g ambient (engine off baseline — confirms sensor mounting is stable)',
                    'rms_g'        => '< 0.05 g (acceptable idle ambient floor before engine start)',
                    'crest_factor' => 'N/A (Pre-start phase)',
                    'pattern'      => 'Static flat baseline — no oscillatory pattern. Any movement in pre-start data indicates loose sensor or external vibration source.',
                ],
                'dtc_codes' => ['N/A (Safety Protocol — no OBD codes applicable)'],
                'root_causes' => [
                    'Recording in a noisy environment (busy road, wind, rain) corrupts MFCC feature extraction and reduces AI classification accuracy.',
                    'Running diagnostic with engine cold (below 60°C) skews vibration baseline — symptoms may be masked or exaggerated.',
                    'Microphone placed near intake or exhaust causes audio clipping (>0 dBFS) and waveform saturation.',
                    'ESP32 sensor mounted on a loose bracket or rubber surface introduces artificial resonant bouncing unrelated to engine condition.',
                    'A/C compressor engaged during capture adds a 120–180 Hz compressor harmonic that overlaps misfire acoustic signatures.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Park vehicle on a flat, level surface. Engage parking brake. Ensure safe clearance around all vehicle sides.',
                    'Step 2: Warm up engine to operating temperature (85°C–100°C) before starting any HAVEN recording session.',
                    'Step 3: Switch OFF A/C, radio, and all non-essential electrical loads to reduce acoustic and electrical interference.',
                    'Step 4: Position your mobile device microphone 30–50 cm from the engine valve cover — not touching any metal surface.',
                    'Step 5: If using ESP32 telemetry, verify the LED heartbeat is active and Wi-Fi is connected before starting capture.',
                    'Step 6: Perform a 5-second test recording, check that waveform shows clean oscillation and is not clipping (flat top/bottom on oscilloscope).',
                ],
                'preventive_actions' => [
                    'Always capture in an enclosed garage or covered parking area to minimize wind noise interference.',
                    'Do not run HAVEN diagnostics while the vehicle is moving — requires stationary engine at idle.',
                    'Ensure the vehicle has sufficient fuel (above 1/4 tank) — fuel starvation near empty can temporarily induce misfire-like symptoms.',
                    'Keep spectators clear of the engine bay during live recording — vibration from touching the vehicle body is picked up by the accelerometer.',
                ],
                'care_tips' => [
                    'Warm-up matters: Cold engines run rich and at elevated RPM — always warm up before diagnosing for accurate baseline comparison.',
                    'Clean your microphone grille regularly — blocked ports cause high-frequency roll-off that degrades MFCC quality.',
                    'If your phone has a case, make sure it is not covering the microphone port during recording.',
                    'Repeat captures: Run at least 2–3 sessions and compare results — HAVEN\'s AI is most reliable when patterns are consistent across multiple recordings.',
                ],
                'risks' => [
                    'Risk: Hot engine components (exhaust manifold > 400°C, turbo housing up to 900°C) can cause severe burns — never touch or position devices near these surfaces.',
                    'Risk: Running a misfiring engine for extended periods can overheat the catalytic converter and cause vehicle fire — limit idle time when P0300-series codes are present.',
                    'Risk: Loose ESP32 sensor wiring near rotating components (fan belt, cooling fan blades) — always secure cables away from moving parts with zip ties.',
                    'Risk: Carbon monoxide build-up in an enclosed space — never run engine diagnostics with the garage door completely closed.',
                ],
                'haven_scope_note' => 'HAVEN AI performs acoustic and vibration-based classification only. It does not read live OBD-II data directly from the ECU port. For real-time live data streaming, connect a dedicated OBD-II Bluetooth adapter alongside HAVEN.',
            ],

            'engine-oil-maintenance' => [
                'slug'           => 'engine-oil-maintenance',
                'title'          => 'Engine Oil & Fluid Health',
                'category'       => 'Preventive Maintenance',
                'severity'       => 'Medium',
                'severity_color' => 'amber',
                'read_time'      => '5 min read',
                'summary'        => 'How engine oil degradation, viscosity breakdown, and contamination directly change acoustic tapping frequencies and vibration amplitude — and what HAVEN\'s telemetry reveals about your lubrication system.',
                'icon'           => 'droplet',
                'acoustic_signature' => [
                    'frequency_range'   => '1.5 kHz – 5.0 kHz (Low-oil acoustic range)',
                    'characteristics'   => 'Increased metallic tapping and upper-engine chatter as oil film thins. High-frequency content intensifies as viscosity drops below specification.',
                    'zcr'               => '0.12 – 0.22 (Elevated — more frequent sign changes from mechanical impacts as oil film collapses)',
                    'spectral_centroid' => 'Shifts progressively higher (> 2,000 Hz) as lubrication degrades — correlated with increasing valve train impact severity.',
                ],
                'vibration_signature' => [
                    'peak_g'       => '2.0 g – 4.5 g (Elevated impulse peaks from dry contact between cam lobes and tappets)',
                    'rms_g'        => '0.35 g – 0.65 g (Elevated continuous energy from increased friction across bearings)',
                    'crest_factor' => '3.5 – 5.0 (Rising impulsiveness indicates boundary lubrication failure)',
                    'pattern'      => 'Gradual amplitude increase over time — unlike sudden fault onset. RMS G rises slowly as viscosity index degrades over service intervals.',
                ],
                'dtc_codes' => ['P0520', 'P0521', 'P0522', 'P0523', 'P0524'],
                'root_causes' => [
                    'Oil oxidation and sludge formation from extended service intervals (beyond 5,000–10,000 km depending on oil grade).',
                    'Coolant contamination of oil (white/milky dipstick appearance) — indicator of head gasket failure.',
                    'Fuel dilution from excessive short-trip driving — reduces oil viscosity and compromises film strength.',
                    'Oil level critically low due to external leak, rear main seal failure, or piston ring blow-by consumption.',
                    'Wrong oil viscosity grade used after service — too thin (e.g., 5W-20 in a 10W-40 engine) reduces film under high-load conditions.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Pull the dipstick — oil should be between MIN and MAX marks. Dark black color is normal; milky/frothy indicates coolant contamination.',
                    'Step 2: Rub a drop of oil between your fingers — gritty texture indicates metallic particle contamination from internal wear.',
                    'Step 3: Run a HAVEN acoustic capture before and after an oil change — compare MFCC spectral centroid values to quantify improvement.',
                    'Step 4: Monitor HAVEN vibration RMS G trend over time — a gradual rise between oil changes suggests increased boundary friction.',
                    'Step 5: Check for oil pressure warning light delay on cold start — indicates low oil level, weak pump, or sludge-blocked oil passages.',
                ],
                'preventive_actions' => [
                    'Change engine oil and filter every 5,000 km (mineral oil) or 10,000 km (full synthetic) — never exceed manufacturer interval.',
                    'Use only the viscosity grade specified in your owner\'s manual (e.g., 5W-30, 10W-40) — viscosity directly affects HAVEN vibration baseline.',
                    'Check oil level every 2 weeks or before long trips — low oil is the single most preventable cause of engine damage.',
                    'After oil change, run a fresh HAVEN capture to establish a new clean baseline for vibration monitoring.',
                ],
                'care_tips' => [
                    'Store engine oil in a sealed container away from heat and direct sunlight — UV exposure degrades additive packages.',
                    'If your vehicle consumes more than 1 liter per 3,000 km, investigate piston ring wear or valve stem seal failure.',
                    'Use an oil catch pan and dispose of used oil at a certified recycling center — never pour used oil down drains.',
                    'Synthetic oil does NOT mean "never change" — its base stock resists breakdown longer but additives still deplete over time.',
                ],
                'risks' => [
                    'Risk: Running with critically low oil (below MIN mark) can cause catastrophic spun bearings within minutes at high RPM — an irreversible failure.',
                    'Risk: Ignoring milky oil appearance (coolant + oil mix) and continuing to drive risks hydrolocking the engine and warping the cylinder head.',
                    'Risk: Over-filling oil above MAX mark causes foaming from crankshaft whipping — aerated oil loses pressure and film strength rapidly.',
                    'Risk: Using the wrong viscosity in cold climates (too thick) prevents cold-start oil flow to hydraulic lash adjusters within the critical first 30 seconds.',
                ],
                'haven_scope_note' => 'HAVEN cannot directly measure oil pressure or viscosity. However, trending RMS G and Crest Factor values across multiple sessions reveals lubricant degradation patterns. If HAVEN detects increasing crest factor between sessions with no other fault code, schedule an oil analysis.',
            ],

            'when-to-see-mechanic' => [
                'slug'           => 'when-to-see-mechanic',
                'title'          => 'When to Seek a Professional Mechanic',
                'category'       => 'Safety & Preparation',
                'severity'       => 'Critical',
                'severity_color' => 'red',
                'read_time'      => '5 min read',
                'summary'        => 'HAVEN AI is a diagnostic intelligence tool — not a replacement for hands-on mechanical repair. Learn which fault signatures require immediate professional intervention and which are safe to monitor.',
                'icon'           => 'alert-triangle',
                'acoustic_signature' => [
                    'frequency_range'   => 'Any frequency range with sudden onset above normal baseline',
                    'characteristics'   => 'Any new sharp metallic knock, deep rhythmic thud, or grinding sound not previously present — treat as urgent until diagnosed.',
                    'zcr'               => 'Sudden ZCR jump > 0.25 compared to previous baseline warrants immediate investigation.',
                    'spectral_centroid' => 'Spectral centroid shift > 1,500 Hz compared to established baseline — stop driving and seek inspection.',
                ],
                'vibration_signature' => [
                    'peak_g'       => '> 5.0 g — stop driving immediately. Impulses this severe risk immediate mechanical failure.',
                    'rms_g'        => '> 0.90 g sustained — abnormal structural vibration; do not ignore.',
                    'crest_factor' => '> 5.5 — extreme impulsiveness indicating imminent mechanical failure (bearing, connecting rod, or piston).',
                    'pattern'      => 'Any vibration pattern that is felt through the steering wheel, seat, or floor at idle — not just audible — is a red flag.',
                ],
                'dtc_codes' => ['P0217', 'P0300', 'P0301', 'P0302', 'P0303', 'P0304', 'P0325', 'P0011', 'P0524'],
                'root_causes' => [
                    'HAVEN classifies acoustic and vibration patterns — it does not perform physical inspection, component replacement, or torque verification.',
                    'Some fault modes (head gasket failure, crankshaft bearing failure, timing belt snap) can cause complete engine seizure within seconds of symptom onset.',
                    'Electronic systems (ABS, airbags, transmission control modules) are outside HAVEN\'s sensor scope entirely.',
                    'Brake system, suspension geometry, steering rack wear — these require physical inspection and cannot be diagnosed acoustically with HAVEN.',
                    'Fuel system pressure, injector flow rates, and lambda sensor function require dedicated scan tools beyond HAVEN\'s current capability.',
                ],
                'diagnostic_steps' => [
                    'Step 1: If HAVEN returns "Knocking" or "Critical Misfire" classification — do NOT continue driving. Pull over safely and call for assistance.',
                    'Step 2: If HAVEN identifies valve clearance issues (Crest Factor > 4.2, tapping at half-RPM) — drive gently to the nearest workshop within 48 hours.',
                    'Step 3: If temperature gauge enters red zone alongside a HAVEN fault — shut engine off immediately. Overheating + detected knock = catastrophic risk.',
                    'Step 4: For "Normal" or "Rough Idle" classifications — you can schedule a professional inspection within 1–2 weeks for confirmation.',
                    'Step 5: Always bring your HAVEN diagnostic report (use the Print Report feature) when visiting a mechanic — it provides precise acoustic and vibration data for faster diagnosis.',
                ],
                'preventive_actions' => [
                    'Use HAVEN as an early warning system — catch faults while they are minor, before they become expensive mechanical failures.',
                    'Run a HAVEN session every 2–4 weeks as part of routine vehicle health monitoring.',
                    'Never delay mechanical repair based solely on "it sounds okay to me" — trust the acoustic data over subjective perception.',
                    'Keep a log of HAVEN reports over time — trend analysis across sessions provides far more diagnostic value than a single snapshot.',
                ],
                'care_tips' => [
                    'HAVEN is most powerful as a trending tool — one reading is useful, but comparing 5 readings over 2 months reveals developing faults early.',
                    'Share your HAVEN report PDF with your mechanic before the service appointment — it saves diagnostic time and reduces labor cost.',
                    'If your mechanic says "I don\'t see anything wrong" but HAVEN consistently detects elevated crest factor — get a second opinion.',
                    'Professional diagnosis and physical repair remain the final authority. HAVEN provides data-driven evidence to guide that process.',
                ],
                'risks' => [
                    'Risk: Continuing to drive with HAVEN\'s "Knocking" classification risks spun rod bearings, piston damage, and complete engine seizure.',
                    'Risk: Ignoring "Critical Misfire" classifications can ignite unburnt fuel in the catalytic converter — causing converter failure or vehicle fire.',
                    'Risk: Assuming HAVEN\'s "Normal" result means all systems are perfect — HAVEN only evaluates acoustic and vibration data. Brake fade, tyre wear, and electrical faults are outside its scope.',
                    'Risk: Self-performing engine repairs without proper training and tools can void manufacturer warranties and create new safety hazards.',
                ],
                'haven_scope_note' => 'HAVEN AI\'s scope is specifically: acoustic engine sound classification (via microphone + MFCC), ESP32 accelerometer vibration telemetry, OBD-II DTC code reference, and AI-powered fault pattern identification. It does NOT replace hands-on mechanical inspection, live OBD scanning, oscilloscope diagnostics, or physical component testing.',
            ],

            'reading-haven-report' => [
                'slug'           => 'reading-haven-report',
                'title'          => 'Understanding Your HAVEN Diagnostic Report',
                'category'       => 'Using HAVEN AI',
                'severity'       => 'Info',
                'severity_color' => 'teal',
                'read_time'      => '5 min read',
                'summary'        => 'A complete guide to reading and interpreting HAVEN\'s AI diagnostic output — including classification labels, confidence scores, telemetry charts, and what actionable steps to take based on each result.',
                'icon'           => 'file-text',
                'acoustic_signature' => [
                    'frequency_range'   => 'Full spectrum 20 Hz – 22.05 kHz (displayed in HAVEN spectrogram view)',
                    'characteristics'   => 'HAVEN extracts 13 MFCC coefficients + ZCR + Spectral Centroid as acoustic feature vector for ML classification.',
                    'zcr'               => 'Shown in report as "Zero Crossing Rate" — higher values indicate more high-frequency content or noisy signal.',
                    'spectral_centroid' => 'Report displays this as Hz value — low (< 1,500 Hz) means healthy smooth combustion; high (> 2,500 Hz) suggests metallic impact.',
                ],
                'vibration_signature' => [
                    'peak_g'       => 'Shown on telemetry chart — single highest acceleration impulse recorded during the session.',
                    'rms_g'        => 'Root Mean Square G — represents continuous average vibration energy level across the session.',
                    'crest_factor' => 'Computed automatically as Peak G / RMS G — the most reliable single-number health indicator in HAVEN.',
                    'pattern'      => 'Time-domain waveform displayed as oscilloscope chart in Step 2 of the diagnostic wizard — visual inspection of rhythm and impulse patterns.',
                ],
                'dtc_codes' => ['N/A (Report Interpretation Guide)'],
                'root_causes' => [
                    'Classification: "Normal" — AI confidence ≥ 70% across baseline acoustic features. No action required.',
                    'Classification: "Knocking" — Spike frequency 5.5–6.5 kHz detected with high amplitude impulses. Requires mechanical inspection.',
                    'Classification: "Misfire" — Cyclic acoustic dropouts at 720° crankshaft intervals. Check spark plugs and coil packs.',
                    'Classification: "Rough Idle" — Elevated broadband low-frequency energy (40–300 Hz). Inspect engine mounts and idle air control.',
                    'Classification: "Valve Clearance" — Periodic tapping at half-engine-speed frequency. Schedule valvetrain inspection.',
                ],
                'diagnostic_steps' => [
                    'Step 1: Open your diagnostic report at /diagnostics/{id} — review the AI Classification label at the top of the results card.',
                    'Step 2: Check the Confidence Score — readings above 75% are reliable classifications; below 60% suggests environmental noise contamination. Re-record in a quieter environment.',
                    'Step 3: Review the Vibration Telemetry section — compare your Peak G and RMS G against the reference values in this Learning Module library.',
                    'Step 4: Read the ML Inference Summary — HAVEN provides a plain-language explanation of what the acoustic features indicate.',
                    'Step 5: Cross-reference with the OBD-II DTC Directory in HAVEN Learning — if your classification matches known DTC patterns, note the relevant codes for your mechanic.',
                    'Step 6: Use the Print Report button to generate a PDF — bring it to your workshop for faster, data-driven diagnosis.',
                ],
                'preventive_actions' => [
                    'Archive every HAVEN report — historical comparison across multiple sessions is the most powerful feature of the system.',
                    'Record at the same location, RPM, and temperature each time — consistency in recording conditions improves classification accuracy.',
                    'After any mechanical repair, run a new HAVEN session and compare acoustic signatures — this validates the repair and establishes a new clean baseline.',
                ],
                'care_tips' => [
                    'A single "Normal" result is not a guarantee — run 3 sessions over 2 weeks before concluding an engine is fault-free.',
                    'Low confidence scores (< 60%) usually mean noisy recording — retry in a quieter environment before acting on the result.',
                    'Crest Factor is the most reliable single metric: below 3.6 is healthy; above 4.5 is a strong fault indicator regardless of classification label.',
                    'The HAVEN AI assistant (chat widget) can explain any section of your report in plain language — just paste the metric values and ask.',
                ],
                'risks' => [
                    'Risk: Over-trusting a single high-confidence result without physical verification. AI classification is probabilistic — not absolute.',
                    'Risk: Ignoring a low-confidence "Normal" result — low confidence means uncertain, not necessarily healthy.',
                    'Risk: Sharing raw report data publicly without understanding privacy implications — diagnostic recordings may contain background voice audio.',
                    'Risk: Acting on misfire classification alone without isolating the specific cylinder — unnecessary spark plug replacement on healthy cylinders wastes money and risks incorrect reinstallation torque.',
                ],
                'haven_scope_note' => 'HAVEN\'s ML model was trained on acoustic recordings of 4-cylinder gasoline ICE engines. Results for diesel engines, rotary engines, or electric vehicles are outside the validated training distribution and should be interpreted with caution.',
            ],

            'battery-charging-health' => [
                'slug'           => 'battery-charging-health',
                'title'          => 'Battery & Charging System Telemetry',
                'category'       => 'Electrical Systems',
                'severity'       => 'Medium',
                'severity_color' => 'amber',
                'read_time'      => '4 min read',
                'summary'        => 'How the INA219 voltage and current sensor in the HAVEN ESP32 kit monitors your charging system health — and what deviations from 13.8V–14.4V mean for engine performance and acoustic baseline.',
                'icon'           => 'battery',
                'acoustic_signature' => [
                    'frequency_range'   => '50 Hz – 300 Hz (Alternator ripple frequency)',
                    'characteristics'   => 'A failing alternator introduces a 100–150 Hz electrical whine into the audio signal — appears as a harmonic overlay on top of normal engine combustion noise.',
                    'zcr'               => 'Slightly elevated ZCR (0.10–0.18) from alternator ripple contamination — can be mistaken for early valve noise if not cross-referenced with voltage data.',
                    'spectral_centroid' => 'Alternator bearing failure shifts spectral centroid higher (> 2,000 Hz) with a distinct high-frequency whine at alternator pulley speed.',
                ],
                'vibration_signature' => [
                    'peak_g'       => '0.8 g – 2.2 g (Low-amplitude, high-frequency pattern at alternator bearing frequency)',
                    'rms_g'        => '0.10 g – 0.35 g (Moderate continuous energy from accessory belt system)',
                    'crest_factor' => '2.5 – 3.8 (Moderate — not impulse-type, distinguishes from valve knock)',
                    'pattern'      => 'Steady high-frequency oscillation correlated with engine RPM × alternator pulley ratio (~2.5×). Increases with RPM, unlike combustion-cycle faults.',
                ],
                'dtc_codes' => ['P0562', 'P0563', 'P0620', 'P0622', 'P0625', 'P0626'],
                'root_causes' => [
                    'Battery voltage below 12.0V at rest indicates a sulfated or failed battery — engine cranks slowly and causes false "rough idle" acoustic signatures from weak spark ignition.',
                    'Alternator not charging (voltage < 13.5V at 1,500 RPM) — battery depletes during operation, leading to voltage-related ECU sensor errors.',
                    'Overcharging (voltage > 14.8V) from a failed voltage regulator — damages battery cells and can cause electrolyte boiling and hydrogen gas release.',
                    'Loose or corroded alternator belt causing slip — introduces intermittent voltage drop and a characteristic chirping sound in the 1–3 kHz range.',
                    'INA219 sensor miscalibration — verify zero-current offset before trusting HAVEN current readings.',
                ],
                'diagnostic_steps' => [
                    'Step 1: With engine running at 1,500 RPM, HAVEN\'s INA219 telemetry should show 13.8V–14.4V on the voltage chart.',
                    'Step 2: If voltage reads 12.0V–13.5V at idle — alternator output is low. Rev to 2,000 RPM and check if voltage rises; if not, suspect alternator diode failure.',
                    'Step 3: Turn on headlights, blower motor, and rear defogger simultaneously — voltage should not drop below 13.5V under full load.',
                    'Step 4: Inspect alternator drive belt tension — it should deflect no more than 10 mm under moderate thumb pressure at the longest span.',
                    'Step 5: Use HAVEN acoustic capture with alternator issue suspected — listen for 100–150 Hz ripple frequency in the spectral view as a secondary confirmation.',
                ],
                'preventive_actions' => [
                    'Test battery voltage every 6 months — a fully charged healthy battery reads 12.6V with engine off.',
                    'Replace vehicle battery every 3–4 years regardless of apparent condition — capacity degrades invisibly with age.',
                    'Keep battery terminals clean and tight — even 0.1 Ω of terminal resistance causes voltage drop under high current draw.',
                    'After replacing a battery, run a HAVEN capture to re-establish the acoustic baseline — new batteries sometimes change idle RPM slightly.',
                ],
                'care_tips' => [
                    'Short trips (under 10 minutes) do not allow the alternator to fully recharge the battery — once per week, drive for 20+ minutes at highway speed.',
                    'Avoid leaving lights, radio, or accessories on with the engine off — 30 minutes of interior lights can deeply discharge a marginal battery.',
                    'If your car struggles to start on a cold morning, check HAVEN\'s voltage telemetry from the previous session — voltage below 12.2V at rest is a warning sign.',
                    'A battery load test at your local workshop (free at most auto parts stores) takes 5 minutes and confirms true battery health under load.',
                ],
                'risks' => [
                    'Risk: Jump-starting incorrectly (reversed polarity) can destroy ECU, ABS module, and alternator diodes in milliseconds — always connect positive-to-positive, negative-to-ground.',
                    'Risk: A deeply discharged battery (below 10.5V) that is force-charged rapidly can release flammable hydrogen gas — charge in a ventilated area.',
                    'Risk: Driving with charging voltage below 12.8V risks complete electrical shutdown while in motion — headlights and power steering (if electric) will fail.',
                    'Risk: Overcharging (> 15V) can crack battery case and cause acid spill — use only a smart charger with voltage regulation.',
                ],
                'haven_scope_note' => 'HAVEN\'s INA219 current and voltage sensor provides real-time charging system monitoring via the ESP32 telemetry pipeline. The voltage reading is displayed in the diagnostic session\'s multi-sensor panel and is used to contextualize acoustic and vibration anomalies — a low-voltage state can cause misfires that appear acoustic in origin.',
            ],

            'cooling-thermal-management' => [
                'slug'           => 'cooling-thermal-management',
                'title'          => 'Cooling System & Thermal Management',
                'category'       => 'Thermal Systems',
                'severity'       => 'High',
                'severity_color' => 'amber',
                'read_time'      => '5 min read',
                'summary'        => 'How the DS18B20 temperature sensor in the HAVEN ESP32 kit tracks coolant or engine bay temperature — and the relationship between thermal management and acoustic/vibration fault signatures.',
                'icon'           => 'thermometer',
                'acoustic_signature' => [
                    'frequency_range'   => '80 Hz – 3.5 kHz (Detonation onset from overheating)',
                    'characteristics'   => 'Overheated engines produce increasing knock signatures as end-gas auto-ignition becomes more likely at combustion chamber temperatures above 95°C coolant equivalent.',
                    'zcr'               => 'ZCR increases progressively as thermal knock emerges — differentiated from cold-start knock by occurring after warm-up rather than during it.',
                    'spectral_centroid' => 'Thermal detonation produces spectral centroid shift toward 4–6 kHz knock band — requires immediate engine shutdown.',
                ],
                'vibration_signature' => [
                    'peak_g'       => '3.0 g – 5.5 g (Thermal knock onset — dangerous range)',
                    'rms_g'        => '0.45 g – 0.80 g (Elevated from knock-induced piston crown stress)',
                    'crest_factor' => '4.0 – 6.0 (High impulsiveness from thermal detonation — emergency shutdown threshold)',
                    'pattern'      => 'Gradual vibration amplitude increase correlated with rising DS18B20 temperature readings — the thermal fault signature builds over time unlike sudden mechanical failure.',
                ],
                'dtc_codes' => ['P0115', 'P0116', 'P0117', 'P0118', 'P0128', 'P0217', 'P0218'],
                'root_causes' => [
                    'Coolant level critically low — air pocket in cooling circuit prevents heat transfer from cylinder head to radiator.',
                    'Thermostat stuck closed — coolant cannot flow to radiator, causing rapid heat soak in the engine block.',
                    'Radiator cooling fan failure (mechanical or electrical) — reduces heat rejection at idle and in slow traffic.',
                    'Water pump impeller detached, corroded, or bearing-failed — no coolant circulation despite pump spinning externally.',
                    'Radiator core blocked externally (leaves, debris) or internally (rust, calcium scale) — reduces cooling surface area.',
                ],
                'diagnostic_steps' => [
                    'Step 1: With engine running and warmed up, verify HAVEN\'s DS18B20 temperature reading is stable between 80°C–100°C.',
                    'Step 2: If DS18B20 rises above 100°C — check dashboard coolant gauge simultaneously. Verify both sensors agree (within 5°C).',
                    'Step 3: Observe radiator fan operation at idle — it should cycle on when coolant temperature exceeds 95°C. A fan that never runs may have a failed relay or temperature switch.',
                    'Step 4: Inspect coolant reservoir level when engine is cold (never when hot — pressurized system risk). Level should be between MIN and MAX marks.',
                    'Step 5: Check coolant colour — fresh coolant is green, orange, or pink. Brown/rusty coolant indicates scale and corrosion — flush and replace.',
                ],
                'preventive_actions' => [
                    'Flush and replace engine coolant every 2 years or 40,000 km — degraded coolant loses corrosion inhibitor and pH buffer.',
                    'Inspect radiator hoses for swelling, cracks, or softness every 12 months — a burst hose causes instant coolant loss and overheating.',
                    'Clean radiator fins annually — insects, dirt, and debris block airflow and reduce cooling capacity by 15–30%.',
                    'Check coolant level monthly — a consistently dropping level indicates an external or internal leak requiring immediate investigation.',
                ],
                'care_tips' => [
                    'Never open the radiator cap when the engine is hot — the cooling system operates at 90–120 kPa pressure. Coolant will flash to steam and cause severe scalding burns.',
                    'Mix coolant with distilled water (50/50 ratio) — tap water introduces calcium deposits that block narrow radiator passages.',
                    'If the temperature gauge moves into the red zone while driving — immediately turn off A/C, turn on heater to full hot (draws heat from coolant), pull over safely, and shut off engine.',
                    'A sweet smell from the engine bay (ethylene glycol) suggests a coolant leak — even a small leak can lead to complete coolant loss within hours of driving.',
                ],
                'risks' => [
                    'Risk: Continuing to drive with temperature in the red zone risks head gasket failure, warped cylinder head (very expensive repair), and potentially complete engine seizure.',
                    'Risk: Adding cold water to an overheated engine can cause thermal shock — cracking the cylinder head or warping the head gasket sealing surface.',
                    'Risk: Ethylene glycol (coolant) is highly toxic to animals — even small puddles in driveways are lethal to pets. Clean up all coolant spills immediately.',
                    'Risk: Cooling system pressure cap failure — a worn cap that releases pressure too early causes boiling at lower temperatures, misleading the driver into thinking the engine is "cooler" than it really is.',
                ],
                'haven_scope_note' => 'HAVEN\'s DS18B20 sensor measures ambient temperature in the engine bay or can be positioned to measure coolant return line temperature (approximately 5–15°C below actual engine operating temperature). For precise coolant temperature, cross-reference with the dashboard gauge or an OBD-II temperature PID from a scan tool.',
            ],

            'safe-driving-active-fault' => [
                'slug'           => 'safe-driving-active-fault',
                'title'          => 'Safe Driving With an Active Fault',
                'category'       => 'Safety & Preparation',
                'severity'       => 'Critical',
                'severity_color' => 'red',
                'read_time'      => '4 min read',
                'summary'        => 'What to do — and what NOT to do — when HAVEN AI detects a fault classification during or after a diagnostic session. Risk levels, driving restrictions, and emergency procedures.',
                'icon'           => 'alert-octagon',
                'acoustic_signature' => [
                    'frequency_range'   => 'Any abnormal classification band',
                    'characteristics'   => 'Any HAVEN classification other than "Normal" represents an acoustic anomaly that requires a defined response protocol based on severity.',
                    'zcr'               => 'Elevated ZCR in the context of a fault classification indicates an active mechanical process — not a recording artifact.',
                    'spectral_centroid' => 'A sudden spectral centroid shift during driving (heard as a new sound) that persists warrants immediate safe stopping.',
                ],
                'vibration_signature' => [
                    'peak_g'       => '> 4.0 g felt as a harsh mechanical thud through the seat/floor — stop immediately.',
                    'rms_g'        => '> 0.70 g sustained — structural vibration beyond acceptable limits for continued operation.',
                    'crest_factor' => '> 5.0 — HAVEN red-zone threshold. Engine should not be operated until physically inspected.',
                    'pattern'      => 'Vibration that is rhythmic and synchronized with RPM is usually engine-sourced. Vibration that is speed-dependent (faster when driving faster) is usually wheel/tyre/driveshaft sourced — outside HAVEN\'s classification scope.',
                ],
                'dtc_codes' => ['P0300', 'P0301', 'P0302', 'P0303', 'P0304', 'P0217', 'P0524', 'P0011'],
                'root_causes' => [
                    'HAVEN "Knocking" classification: thermal detonation or fuel-related knock — engine is at risk of piston and rod bearing damage.',
                    'HAVEN "Misfire" classification: unburnt fuel reaching the catalytic converter — fire risk and catalyst destruction if sustained.',
                    'HAVEN "Valve Clearance" classification: mechanical tapping fault — can lead to complete valve train failure if ignored.',
                    'HAVEN "Rough Idle" classification: structural or mount-related vibration — lower immediate risk but indicates progressive mechanical deterioration.',
                    'Temperature gauge in red zone + any HAVEN fault: compound risk — do not continue driving under any circumstances.',
                ],
                'diagnostic_steps' => [
                    'Step 1 (Knocking detected): Reduce engine load immediately — do not accelerate hard. Drive at minimal throttle to the nearest safe stop. Do not exceed 60 km/h. Call for professional assistance.',
                    'Step 2 (Misfire detected): Turn off A/C and all non-essential loads. Drive slowly (below 40 km/h) to a workshop. Avoid hard acceleration — unburnt fuel in the exhaust is a fire hazard.',
                    'Step 3 (Valve Clearance detected): The vehicle is still driveable but schedule professional inspection within 48 hours. Avoid high RPM (do not exceed 3,000 RPM until inspected).',
                    'Step 4 (Rough Idle detected): Monitor for deterioration. If vibration worsens noticeably during a single drive session, pull over and re-run HAVEN.',
                    'Step 5 (Any fault + warning light): Never ignore a Check Engine Light accompanied by a HAVEN fault classification. The combination of both signals indicates a confirmed issue — seek mechanical inspection immediately.',
                ],
                'preventive_actions' => [
                    'Run HAVEN diagnostics proactively every 2–4 weeks — catching faults early avoids emergency driving situations.',
                    'Keep the HAVEN app bookmarked for quick access — run a capture the moment you hear a new, unfamiliar sound from the engine.',
                    'Know your vehicle\'s normal baseline from previous HAVEN sessions — this makes new anomalies immediately obvious.',
                    'Always carry the HAVEN report PDF when travelling long distances — it provides your mechanic with a data snapshot if you break down away from your regular workshop.',
                ],
                'care_tips' => [
                    'If you smell burning oil, rubber, or coolant — pull over immediately regardless of what HAVEN last said. Sensor-based systems cannot detect all failure modes.',
                    'Keep an emergency kit in your vehicle: jumper cables, coolant top-up bottle, engine oil (correct grade), and basic tools.',
                    'Program your roadside assistance number into your phone — do not attempt engine repairs on the side of a busy road.',
                    'After any roadside incident, run a fresh HAVEN session before resuming normal driving — confirm the issue is resolved, not masked.',
                ],
                'risks' => [
                    'Risk: Driving with a confirmed knock classification at highway speed risks catastrophic engine failure (connecting rod breakthrough the block) while at speed.',
                    'Risk: Sustained misfire causes catalytic converter temperatures exceeding 900°C — the converter can ignite surrounding heat shields and vehicle undercoating.',
                    'Risk: Attempting to diagnose a fault by yourself on a live engine in an unsafe location (roadside, dark area) — prioritize personal safety over diagnosis.',
                    'Risk: Dismissing HAVEN\'s result as a false positive without a second confirmation session — if the classification repeats across two separate sessions, it is not a false positive.',
                ],
                'haven_scope_note' => 'HAVEN AI provides fault classification based on acoustic and vibration data collected at idle or low-speed conditions. It does not monitor your vehicle in real-time while driving. Always run HAVEN in a safe, stationary position. If symptoms appear while driving, pull over safely first, then run a diagnostic session.',
            ],
        ];
    }

    /**
     * OBD-II Diagnostic Trouble Codes Dictionary.
     */
    protected function getDtcList(): array
    {
        return [
            [
                'code' => 'P0300',
                'title' => 'Random/Multiple Cylinder Misfire Detected',
                'system' => 'Ignition & Fuel',
                'severity' => 'High',
                'severity_color' => 'amber',
                'symptoms' => 'Flashing Check Engine Light, engine vibration, sluggish acceleration, fuel odor in exhaust.',
                'causes' => 'Fouled spark plugs, weak fuel pump, low fuel pressure, vacuum leak, bad crankshaft position sensor.',
            ],
            [
                'code' => 'P0301 – P0304',
                'title' => 'Cylinder 1–4 Specific Misfire Detected',
                'system' => 'Ignition & Fuel',
                'severity' => 'High',
                'severity_color' => 'amber',
                'symptoms' => 'Rhythmic stumbling, cylinder drop, rough idle, loss of horsepower.',
                'causes' => 'Specific cylinder ignition coil failure, burned spark plug, clogged fuel injector, low compression.',
            ],
            [
                'code' => 'P0325',
                'title' => 'Knock Sensor 1 Circuit Malfunction',
                'system' => 'Engine Telemetry',
                'severity' => 'Medium',
                'severity_color' => 'amber',
                'symptoms' => 'Retarded engine timing, sluggish low-end torque, increased fuel consumption.',
                'causes' => 'Knock sensor piezo element failure, wiring harness rodent damage, corroded electrical connector.',
            ],
            [
                'code' => 'P0327',
                'title' => 'Knock Sensor 1 Circuit Low Input',
                'system' => 'Engine Telemetry',
                'severity' => 'Medium',
                'severity_color' => 'amber',
                'symptoms' => 'Engine computer cannot detect knock feedback, ECU enters safe/limp timing map.',
                'causes' => 'Sensor signal wire shorted to ground, incorrect sensor installation torque, internal sensor failure.',
            ],
            [
                'code' => 'P0171',
                'title' => 'System Too Lean (Bank 1)',
                'system' => 'Air-Fuel Ratio',
                'severity' => 'Medium',
                'severity_color' => 'amber',
                'symptoms' => 'Hesitation upon tip-in acceleration, rough idle, elevated combustion chamber temperature.',
                'causes' => 'Intake vacuum leak, contaminated MAF sensor wire, low fuel pressure, clogged fuel filter.',
            ],
            [
                'code' => 'P0115',
                'title' => 'Engine Coolant Temperature Circuit Malfunction',
                'system' => 'Cooling System',
                'severity' => 'High',
                'severity_color' => 'amber',
                'symptoms' => 'Radiator fan constantly runs, hard cold starting, poor fuel economy, gauge stuck at cold.',
                'causes' => 'ECT sensor thermistor open circuit, damaged wiring pigtail, low coolant level around sensor probe.',
            ],
            [
                'code' => 'P0128',
                'title' => 'Coolant Thermostat (Coolant Temp Below Regulating Temp)',
                'system' => 'Cooling System',
                'severity' => 'Low',
                'severity_color' => 'teal',
                'symptoms' => 'Heater blows lukewarm air, engine takes excessive time to reach operating temperature.',
                'causes' => 'Thermostat stuck open, weak thermostat return spring, faulty ECT sensor.',
            ],
            [
                'code' => 'P0217',
                'title' => 'Engine Coolant Over-Temperature Condition',
                'system' => 'Cooling System',
                'severity' => 'Critical',
                'severity_color' => 'red',
                'symptoms' => 'Temperature gauge in red zone, boiling coolant in overflow tank, engine knocking, steam from hood.',
                'causes' => 'Thermostat stuck closed, radiator fan motor failure, massive coolant loss, water pump impeller detached.',
            ],
            [
                'code' => 'P0505',
                'title' => 'Idle Air Control System Malfunction',
                'system' => 'Air Intake',
                'severity' => 'Medium',
                'severity_color' => 'amber',
                'symptoms' => 'Engine stalls when coming to a stop, high idle (1,500+ RPM), fluctuating surge idle.',
                'causes' => 'Carbon buildup in IAC passage, burned IAC stepper motor windings, vacuum leak.',
            ],
            [
                'code' => 'P0101',
                'title' => 'Mass or Volume Air Flow Circuit Range/Performance',
                'system' => 'Air Intake',
                'severity' => 'Medium',
                'severity_color' => 'amber',
                'symptoms' => 'Black smoke from exhaust, hesitation, unstable idle, engine runs rich.',
                'causes' => 'Dirty hot-wire MAF sensor element, air filter box improperly latched, torn intake ducting.',
            ],
            [
                'code' => 'P0524',
                'title' => 'Engine Oil Pressure Too Low',
                'system' => 'Lubrication System',
                'severity' => 'Critical',
                'severity_color' => 'red',
                'symptoms' => 'Oil pressure warning light on, audible valvetrain tapping/clatter, elevated Crest Factor (>4.5) on HAVEN telemetry.',
                'causes' => 'Critically low oil level, failed oil pump, clogged oil pickup screen, severe bearing clearance wear, oil fuel dilution.',
            ],
            [
                'code' => 'P0562',
                'title' => 'System Voltage Low',
                'system' => 'Electrical & Charging',
                'severity' => 'High',
                'severity_color' => 'amber',
                'symptoms' => 'Dim headlights, slow starter cranking, erratic sensor data, HAVEN INA219 telemetry below 12.0V.',
                'causes' => 'Failing alternator, loose alternator serpentine belt, weak/sulfated battery, corroded battery terminal cables.',
            ],
            [
                'code' => 'P0563',
                'title' => 'System Voltage High',
                'system' => 'Electrical & Charging',
                'severity' => 'Critical',
                'severity_color' => 'red',
                'symptoms' => 'Blown fuses, burning electrical odor, battery bubbling/acid smell, HAVEN INA219 telemetry above 15.0V.',
                'causes' => 'Internal alternator voltage regulator failure, damaged ECU voltage reference circuit, loose ground strap.',
            ],
            [
                'code' => 'P0011',
                'title' => 'Camshaft Position "A" Timing Over-Advanced (Bank 1)',
                'system' => 'Valvetrain & VVT',
                'severity' => 'High',
                'severity_color' => 'amber',
                'symptoms' => 'Rough idle, engine stall, metallic tapping at camshaft frequency, elevated high-frequency spectral centroid.',
                'causes' => 'Oil sludge clogging VVT solenoid spool valve, stretched timing chain, worn camshaft phaser gear, low oil pressure.',
            ],
            [
                'code' => 'P0420',
                'title' => 'Catalyst System Efficiency Below Threshold (Bank 1)',
                'system' => 'Exhaust & Emissions',
                'severity' => 'Medium',
                'severity_color' => 'amber',
                'symptoms' => 'Check Engine Light illuminated, sulfur/rotten egg smell in exhaust, slight power deficit.',
                'causes' => 'Prolonged driving with active engine misfire (P0300) overheating catalyst matrix, oil burning, lead or silicone fuel contamination.',
            ],
        ];
    }

    /**
     * Learning Topics & Categories Repository.
     */
    protected function getTopics(): array
    {
        $allModules = $this->getModules();

        $topics = [
            'mechanical-faults' => [
                'slug'        => 'mechanical-faults',
                'title'       => 'Mechanical Faults',
                'category'    => 'Mechanical Faults',
                'description' => 'Valvetrain lash, camshaft lobe wear, lifter bleed-down, and engine mount resonance.',
                'icon'        => 'wrench',
                'color'       => 'amber',
                'module_slugs'=> ['valve-clearance', 'loose-component'],
            ],
            'combustion-ignition' => [
                'slug'        => 'combustion-ignition',
                'title'       => 'Combustion & Ignition',
                'category'    => 'Combustion & Ignition',
                'description' => 'Cylinder misfires, unburnt fuel detection, spark plug wear, and cyclic torque deficits.',
                'icon'        => 'flame',
                'color'       => 'red',
                'module_slugs'=> ['misfire'],
            ],
            'safety-preparation' => [
                'slug'        => 'safety-preparation',
                'title'       => 'Safety & Emergency Procedures',
                'category'    => 'Safety & Preparation',
                'description' => 'Pre-diagnostic safety checks, active fault driving rules, and workshop referral guidance.',
                'icon'        => 'shield',
                'color'       => 'rose',
                'module_slugs'=> ['pre-diagnostic-safety', 'when-to-see-mechanic', 'safe-driving-active-fault'],
            ],
            'baseline-reference' => [
                'slug'        => 'baseline-reference',
                'title'       => 'Baseline Engine Reference',
                'category'    => 'Baseline Reference',
                'description' => 'Acoustic and vibration telemetry standards of healthy gasoline ICE at idle and revving.',
                'icon'        => 'check-circle',
                'color'       => 'teal',
                'module_slugs'=> ['normal-operation'],
            ],
            'signal-processing' => [
                'slug'        => 'signal-processing',
                'title'       => 'Signal Processing & DSP',
                'category'    => 'Signal Processing',
                'description' => 'Mel-Frequency Cepstral Coefficients (MFCCs), spectral centroid, and zero-crossing analysis.',
                'icon'        => 'activity',
                'color'       => 'cyan',
                'module_slugs'=> ['acoustic-signal-analysis'],
            ],
            'hardware-iot' => [
                'slug'        => 'hardware-iot',
                'title'       => 'Hardware & ESP32 Telemetry',
                'category'    => 'Hardware & IoT',
                'description' => 'MPU6050/ADXL345 accelerometer mounting, ESP32 wiring, Peak G, and CSV logging.',
                'icon'        => 'cpu',
                'color'       => 'indigo',
                'module_slugs'=> ['esp32-vibration-telemetry'],
            ],
            'preventive-maintenance' => [
                'slug'        => 'preventive-maintenance',
                'title'       => 'Preventive Fluid Maintenance',
                'category'    => 'Preventive Maintenance',
                'description' => 'Engine oil degradation, viscosity breakdown, fluid contamination, and lubrication health.',
                'icon'        => 'droplet',
                'color'       => 'emerald',
                'module_slugs'=> ['engine-oil-maintenance'],
            ],
            'electrical-systems' => [
                'slug'        => 'electrical-systems',
                'title'       => 'Electrical & Charging Systems',
                'category'    => 'Electrical Systems',
                'description' => 'INA219 current sensing, alternator ripple frequencies, and battery voltage health.',
                'icon'        => 'battery',
                'color'       => 'amber',
                'module_slugs'=> ['battery-charging-health'],
            ],
            'thermal-systems' => [
                'slug'        => 'thermal-systems',
                'title'       => 'Thermal Management & Cooling',
                'category'    => 'Thermal Systems',
                'description' => 'DS18B20 temperature tracking, coolant circulation, and thermal detonation hazards.',
                'icon'        => 'thermometer',
                'color'       => 'orange',
                'module_slugs'=> ['cooling-thermal-management'],
            ],
            'using-haven-ai' => [
                'slug'        => 'using-haven-ai',
                'title'       => 'Using HAVEN AI Diagnostics',
                'category'    => 'Using HAVEN AI',
                'description' => 'Interpreting AI classification scores, acoustic spectrograms, and comprehensive diagnostic reports.',
                'icon'        => 'file-text',
                'color'       => 'blue',
                'module_slugs'=> ['reading-haven-report'],
            ],
        ];

        // Attach populated modules to each topic
        foreach ($topics as $slug => &$topic) {
            $topic['modules'] = [];
            foreach ($topic['module_slugs'] as $modSlug) {
                if (isset($allModules[$modSlug])) {
                    $topic['modules'][$modSlug] = $allModules[$modSlug];
                }
            }
            $topic['study_count'] = count($topic['modules']);
        }
        unset($topic);

        return $topics;
    }

    /**
     * Learning Modules Hub (Index).
     */
    public function index(Request $request): View
    {
        $allModules = $this->getModules();
        $allTopics  = $this->getTopics();
        $search     = strtolower(trim($request->query('q', '')));

        $topics = collect($allTopics)->filter(function ($t) use ($search) {
            if ($search) {
                $matchTopic = str_contains(strtolower($t['title']), $search) ||
                              str_contains(strtolower($t['description']), $search) ||
                              str_contains(strtolower($t['category']), $search);

                $matchStudy = collect($t['modules'])->some(function ($m) use ($search) {
                    return str_contains(strtolower($m['title']), $search) ||
                           str_contains(strtolower($m['summary']), $search);
                });

                return $matchTopic || $matchStudy;
            }
            return true;
        })->all();

        $matchingStudies = [];
        if ($search) {
            $matchingStudies = collect($allModules)->filter(function ($mod) use ($search) {
                return str_contains(strtolower($mod['title']), $search) ||
                       str_contains(strtolower($mod['summary']), $search) ||
                       str_contains(strtolower($mod['category']), $search);
            })->all();
        }

        return view('learning.index', [
            'topics'          => $topics,
            'allTopics'       => $allTopics,
            'search'          => $search,
            'totalCount'      => count($allModules),
            'topicCount'      => count($allTopics),
            'dtcCount'        => count($this->getDtcList()),
            'matchingStudies' => $matchingStudies,
            'modules'         => $allModules,
            'categories'      => collect($allModules)->pluck('category')->unique()->values()->all(),
            'activeCategory'  => 'all',
            'learningStats'   => auth()->check() ? auth()->user()->getLearningStats() : null,
        ]);
    }

    /**
     * Display a specific Learning Topic and all its related studies.
     */
    public function topic(string $slug): View
    {
        $allTopics = $this->getTopics();

        abort_unless(isset($allTopics[$slug]), 404, 'Learning topic not found.');

        $topic = $allTopics[$slug];
        $otherTopics = collect($allTopics)->where('slug', '!==', $slug)->all();

        return view('learning.topic', [
            'topic'         => $topic,
            'modules'       => $topic['modules'],
            'otherTopics'   => $otherTopics,
            'allTopics'     => $allTopics,
            'dtcCount'      => count($this->getDtcList()),
            'learningStats' => auth()->check() ? auth()->user()->getLearningStats() : null,
        ]);
    }

    /**
     * Display a single module tutorial.
     */
    public function show(string $slug): View
    {
        $modules = $this->getModules();

        abort_unless(isset($modules[$slug]), 404, 'Learning module not found.');

        $module = $modules[$slug];
        $topics = $this->getTopics();

        // Auto-mark module as completed for authenticated user
        if (auth()->check()) {
            $user = auth()->user();
            $progress = \App\Models\LearningProgress::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'completed_modules' => [],
                    'quiz_best_score'   => 0,
                    'quiz_total'        => 10,
                    'quiz_attempts'     => 0,
                ]
            );
            $completed = is_array($progress->completed_modules) ? $progress->completed_modules : [];
            if (!in_array($slug, $completed, true)) {
                $completed[] = $slug;
                $progress->update(['completed_modules' => $completed]);
            }
        }

        // Find parent topic
        $parentTopic = null;
        foreach ($topics as $t) {
            if (in_array($slug, $t['module_slugs'])) {
                $parentTopic = $t;
                break;
            }
        }

        // Related studies: prefer studies in the same topic, then other modules
        $related = [];
        if ($parentTopic) {
            foreach ($parentTopic['modules'] as $mSlug => $m) {
                if ($mSlug !== $slug) {
                    $related[$mSlug] = $m;
                }
            }
        }
        if (count($related) < 3) {
            foreach ($modules as $mSlug => $m) {
                if ($mSlug !== $slug && !isset($related[$mSlug])) {
                    $related[$mSlug] = $m;
                    if (count($related) >= 3) break;
                }
            }
        }

        return view('learning.show', [
            'module'        => $module,
            'parentTopic'   => $parentTopic,
            'related'       => $related,
            'learningStats' => auth()->check() ? auth()->user()->getLearningStats() : null,
        ]);
    }

    /**
     * OBD-II Diagnostic Trouble Codes Reference Directory.
     */
    public function dtc(Request $request): View
    {
        $dtcs = $this->getDtcList();
        $search = strtolower(trim($request->query('q', '')));

        if ($search) {
            $dtcs = collect($dtcs)->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['code']), $search) ||
                       str_contains(strtolower($item['title']), $search) ||
                       str_contains(strtolower($item['system']), $search) ||
                       str_contains(strtolower($item['symptoms']), $search);
            })->values()->all();
        }

        return view('learning.dtc', [
            'dtcs' => $dtcs,
            'search' => $search,
        ]);
    }

    /**
     * Interactive Diagnostic Knowledge Quiz.
     */
    public function quiz(): View
    {
        $questions = [
            [
                'id' => 1,
                'question' => 'Which combination of acoustic and accelerometer telemetry strongly indicates Improper Valve Clearance?',
                'options' => [
                    'A' => 'High RMS G (>0.6g) with low peak G and low ZCR (<0.10)',
                    'B' => 'Peak G > 3.6g, Crest Factor > 4.2, elevated mid-frequency acoustics (2.5–6.5 kHz), tapping at half engine RPM',
                    'C' => 'Periodic low-frequency exhaust chuffing with nominal vibration (<1.0g)',
                    'D' => 'Zero vibration with steady 100 Hz hum',
                ],
                'correct' => 'B',
                'explanation' => 'Improper valve clearance (excessive lash) causes the camshaft lobe to impact the rocker arm/tappet sharply at every valve event, generating repetitive high-crest-factor impulse spikes (Peak G >3.6g, Crest Factor >4.2) and high-frequency metallic tapping in the 2.5–6.5 kHz acoustic band at camshaft speed (half engine RPM).',
            ],
            [
                'id' => 2,
                'question' => 'What is the physical meaning of "Crest Factor" in the HAVEN vibration feature pipeline?',
                'options' => [
                    'A' => 'The ratio of engine RPM to vehicle road speed',
                    'B' => 'The total number of samples collected divided by duration',
                    'C' => 'The ratio of Peak G-force to Root-Mean-Square (RMS) G-force (Peak / RMS)',
                    'D' => 'The frequency of spark plug firing events',
                ],
                'correct' => 'C',
                'explanation' => 'Crest factor is calculated as Peak G / RMS G. It measures the impulsiveness or "spikiness" of the signal. Continuous steady vibration has a low crest factor (~2.0), while violent knocking creates sharp impulse spikes yielding crest factors > 4.0.',
            ],
            [
                'id' => 3,
                'question' => 'A user records an engine with a cyclical torque drop every 720 degrees and an erratic Zero Crossing Rate (ZCR). Which DTC code is most likely stored?',
                'options' => [
                    'A' => 'P0300 / P0301 (Cylinder Misfire)',
                    'B' => 'P0128 (Thermostat Coolant Temp Below Regulating)',
                    'C' => 'P0505 (Idle Air Control Malfunction)',
                    'D' => 'P0420 (Catalyst System Efficiency)',
                ],
                'correct' => 'A',
                'explanation' => 'In a 4-stroke engine, every cylinder completes a power cycle once every 720° of crankshaft rotation. A cylinder failing to combust creates a cyclic deceleration pulse every 720°, characteristic of a misfire (P0300/P0301).',
            ],
            [
                'id' => 4,
                'question' => 'In digital audio feature extraction, what do Mel-Frequency Cepstral Coefficients (MFCCs) represent?',
                'options' => [
                    'A' => 'The raw voltage output of the microphone',
                    'B' => 'A compact representation of the spectral envelope mapped to human auditory perception',
                    'C' => 'The air temperature around the exhaust manifold',
                    'D' => 'The battery charging rate from the alternator',
                ],
                'correct' => 'B',
                'explanation' => 'MFCCs compress acoustic spectra using triangular filterbanks spaced along the non-linear Mel scale. They capture the timbre and resonant characteristics of the engine sound while discarding pitch variations.',
            ],
            [
                'id' => 5,
                'question' => 'When mounting an ESP32 accelerometer (MPU6050/ADXL345) to capture ICE vibration telemetry, what is the recommended practice?',
                'options' => [
                    'A' => 'Hang the sensor loosely on the radiator hose',
                    'B' => 'Place the sensor on the soft rubber air cleaner housing',
                    'C' => 'Securely mount the sensor with high-rigidity coupling to the cylinder head or engine block',
                    'D' => 'Keep the sensor inside the passenger glove compartment',
                ],
                'correct' => 'C',
                'explanation' => 'To capture true internal combustion mechanical forces without false resonant harmonics, the sensor must be rigidly coupled directly to metallic engine structure (e.g., cylinder head or block bolt boss).',
            ],
            [
                'id' => 6,
                'question' => 'Before recording audio for HAVEN AI diagnostic classification, which preparation step is essential to ensure high data integrity?',
                'options' => [
                    'A' => 'Keep the vehicle moving at 60 km/h with air conditioning on full blast',
                    'B' => 'Warm engine to operating temperature (85–100°C), turn OFF A/C and radio, and hold the microphone 30–50 cm from the valve cover in a quiet stationary setting',
                    'C' => 'Place the smartphone directly touching the red-hot exhaust manifold',
                    'D' => 'Disconnect the car battery completely before recording',
                ],
                'correct' => 'B',
                'explanation' => 'Acoustic feature extraction (MFCCs) requires high signal-to-noise ratio. Running A/C adds overlapping 120–180 Hz compressor harmonics, cold engines have unstable fast-idle RPM, and touching vibrating parts saturates the microphone.',
            ],
            [
                'id' => 7,
                'question' => 'If HAVEN classifies an active test as severe "Knocking" with Peak G > 4.5g and Crest Factor > 5.0, what is the immediate recommended action?',
                'options' => [
                    'A' => 'Continue driving normally at high RPM on the highway for the next month',
                    'B' => 'Immediately reduce engine load, avoid hard acceleration, pull over safely, and arrange professional mechanical inspection to prevent catastrophic internal damage',
                    'C' => 'Ignore the result because HAVEN replaces the need for a physical mechanic',
                    'D' => 'Spray cold water over the hot engine block while the engine is revving',
                ],
                'correct' => 'B',
                'explanation' => 'Extreme impulsiveness (Crest Factor > 5.0, Peak G > 4.5g) indicates severe combustion detonation or mechanical impact (rod knock / bearing failure). Continued high-load operation risks piston destruction or throwing a connecting rod through the engine block.',
            ],
            [
                'id' => 8,
                'question' => 'Which of the following falls strictly within HAVEN AI\'s diagnostic capabilities, respecting its operational scope and design?',
                'options' => [
                    'A' => 'Automated physical replacement of spark plugs and direct mechanical repairs',
                    'B' => 'Real-time steering alignment calibration and tyre tread depth measurement',
                    'C' => 'Acoustic sound and ESP32 multi-sensor vibration feature extraction for ICE fault pattern classification and early anomaly detection',
                    'D' => 'Live ECU reflashing and transmission programming while driving at high speed',
                ],
                'correct' => 'C',
                'explanation' => 'HAVEN AI is designed as a software and IoT diagnostic intelligence platform analyzing acoustics and vibration signatures to assist technicians and vehicle owners. It does not replace physical inspection, tools, or professional mechanical repair.',
            ],
            [
                'id' => 9,
                'question' => 'How does severe engine oil degradation or critically low oil level physically manifest in HAVEN telemetry?',
                'options' => [
                    'A' => 'Causes the vibration Crest Factor and upper-frequency spectral centroid to drop to zero',
                    'B' => 'Increases boundary mechanical friction, elevating high-frequency metallic tapping (spectral centroid > 2,000 Hz) and raising the vibration Crest Factor due to un-cushioned metal-to-metal impacts',
                    'C' => 'Lowers engine coolant temperature to freezing temperatures',
                    'D' => 'Converts the audio signal into a pure 10 Hz sine wave',
                ],
                'correct' => 'B',
                'explanation' => 'As lubricant film breaks down or oil pressure starves valvetrain components, hydraulic lash adjusters collapse and metal-to-metal contact occurs, generating high-frequency acoustic chatter (>2,000 Hz) and sharp impulse vibration peaks.',
            ],
            [
                'id' => 10,
                'question' => 'During a HAVEN diagnostic session with active ESP32 telemetry, the INA219 sensor reports a continuous running bus voltage of 11.8V at 1,500 RPM. What does this indicate?',
                'options' => [
                    'A' => 'Normal optimal alternator charging condition',
                    'B' => 'Overcharging due to faulty voltage regulator outputting over 16V',
                    'C' => 'Undercharging / charging system deficiency (alternator failure or slipping belt) that may induce weak ignition and false misfire symptoms',
                    'D' => 'The vehicle has transformed into a fully electric propulsion vehicle',
                ],
                'correct' => 'C',
                'explanation' => 'A healthy automotive charging system outputs 13.8V to 14.4V with the engine running. 11.8V means the engine is discharging the battery, which can weaken spark ignition energy and cause false combustion misfire signatures.',
            ],
        ];

        $learningStats = auth()->check() ? auth()->user()->getLearningStats() : null;

        return view('learning.quiz', [
            'questions'     => $questions,
            'learningStats' => $learningStats,
        ]);
    }

    /**
     * Submit and persist diagnostic quiz results.
     */
    public function submitQuiz(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:50',
            'total' => 'required|integer|min:1|max:50',
        ]);

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $progress = \App\Models\LearningProgress::firstOrCreate(
            ['user_id' => $user->id],
            [
                'completed_modules' => [],
                'quiz_best_score'   => 0,
                'quiz_total'        => $validated['total'],
                'quiz_attempts'     => 0,
            ]
        );

        $score = $validated['score'];
        $total = $validated['total'];
        $pct = (int) round(($score / $total) * 100);

        $newBest = max((int) $progress->quiz_best_score, $score);
        $attempts = ((int) $progress->quiz_attempts) + 1;

        $progress->update([
            'quiz_best_score' => $newBest,
            'quiz_total'      => $total,
            'quiz_attempts'   => $attempts,
            'last_quiz_at'    => now(),
        ]);

        $tier = match(true) {
            $pct === 100 => 'Master Diagnostician',
            $pct >= 80   => 'Senior Diagnostic Specialist',
            $pct >= 60   => 'Field Technician',
            default      => 'Diagnostic Apprentice',
        };

        return response()->json([
            'success'       => true,
            'score'         => $score,
            'total'         => $total,
            'percentage'    => $pct,
            'best_score'    => $newBest,
            'attempts'      => $attempts,
            'tier'          => $tier,
            'message'       => 'Quiz performance saved to your vehicle engineer profile.',
        ]);
    }

    /**
     * Explicitly mark a learning module as completed.
     */
    public function markCompleted(Request $request, string $slug): \Illuminate\Http\JsonResponse
    {
        $modules = $this->getModules();
        abort_unless(isset($modules[$slug]), 404, 'Module not found');

        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $progress = \App\Models\LearningProgress::firstOrCreate(
            ['user_id' => $user->id],
            [
                'completed_modules' => [],
                'quiz_best_score'   => 0,
                'quiz_total'        => 10,
                'quiz_attempts'     => 0,
            ]
        );

        $completed = is_array($progress->completed_modules) ? $progress->completed_modules : [];
        if (!in_array($slug, $completed, true)) {
            $completed[] = $slug;
            $progress->update(['completed_modules' => $completed]);
        }

        return response()->json([
            'success'         => true,
            'completed_count' => count($completed),
            'completed'       => true,
        ]);
    }
}
