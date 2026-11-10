<?php

namespace App\Services;

use App\Models\Diagnostic;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiagnosticAiService
{
    /**
     * Generate structured AI recommendations for a completed diagnostic.
     */
    public function generateRecommendations(Diagnostic $diagnostic): array
    {
        $vehicle = $diagnostic->vehicle;
        $rawCondition = $diagnostic->ml_condition ?? 'normal';
        $condition = ($rawCondition === 'ambient_noise')
            ? 'ambient_noise'
            : Diagnostic::canonicalCondition($rawCondition);
        $rawConfidence = $diagnostic->ml_confidence;
        $confidence = round(($rawConfidence ?? 0.8) * 100, 1);
        $isLowConfidence = ($rawConfidence !== null && $rawConfidence < 0.60);
        $peakG = $diagnostic->vibration_peak_g ?? 0;
        $rmsG = $diagnostic->vibration_rms_g ?? 0;
        $crestFactor = $diagnostic->crest_factor ?? ($rmsG > 0 ? round($peakG / $rmsG, 2) : 0);

        $vehicleName = $vehicle ? "{$vehicle->year} {$vehicle->make} {$vehicle->model}" : 'Vehicle';
        $engine = $vehicle?->engine_type ?: 'Engine';
        $mileage = $vehicle?->mileage_km ? number_format($vehicle->mileage_km).' km' : 'unspecified mileage';

        $recs = $this->buildConditionRecommendations($condition, $vehicleName, $engine, $peakG, $rmsG, $crestFactor);

        if ($isLowConfidence && $condition !== 'ambient_noise') {
            $recs['low_confidence_warning'] = true;
            $recs['urgency'] = "{$recs['urgency']} (Verify via re-test — low ML confidence {$confidence}%)";
            $recs['summary'] = "LOW CONFIDENCE NOTICE ({$confidence}%): Class probabilities were closely split below the 60% certainty threshold. {$recs['summary']}";
            array_unshift(
                $recs['action_checklist'],
                "0. RE-VERIFY CAPTURE ({$confidence}% Confidence): Before purchasing parts, re-run a clean 10-second diagnostic with the sensor firmly coupled to the engine block to confirm this classification."
            );
        }

        return $recs;
    }

    private function buildConditionRecommendations(
        string $condition,
        string $vehicleName,
        string $engine,
        float $peakG,
        float $rmsG,
        float $crestFactor
    ): array {
        switch ($condition) {
            case 'valve_clearance':
            case 'knocking':
                return [
                    'condition' => 'Improper Valve Clearance & Valvetrain Lash',
                    'safety_status' => 'MECHANICAL WEAR · ADJUSTMENT REQUIRED',
                    'safety_color' => 'red',
                    'drive_safety' => 'Driveable with caution at low RPM to a repair facility. Avoid high RPM; excessive valve clearance causes valve seat battering, flattened camshaft lobes, and bent pushrods, while zero-clearance risks burnt exhaust valves.',
                    'urgency' => 'Inspect & Adjust Lash Within 48 Hours',
                    'summary' => "HAVEN detected abnormal high-frequency acoustic impact tapping and mechanical vibration spikes ({$peakG}g Peak, Crest Factor {$crestFactor}) on your {$vehicleName} ({$engine}). The repetitive impact signature corresponds to 0.5x crankshaft rotation (camshaft speed), indicating excessive clearance between the cam lobes, rocker arms, and valve stem tips.",
                    'probable_causes' => [
                        'Excessive valve lash from worn hydraulic lifters or loose manual rocker arm adjusting nuts.',
                        'Worn camshaft lobe profiles or fatigued valve springs.',
                        'Low oil pressure or varnished oil galleys delaying hydraulic lifter pump-up.',
                        'Severe pre-ignition detonation pinging producing acoustic shockwaves.',
                        'Worn connecting rod journal bearings generating impact play under load.',
                    ],
                    'action_checklist' => [
                        '1. Remove cylinder head valve cover to inspect valvetrain rocker arms, pushrods, and cam lobes.',
                        '2. Measure cold valve lash using feeler gauges against factory specification (typically 0.20mm intake, 0.25mm exhaust).',
                        '3. Inspect engine oil level on dipstick; verify oil viscosity matches manufacturer grade (0W-20/5W-30).',
                        '4. Check for varnished hydraulic lash adjusters (HLA); bleed or replace sticky lifters.',
                        '5. Connect an OBD-II scanner to read live knock sensor retard data and fault codes (P0325–P0330).',
                    ],
                    'cost_estimate' => '$60 – $180 (Valve Lash Adjustment / Valve Cover Gasket) to $350 – $750 (Camshaft / Lifter Replacement)',
                    'prompt_suggestions' => [
                        'What is the difference between lifter tick and rod knock?',
                        'How do I test my knock sensor with a multimeter?',
                        'What happens if valve clearance is too tight vs too loose?',
                    ],
                ];

            case 'misfire':
            case 'misfiring':
                return [
                    'condition' => 'Engine Misfire & Combustion Dropout',
                    'safety_status' => 'HIGH ATTENTION · DRIVE WITH CAUTION',
                    'safety_color' => 'amber',
                    'drive_safety' => 'Driveable only at low speed to the nearest service shop. Avoid full throttle; unburnt fuel entering the exhaust will overheat and melt the catalytic converter (replacement cost $800+).',
                    'urgency' => 'Inspect Within 24–48 Hours',
                    'summary' => "Cyclic acoustic dropouts and torque fluctuation ({$peakG}g Peak, {$rmsG}g RMS) detected on your {$vehicleName} ({$engine}). A cylinder is failing to combust cleanly every 720° of crankshaft rotation.",
                    'probable_causes' => [
                        'Worn or fouled spark plug with excessive electrode gap.',
                        'Failing ignition coil pack (COP) with internal insulation breakdown.',
                        'Clogged or leaking fuel injector on the affected cylinder.',
                        'Low cylinder compression from valve recession or head gasket leak.',
                        'Intake manifold runner vacuum leak leaning out individual cylinders.',
                    ],
                    'action_checklist' => [
                        '1. Scan OBD-II trouble codes to identify the specific cylinder (P0301 = Cyl 1, P0302 = Cyl 2, etc.).',
                        '2. Perform the Coil Swap Test: swap coil from misfiring cylinder with an adjacent one; clear codes and re-scan.',
                        '3. Remove spark plug and inspect for fuel fouling, oil wetting, or burned ground electrodes.',
                        '4. Inspect ignition coil boots for white carbon tracking lines or heat cracks.',
                        '5. Test fuel injector pulse using a noid light or stethoscope click check.',
                    ],
                    'cost_estimate' => '$25 – $90 (Spark Plug / DIY Coil) to $250 – $450 (Shop Diagnostics & Replacement)',
                    'prompt_suggestions' => [
                        'How do I do the ignition coil swap test?',
                        'Will a misfire damage my catalytic converter?',
                        'What spark plug gap should I use for my vehicle?',
                    ],
                ];

            case 'loose_component':
            case 'idling_rough':
                return [
                    'condition' => 'Loose or Worn Component Vibration',
                    'safety_status' => 'MODERATE CAUTION · SERVICE RECOMMENDED',
                    'safety_color' => 'amber',
                    'drive_safety' => 'Safe for short-distance city and highway transit. Primary consequence is cabin NVH (Noise, Vibration, Harshness), component fatigue, and secondary stress on exhaust flex pipes.',
                    'urgency' => 'Schedule Service Within 1–2 Weeks',
                    'summary' => "Elevated continuous baseline structural vibration ({$rmsG}g RMS) with moderate peak forces ({$peakG}g) recorded on your {$vehicleName} ({$engine}). This persistent vibration signature points to mechanical mount degradation, loose accessory brackets, or unmetered intake airflow at idle.",
                    'probable_causes' => [
                        'Degraded or collapsed hydraulic/rubber engine and transmission mounts transferring powertrain resonance to the chassis.',
                        'Loose accessory drive mounting bolts (alternator, A/C compressor, power steering pump bracket).',
                        'Worn serpentine belt idler pulley bearing or automatic belt tensioner arm play.',
                        'Dirty throttle body butterfly valve or clogged Idle Air Control (IAC) passages causing idle hunt.',
                        'Torn intake vacuum hose or dried intake manifold gasket.',
                    ],
                    'action_checklist' => [
                        '1. Perform engine mount pry-bar and brake-torque test to inspect rubber bushings for tears or hydraulic fluid leakage.',
                        '2. Check torque on all accessory mounting brackets and exhaust manifold heat shields.',
                        '3. Inspect serpentine belt tensioner pulley for wobbling or bearing play with engine off.',
                        '4. Clean throttle body throat and throttle plate using dedicated throttle body spray cleaner.',
                        '5. Perform visual and smoke/carb-cleaner spray check around intake vacuum lines to find unmetered air leaks.',
                    ],
                    'cost_estimate' => '$20 – $50 (DIY Bracket / Cleaner) to $140 – $320 (Motor Mount Replacement)',
                    'prompt_suggestions' => [
                        'How can I tell if my engine mount is bad?',
                        'How do I perform an engine idle relearn?',
                        'Where are the most common vacuum leak spots?',
                    ],
                ];

            case 'overheating':
                return [
                    'condition' => 'Engine Overheating & Thermal Stress',
                    'safety_status' => 'CRITICAL EMERGENCY · SHUT DOWN ENGINE',
                    'safety_color' => 'red',
                    'drive_safety' => 'Severe danger of catastrophic engine seizure, warped aluminum cylinder head, and blown head gasket ($2,000+ repair). Do NOT drive under any circumstances if coolant is boiling.',
                    'urgency' => 'Immediate Action Before Re-Starting',
                    'summary' => "Acoustic thermal stress signatures and friction vibrations detected on your {$vehicleName}. High-frequency cavitation and metal thermal expansion indicate cooling system failure.",
                    'probable_causes' => [
                        'Thermostat stuck shut, blocking coolant flow from engine block to radiator.',
                        'Radiator cooling fan motor, fuse, or temperature relay failure.',
                        'Coolant leak from radiator end-tanks, split hose, or leaking water pump weep hole.',
                        'Blown head gasket allowing hot combustion gases into the cooling jackets.',
                        'Broken or slipping serpentine belt failing to turn the water pump pulley.',
                    ],
                    'action_checklist' => [
                        '1. SHUT ENGINE OFF IMMEDIATELY and let it cool for at least 30 minutes. NEVER open radiator cap while hot!',
                        '2. Inspect ground beneath engine for green/pink/orange coolant puddles.',
                        '3. Verify electric radiator fan spins freely and turns on when A/C is enabled.',
                        '4. Check upper and lower radiator hoses: if upper is scalding hot and lower is cold, thermostat is stuck shut.',
                        '5. Refill cooling system with proper 50/50 ethylene glycol coolant once engine is completely cool.',
                    ],
                    'cost_estimate' => '$30 – $70 (Thermostat / Cap) to $250 – $600 (Water Pump / Radiator Replacement)',
                    'prompt_suggestions' => [
                        'How long should I wait before opening the radiator cap?',
                        'How do I check if my thermostat is stuck closed?',
                        'What happens if I drive an overheating car for 2 miles?',
                    ],
                ];

            case 'ambient_noise':
            case 'no_engine':
            case 'environmental_noise':
                return [
                    'condition' => 'Ambient / Environmental Noise (No Engine Detected)',
                    'safety_status' => 'INVALID CAPTURE · ENGINE NOT DETECTED',
                    'safety_color' => 'amber',
                    'drive_safety' => 'No vehicle powertrain was coupled to this recording. The captured audio reflects ambient crowd or environmental noise rather than a running vehicle powertrain.',
                    'urgency' => 'Re-capture Diagnostic With Microphone at Engine Bay',
                    'summary' => 'HAVEN AI acoustic and vibrational analysis detected ambient background noise without internal combustion engine harmonics or mechanical block vibration (0.00g RMS). The recording was taken away from an operating vehicle.',
                    'probable_causes' => [
                        'Diagnostic was started in an open area, crowd, or room without an operating engine nearby.',
                        'Vehicle engine was turned off or idling too far from the recording device.',
                        'ESP32 ADXL345 accelerometer was not physically mounted to the engine block.',
                    ],
                    'action_checklist' => [
                        '1. Open vehicle hood and hold microphone within 0.5 meters of the engine block / intake manifold.',
                        '2. Start vehicle and allow it to idle at operating temperature (750–900 RPM).',
                        '3. Firmly attach the ESP32 ADXL345 vibration sensor to an engine metal bracket or cylinder head.',
                        '4. Avoid loud public crowds, heavy traffic, or background talking during recording.',
                    ],
                    'cost_estimate' => '$0.00 (Re-test with device near engine bay)',
                    'prompt_suggestions' => [
                        'Where should I place my phone or mic to record the engine?',
                        'How do I attach the ESP32 vibration sensor to the car?',
                        'What sound does an engine make compared to crowd noise?',
                    ],
                ];

            default: // normal
                return [
                    'condition' => 'Normal Engine Operation (Baseline Idling & Revving)',
                    'safety_status' => 'OPTIMAL HEALTH · SAFE TO DRIVE',
                    'safety_color' => 'green',
                    'drive_safety' => 'Vehicle is operating within normal manufacturer acoustic and mechanical vibration boundaries. No mechanical anomalies detected.',
                    'urgency' => 'Routine Preventive Care',
                    'summary' => "HAVEN telemetry confirmed a smooth harmonic frequency envelope and balanced vibration profile ({$peakG}g Peak, {$rmsG}g RMS, Crest Factor {$crestFactor}) across both baseline idling and revving operational states on your {$vehicleName} ({$engine}).",
                    'probable_causes' => [
                        'Healthy cylinder compression and balanced fuel delivery across all cylinders.',
                        'Intact engine mounting isolating rotational vibration from vehicle subframe.',
                        'Proper valve clearance lash and optimal ignition timing matching engine specification.',
                    ],
                    'action_checklist' => [
                        '1. Maintain regular oil change intervals per factory schedule (every 5,000–10,000 km).',
                        '2. Inspect air filter and fluid levels (coolant, brake fluid, transmission fluid).',
                        '3. Re-run HAVEN diagnostics every 6 months or whenever an unfamiliar sound develops.',
                    ],
                    'cost_estimate' => '$0 (No repairs needed)',
                    'prompt_suggestions' => [
                        'What is the best oil viscosity for my engine?',
                        'How often should I change spark plugs?',
                        'What should I watch out for as my car reaches 100,000 km?',
                    ],
                ];
        }
    }

    /**
     * Interactive Copilot response generator (Diagnostic Session Assistant).
     */
    public function answerQuestion(Diagnostic $diagnostic, string $question): string
    {
        $q = trim($question);

        // 1. Try Generative LLM if API key is present
        $llmAnswer = $this->callLlmApi($diagnostic, $q);
        if ($llmAnswer !== null && strlen(trim($llmAnswer)) > 0) {
            return $llmAnswer;
        }

        // 2. Local Expert Diagnostic Engine tailored for this specific session
        return $this->expertSessionAnswer($diagnostic, $q);
    }

    /**
     * Query configured LLM Provider with full diagnostic session telemetry.
     */
    protected function callLlmApi(Diagnostic $diagnostic, string $question): ?string
    {
        $geminiKey = config('services.gemini.key');
        $openAiKey = config('services.openai.key');
        $groqKey = config('services.groq.key');

        if (! $geminiKey && ! $openAiKey && ! $groqKey) {
            return null;
        }

        $vehicle = $diagnostic->vehicle;
        $vName = $vehicle ? "{$vehicle->year} {$vehicle->make} {$vehicle->model}" : 'Vehicle';
        $engine = $vehicle?->engine_type ?: 'Engine';
        $mileage = $vehicle?->mileage_km ? number_format($vehicle->mileage_km).' km' : 'unrecorded mileage';
        $cond = ucwords(str_replace('_', ' ', $diagnostic->ml_condition ?? 'normal'));
        $conf = round(($diagnostic->ml_confidence ?? 0.8) * 100, 1);
        $peakG = $diagnostic->vibration_peak_g ?? 0;
        $rmsG = $diagnostic->vibration_rms_g ?? 0;
        $crest = $diagnostic->crest_factor ?? ($rmsG > 0 ? round($peakG / $rmsG, 2) : 0);

        $systemPrompt = "You are HAVEN AI, an ASE Master Diagnostic Specialist analyzing an active diagnostic session for a user's vehicle.\n"
            ."Session Data:\n"
            ."- Vehicle: {$vName} ({$engine}, {$mileage})\n"
            ."- ML Detected Condition: {$cond} ({$conf}% confidence)\n"
            ."- Vibration Peak Acceleration: {$peakG}g Peak\n"
            ."- Vibration Continuous Energy: {$rmsG}g RMS\n"
            ."- Crest Factor: {$crest}\n\n"
            .'Provide direct, technically accurate, safety-focused advice tailored specifically to this vehicle and test result. '
            .'Format cleanly in Markdown with bullet points, and keep responses under 250 words.';

        $systemPrompt = GlobalAiAssistantService::sanitizePromptText($systemPrompt);
        $question = GlobalAiAssistantService::sanitizePromptText($question);

        // 1. Try Groq First (Ultra-fast LLM Inference)
        if ($groqKey) {
            try {
                $model = config('services.groq.model', 'openai/gpt-oss-120b');
                $response = Http::timeout(8)->withToken($groqKey)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $question],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 650,
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['choices'][0]['message']['content'] ?? null;
                    if ($text) {
                        return trim($text);
                    }
                } else {
                    Log::warning('HAVEN Diagnostic AI Groq call failed', ['status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('HAVEN Diagnostic AI Groq exception: '.$e->getMessage());
            }
        }

        // 2. Try Gemini (Fallback)
        if ($geminiKey) {
            try {
                $model = config('services.gemini.model', 'gemini-1.5-flash');
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}";
                $response = Http::timeout(8)->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => "{$systemPrompt}\n\nUser Question: {$question}"],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'maxOutputTokens' => 650,
                    ],
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if ($text) {
                        return trim($text);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('HAVEN Diagnostic AI Gemini exception: '.$e->getMessage());
            }
        }

        // OpenAI
        if ($openAiKey) {
            try {
                $model = config('services.openai.model', 'gpt-4o-mini');
                $response = Http::timeout(8)->withToken($openAiKey)->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $question],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 650,
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['choices'][0]['message']['content'] ?? null;
                    if ($text) {
                        return trim($text);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('HAVEN Diagnostic AI OpenAI exception: '.$e->getMessage());
            }
        }

        return null;
    }

    /**
     * Local Expert Diagnostic Engine for session queries.
     */
    protected function expertSessionAnswer(Diagnostic $diagnostic, string $question): string
    {
        $q = strtolower(trim($question));
        $recs = $this->generateRecommendations($diagnostic);
        $vehicle = $diagnostic->vehicle;
        $vName = $vehicle ? "{$vehicle->year} {$vehicle->make} {$vehicle->model}" : 'your vehicle';
        $engine = $vehicle?->engine_type ?: 'engine';
        $condition = $diagnostic->ml_condition ?? 'normal';
        $canonical = Diagnostic::canonicalCondition($condition);
        $peakG = $diagnostic->vibration_peak_g ?? 0;
        $rmsG = $diagnostic->vibration_rms_g ?? 0;
        $crest = $diagnostic->crest_factor ?? ($rmsG > 0 ? round($peakG / $rmsG, 2) : 0);

        // -------------------------------------------------------------
        // Specific Suggested Prompt Chips Handling (Direct Exact Answers)
        // -------------------------------------------------------------
        // 1. "Can I drive 5 miles to the nearest garage?"
        if (preg_match('/\b(can i drive|drive 5 miles|nearest garage|drive it home|safe to drive|drive anywhere)\b/i', $q)) {
            if ($canonical === 'valve_clearance') {
                return "⚠️ **Driving Assessment for {$vName}:**\n\n"
                    ."Driveable with caution at low RPM to the nearest service shop. Avoid high RPM or hard uphill acceleration.\n\n"
                    ."• Severe valve lash ({$peakG}g Peak, Crest Factor {$crest}) causes repetitive metal-on-metal impact between camshaft lobes, rocker arms, and valve stems.\n"
                    .'• Keep engine RPM strictly **under 2,000 RPM** to prevent valve seat battering, pushrod bending, or camshaft lobe flattening. If a loud deep thud accompanies the tick, tow immediately to prevent thrown rod damage.';
            } elseif ($canonical === 'loose_component' && ($peakG > 2.0 || $rmsG > 0.55)) {
                return "🟠 **Drive With Caution:** Elevated structural vibration ({$peakG}g Peak) suggests a degraded or failed engine/transmission mount. Limit highway speed to 80 km/h and avoid aggressive acceleration to prevent drivetrain damage.";
            } elseif ($canonical === 'misfire') {
                return '⚠️ **Drive With Caution (Short Distance Only):** You can drive 5 miles to a repair shop, but keep speeds low and **avoid heavy acceleration**. Unburnt fuel enters the exhaust system and can overheat and melt the catalytic converter ($800+ replacement).';
            } elseif ($canonical === 'loose_component') {
                return '🟡 **Yes, Safe for Short Trips:** Loose or worn component vibration (such as degraded motor mounts or accessory bracket vibration) will not cause immediate catastrophic breakdown over 5 miles, but should be torqued and inspected soon.';
            } else {
                return "✅ **Safe to Drive:** Your {$vName} recorded a healthy combustion rhythm ({$peakG}g Peak, {$rmsG}g RMS). It is completely safe for normal highway and city driving.";
            }
        }

        // 2. "What is the difference between lifter tick and rod knock?"
        if (preg_match('/\b(lifter tick.*rod knock|difference.*knock.*tick|knock vs tick)\b/i', $q)) {
            return "⚡ **Lifter Tick vs. Rod Knock on {$vName}:**\n\n"
                ."• **Lifter Tick (Valvetrain):** High-pitched metallic clicking that cycles at **half engine RPM** (camshaft speed). Often louder during cold startup and softens as oil warms up. Non-catastrophic.\n"
                .'• **Rod Knock (Crankshaft Bearing):** Deep, hollow mechanical thud cycling at **full engine RPM** (crankshaft speed). Gets violently louder under load or acceleration. Generates high Peak G shockwaves (>3.5g). Catastrophic risk requiring immediate engine shutdown.';
        }

        // 3. "How do I test my knock sensor with a multimeter?"
        if (preg_match('/\b(test.*knock sensor|multimeter.*knock sensor)\b/i', $q)) {
            return "⚡ **How to Test a Knock Sensor with a Multimeter:**\n\n"
                ."1. **Disconnect Harness:** Unplug the electrical connector from the knock sensor (usually mounted on the engine block under the intake manifold).\n"
                ."2. **Resistance Test (Ohms):** Set multimeter to 200kΩ or 2MΩ. Measure between the sensor signal pin and engine ground (or sensor body). Most piezo sensors should read between **100kΩ and 500kΩ** (or open depending on model spec). Zero ohms indicates a shorted sensor.\n"
                .'3. **AC Voltage Output Test (Dynamic):** Set multimeter to **AC Millivolts (mV AC)**. Connect probes across sensor terminals. Lightly tap the engine block near the sensor with a small metal wrench or brass drift. A healthy piezoelectric crystal will generate a momentary spike of **20mV to 100mV AC** on impact!';
        }

        // 4. "How do I do the ignition coil swap test?"
        if (preg_match('/\b(coil swap|swap test|test ignition coil)\b/i', $q)) {
            return "🔄 **The 10-Minute Ignition Coil Swap Test:**\n\n"
                ."1. **Identify Cylinder:** Use an OBD-II scanner to read which cylinder is misfiring (e.g., **P0301** = Cylinder 1).\n"
                ."2. **Swap Coils:** Unbolt Coil #1 and swap it with Coil #2 (or any adjacent cylinder). Keep the spark plugs in their original cylinders.\n"
                ."3. **Clear Codes & Test Drive:** Clear OBD-II DTCs, start the vehicle, and drive until the Check Engine Light triggers again.\n"
                ."4. **Verify Outcome:**\n"
                ."   • If the code moved to **P0302**, the **coil pack is 100% bad** (replace it).\n"
                .'   • If the code stayed on **P0301**, the coil is fine — inspect spark plug #1, cylinder compression, or injector #1.';
        }

        // 5. "Will a misfire damage my catalytic converter?"
        if (preg_match('/\b(misfire.*damage.*catalytic|damage.*converter|melt.*cat)\b/i', $q)) {
            return "🔥 **Yes, Misfires Quickly Destroy Catalytic Converters:**\n\n"
                ."When a cylinder misfires, raw liquid gasoline is not ignited. The piston pushes this unburnt fuel straight out the exhaust valves into the exhaust downpipe.\n\n"
                ."• When raw fuel reaches the 1,200°F (650°C) honeycomb ceramic core of the catalytic converter, it combusts inside the catalyst itself!\n"
                ."• Internal temperatures spike past **1,800°F (1,000°C)**, melting and collapsing the precious metal honeycomb into an unbreathable ceramic slug ($800–$1,800 replacement cost).\n"
                .'• **Rule:** If the Check Engine Light is **flashing**, stop driving immediately.';
        }

        // 6. "What spark plug gap should I use for my vehicle?"
        if (preg_match('/\b(spark plug gap|plug gap spec|what gap)\b/i', $q)) {
            return "⚡ **Spark Plug Gap Guidelines for {$vName}:**\n\n"
                ."• Most modern four-cylinder engines ({$engine}) specify a gap between **0.028\" and 0.035\"** (0.7mm – 0.9mm) for turbocharged engines, and **0.038\" to 0.044\"** (1.0mm – 1.1mm) for naturally aspirated engines.\n"
                ."• Check the **underhood emissions label (VEIC sticker)** on the underside of your hood for the exact factory gap specification.\n"
                .'• **Warning on Iridium/Platinum Plugs:** Modern fine-wire iridium plugs are pre-gapped at the factory. Do NOT pry open the gap using a coin-type spark plug tool, as the brittle iridium weld bead will shatter. Use a wire gauge gently.';
        }

        // 7. "How do I perform an engine idle relearn?"
        if (preg_match('/\b(idle relearn|relearn procedure|reset idle)\b/i', $q)) {
            return "🔄 **ECU Idle Relearn Procedure (Standard Method):**\n\n"
                ."Perform this after disconnecting the battery or cleaning the throttle body:\n\n"
                ."1. Turn all vehicle accessories OFF (A/C, heater blower, radio, headlights, interior dome lights).\n"
                ."2. Start engine without touching the accelerator pedal.\n"
                ."3. Let the engine idle in Park (or Neutral) for **10 full minutes** until the radiator cooling fan cycles on at least once.\n"
                ."4. Turn the A/C ON full blast and let the engine idle for another **3 minutes** to calibrate under compressor load.\n"
                .'5. Turn ignition OFF for 30 seconds, restart, and take for a gentle 10-minute drive.';
        }

        // 8. "How can I tell if my engine mount is bad?"
        if (preg_match('/\b(tell.*engine mount|bad engine mount|motor mount test)\b/i', $q)) {
            return "🔩 **How to Diagnose a Bad Engine Mount on {$vName}:**\n\n"
                ."1. **Brake-Torque Test (Requires 2 people):** Set parking brake firmly, press foot brake all the way down, shift into Drive, and give the accelerator a quick, gentle tap to 1,500 RPM while a helper watches the engine bay from the side. If the engine lifts or rocks excessively (> 1 inch), a mount is broken.\n"
                ."2. **Hydraulic Mount Leakage:** Many modern mounts are oil-filled. Look for dark brown or black hydraulic fluid stains dripping down the rubber bushing housing.\n"
                .'3. **Vibration Test:** The cabin shakes violently at idle when in Drive at a stoplight, but smooths out the instant you shift into Neutral.';
        }

        // 9. "Where are the most common vacuum leak spots?"
        if (preg_match('/\b(vacuum leak|common vacuum spots|find vacuum leak)\b/i', $q)) {
            return "🔍 **Most Common Engine Vacuum Leak Locations:**\n\n"
                ."1. **PCV Valve Hose & Elbow:** The rubber PCV hose softens from hot oil vapors and collapses or splits at the intake manifold port.\n"
                ."2. **Intake Air Duct Bellows:** The pleated rubber intake hose between the MAF sensor and throttle body often cracks on the underside where it cannot be seen.\n"
                ."3. **Intake Manifold Gaskets:** Dried graphite/silicone gaskets leak unmetered air into cylinder runners.\n"
                ."4. **Brake Booster Vacuum Hose & Check Valve:** Connects the brake booster to intake vacuum.\n"
                ."5. **Testing Tip:** With engine idling, lightly spray aerosol carburetor cleaner or unlit propane around suspected joints. If engine idle RPM momentarily surges, you've found the leak!";
        }

        // 10. "How long should I wait before opening the radiator cap?"
        if (preg_match('/\b(how long.*wait.*radiator cap|open.*radiator cap|hot radiator cap)\b/i', $q)) {
            return "🛑 **Radiator Cap Safety Warning:**\n\n"
                ."• **Wait at least 30 to 45 minutes** after shutting off the engine.\n"
                ."• An automotive cooling system operates under **14–18 PSI of pressure**, raising the boiling point of coolant past 230°F (110°C). Opening the cap while hot releases pressure instantly, causing the coolant to flash-boil into scalding steam that will severely burn your hands and face.\n"
                .'• **Safety Check:** Squeeze the upper radiator hose with a thick rag. If the hose feels hard and pressurized like an inflated bike tire, do NOT open the cap yet.';
        }

        // 11. "How do I check if my thermostat is stuck closed?"
        if (preg_match('/\b(thermostat.*stuck|test thermostat)\b/i', $q)) {
            return "🌡️ **How to Test for a Stuck-Closed Thermostat:**\n\n"
                ."1. **Two-Hose Temperature Test:** With the engine warmed to operating temp (gauge reading hot):\n"
                ."   • Feel the **Upper Radiator Hose** (hot coolant entering radiator from engine block). It should be scalding hot.\n"
                ."   • Carefully feel the **Lower Radiator Hose** (coolant returning from radiator to engine through thermostat).\n"
                ."   • **If Upper is boiling hot and Lower is cool to the touch**, the thermostat is stuck closed and preventing coolant circulation!\n"
                .'2. **Stove Pot Test (Bench Test):** Drop the removed thermostat into a pot of water on the stove with a cooking thermometer. It should open visibly around 180°F–195°F (82°C–90°C).';
        }

        // 12. "What happens if I drive an overheating car for 2 miles?"
        if (preg_match('/\b(drive.*overheating.*2 miles|what happens if.*overheat)\b/i', $q)) {
            return "🛑 **Consequences of Driving 2 Miles Overheating:**\n\n"
                ."Modern engines use aluminum cylinder heads bolted to cast iron or aluminum blocks. Aluminum expands twice as fast as cast iron under excessive heat:\n\n"
                ."1. **Cylinder Head Warpage:** Within 60–90 seconds of steam generation, the aluminum head bows, rupturing the multi-layer steel (MLS) head gasket.\n"
                ."2. **Cylinder Scoring:** Heat causes aluminum pistons to swell inside the cylinders, scuffing and scoring the cylinder walls.\n"
                ."3. **Engine Seizure:** At extreme temperatures, connecting rod bearings weld to the crankshaft journals, locking the engine completely.\n"
                .'• **Cost difference:** $50 (tow truck) vs $2,500–$5,000 (complete engine rebuild). Never drive an overheating vehicle.';
        }

        // 13. "What is the best oil viscosity for my engine?"
        if (preg_match('/\b(oil viscosity|best oil|what oil|oil grade|5w-30|0w-20)\b/i', $q)) {
            return "🛢️ **Recommended Engine Oil for {$vName}:**\n\n"
                ."• Look at your engine oil filler cap under the hood — the manufacturer permanently stamps the factory-recommended SAE viscosity grade directly on the cap (typically **0W-20, 5W-20, or 5W-30 Full Synthetic** for modern {$vName}).\n"
                ."• **Why Viscosity Matters:** Modern Variable Valve Timing (VVT) actuators use fine hydraulic oil passages that require rapid flow on cold startups. Using oil that is too thick (like 10W-40) can trigger VVT DTC codes (P0011/P0016) and cold-start rattle.\n"
                ."• Always look for the **API 'SP' or ILSAC 'GF-6'** certification starburst symbol on the bottle for maximum timing chain and pre-ignition protection.";
        }

        // 14. "What should I watch out for as my car reaches 100,000 km?"
        if (preg_match('/\b(100,000|100k|milestone|major service|watch out for)\b/i', $q)) {
            return "🏁 **Major 100,000 km (60,000 mile) Preventive Maintenance Checklist for {$vName}:**\n\n"
                ."1. **Spark Plugs:** Replace iridium spark plugs and inspect coil boots for carbon tracking.\n"
                ."2. **Transmission Fluid (ATF):** Drain and refill automatic transmission fluid to preserve clutch pack life.\n"
                ."3. **Cooling System Flush:** Drain and flush ethylene glycol coolant to refresh corrosion inhibitors.\n"
                ."4. **Serpentine Drive Belt & Tensioner:** Inspect belt for micro-cracks (more than 3 cracks per inch requires replacement) and check tensioner pulley bearing play.\n"
                ."5. **Brake Fluid:** Moisture test brake fluid; flush and bleed with fresh DOT 3 or DOT 4 fluid.\n"
                .'6. **Suspension Bushings:** Inspect sway bar end links, ball joint boots, and control arm bushings for tears.';
        }

        // -------------------------------------------------------------
        // Cost & Price Questions
        // -------------------------------------------------------------
        if (preg_match('/\b(cost|price|how much|estimate|expensive|repair bill)\b/i', $q)) {
            return "💰 **Estimated Repair Range for {$vName}:**\n\n"
                ."• **Detected Condition:** {$recs['condition']}\n"
                ."• **Estimated Cost Range:** {$recs['cost_estimate']}\n"
                ."• **Urgency:** {$recs['urgency']}\n\n"
                .'💡 *Money-Saving Tip:* If this is a DIY job (such as cleaning the throttle body, replacing a single ignition coil, or changing spark plugs), parts typically cost under $50 on RockAuto or Amazon. If visiting an independent repair shop, request an OBD-II diagnostic scan first before authorizing parts replacement.';
        }

        // -------------------------------------------------------------
        // Fix / Repair / Action Plan Questions
        // -------------------------------------------------------------
        if (preg_match('/\b(how (to|do i) (fix|repair)|what (should|can) i do|next steps|action plan|checklist|step by step)\b/i', $q)) {
            $steps = implode("\n", array_slice($recs['action_checklist'], 0, 4));

            return "🔧 **Recommended Action Plan for {$vName}:**\n\n{$steps}\n\nStart with the simplest, lowest-cost checks (visual inspection, fluid levels, coil swap) before replacing expensive parts.";
        }

        // -------------------------------------------------------------
        // Telemetry & Metrics Explanation
        // -------------------------------------------------------------
        if (preg_match('/\b(why|explain|what does.*mean|peak g|rms g|crest factor|vibration numbers)\b/i', $q)) {
            return "🧠 **HAVEN Telemetry Breakdown for {$vName}:**\n\n"
                ."{$recs['summary']}\n\n"
                ."• **Peak Acceleration ({$peakG}g):** Measures instantaneous impact shock. Healthy engines remain under 1.8g; knocks exceed 3.5g.\n"
                ."• **RMS Energy ({$rmsG}g):** Continuous rotational baseline vibration.\n"
                ."• **Crest Factor ({$crest}):** Ratio of peak to continuous vibration. Values > 4.5 flag violent, sharp mechanical impact events.\n"
                ."• **Safety Status:** {$recs['safety_status']}";
        }

        // -------------------------------------------------------------
        // OBD-II Code Lookup inside Session
        // -------------------------------------------------------------
        if (preg_match('/\b([pbc][0-3][0-9]{3})\b/i', $q, $matches)) {
            $code = strtoupper($matches[1]);

            return (new GlobalAiAssistantService)->lookupDtcCode($code);
        }

        // -------------------------------------------------------------
        // Smart Contextual Fallback for Session
        // -------------------------------------------------------------
        return "👋 **HAVEN AI Mechanic:** Analyzing your **{$vName}** diagnostic session (Status: **".ucwords(str_replace('_', ' ', $condition)).'** with **'.round(($diagnostic->ml_confidence ?? 0.8) * 100)."% confidence**):\n\n"
            ."• **Safety Assessment:** {$recs['safety_status']}\n"
            ."• **Driveability:** {$recs['drive_safety']}\n"
            .'• **First Recommended Step:** '.($recs['action_checklist'][0] ?? 'Perform engine inspection.')."\n\n"
            ."You can ask me specific questions like *'Can I drive 5 miles?'*, *'How do I do a coil swap test?'*, or *'What is the repair cost estimate?'*.";
    }
}
