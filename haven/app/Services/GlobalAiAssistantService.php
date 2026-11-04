<?php

namespace App\Services;

use App\Models\Diagnostic;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GlobalAiAssistantService
{
    /**
     * Answer global automotive & system questions for the website AI Orb.
     */
    public function answer(string $question, ?int $userId = null): string
    {
        $q = trim($question);
        $userId = $userId ?? Auth::id();

        // 1. Compile User & Garage Context
        $context = $this->buildUserContext($userId);

        // 2. Try Generative LLM if API Key is configured (Gemini / OpenAI / Groq)
        $llmAnswer = $this->callLlmApi($q, $context);
        if ($llmAnswer !== null && strlen(trim($llmAnswer)) > 0) {
            return $llmAnswer;
        }

        // 3. Fallback to Local Automotive Diagnostic Expert System
        return $this->expertDiagnosticEngine($q, $context);
    }

    /**
     * Gather garage and diagnostic context for the current user (excluding device keys and PII).
     */
    protected function buildUserContext(?int $userId): array
    {
        if (!$userId) {
            return [
                'has_user' => false,
                'vehicles' => collect(),
                'latest_diagnostic' => null,
            ];
        }

        $vehicles = Vehicle::where('user_id', $userId)
            ->select(['id', 'year', 'make', 'model', 'engine_type', 'transmission', 'mileage_km'])
            ->get();

        $latest = Diagnostic::where('user_id', $userId)
            ->select(['id', 'vehicle_id', 'status', 'ml_condition', 'ml_confidence', 'vibration_peak_g', 'created_at'])
            ->with(['vehicle:id,year,make,model'])
            ->latest()
            ->first();

        return [
            'has_user' => true,
            'vehicles' => $vehicles,
            'latest_diagnostic' => $latest,
        ];
    }

    /**
     * Strip device keys, credentials, VINs, and PII before sending prompts to third-party LLMs.
     */
    public static function sanitizePromptText(string $text): string
    {
        $patterns = [
            '/\bhvn_dev_[a-f0-9]+\b/i' => '[REDACTED_DEVICE_KEY]',
            '/\b(?:sk-[a-zA-Z0-9_-]{16,}|AIza[0-9A-Za-z_-]{20,}|gsk_[a-zA-Z0-9_-]{16,})\b/' => '[REDACTED_API_KEY]',
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '[REDACTED_EMAIL]',
            '/\b(?:\+?\d{1,3}[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3,4}[\s.-]?\d{4}\b/' => '[REDACTED_PHONE]',
            '/\b[A-HJ-NPR-Z0-9]{17}\b/i' => '[REDACTED_VIN]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $text) ?? $text;
    }

    /**
     * Query configured LLM Provider (Google Gemini, OpenAI, or Groq).
     */
    protected function callLlmApi(string $question, array $context): ?string
    {
        $geminiKey = config('services.gemini.key');
        $openAiKey = config('services.openai.key');
        $groqKey   = config('services.groq.key');

        // Check if any key is available
        if (!$geminiKey && !$openAiKey && !$groqKey) {
            return null;
        }

        // Build Master System Prompt
        $systemPrompt = "You are HAVEN AI Assistant, an elite automotive intelligence specialist, diagnostic engineer, and precision powertrain consultant. "
            . "You assist drivers, mechanics, and technicians using the HAVEN Automotive Acoustic & Vibration Diagnostics platform.\n\n"
            . "Brand Tone & Style Guidelines:\n"
            . "1. Elegance, precision, and automotive sophistication: professional, welcoming, refined, and reassuring.\n"
            . "2. When the user offers a greeting or pleasantry (e.g., 'hi', 'hello', 'hey', 'good day'), greet them warmly in this exact refined style:\n"
            . "   'Welcome to the Haven Experience! How may I assist you today with vehicle diagnostics, acoustic and vibration telemetry, or powertrain troubleshooting?'\n"
            . "3. Provide direct, mechanically sound, practical automotive diagnostics and troubleshooting steps.\n"
            . "4. Distinguish between dangerous mechanical conditions (e.g. rod knock, oil starvation, flashing CEL) and non-catastrophic wear (e.g. lifter tick, belt squeal, brake dust).\n"
            . "5. Structure your answers cleanly with short paragraphs or crisp bullet points, maintaining an immaculate, uncluttered presentation.\n"
            . "6. If asked about vehicle telemetry, HAVEN analyzes engine audio FFT frequency spectrum and ESP32 accelerometer vibration (Peak G, RMS G, and Crest Factor).\n";

        if ($context['has_user']) {
            if ($context['vehicles']->isNotEmpty()) {
                $vList = $context['vehicles']->map(fn($v) => "{$v->year} {$v->make} {$v->model} ({$v->engine_type}, {$v->mileage_km} km)")->implode(', ');
                $systemPrompt .= "\nUser's Registered Garage: [{$vList}].";
            }
            if ($context['latest_diagnostic']) {
                $ld = $context['latest_diagnostic'];
                $cond = ucwords(str_replace('_', ' ', $ld->ml_condition ?? 'normal'));
                $peak = $ld->vibration_peak_g ?? 'N/A';
                $systemPrompt .= "\nUser's Latest Test Session: Status {$ld->status}, ML Condition: {$cond}, Peak G: {$peak}g.";
            }
        }

        $systemPrompt = self::sanitizePromptText($systemPrompt);
        $question = self::sanitizePromptText($question);

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
                    Log::warning('HAVEN AI Groq call failed', ['status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('HAVEN AI Groq exception: ' . $e->getMessage());
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
                } else {
                    Log::warning('HAVEN AI Gemini call failed', ['status' => $response->status(), 'body' => $response->body()]);
                }
            } catch (\Throwable $e) {
                Log::warning('HAVEN AI Gemini exception: ' . $e->getMessage());
            }
        }

        // Try OpenAI
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
                Log::warning('HAVEN AI OpenAI exception: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Extensive Built-in Automotive Diagnostic Expert Engine (Offline Knowledge Base).
     * Provides accurate, actionable mechanical analysis without returning unrelated canned responses.
     */
    protected function expertDiagnosticEngine(string $question, array $context): string
    {
        $q = strtolower(trim($question));

        // -------------------------------------------------------------
        // 1. Garage Vehicles Inquiry (Explicit listing or garage query)
        // -------------------------------------------------------------
        if (preg_match('/\b(what cars?|what vehicles?|show my (car|cars|garage|vehicles?)|list (my )?(cars?|garage|vehicles?)|view (my )?(cars?|garage|vehicles?)|my registered (cars?|vehicles?)|in my garage|how many cars|registered vehicles?)\b/i', $q) ||
            preg_match('/^(my cars?|my vehicles?|garage)\??$/i', $q)) {
            if ($context['has_user']) {
                $vehicles = $context['vehicles'];
                if ($vehicles->isNotEmpty()) {
                    $list = $vehicles->map(function ($v) {
                        $engine = $v->engine_type ?: 'Standard Powertrain';
                        $mileage = $v->mileage_km ? number_format($v->mileage_km) . ' km' : 'mileage unrecorded';
                        return "• **{$v->year} {$v->make} {$v->model}** ({$engine}, {$mileage})";
                    })->implode("\n");
                    return "🚗 **Your Registered Garage:**\n\n{$list}\n\nSelect any vehicle from the **Diagnostics** menu to record engine audio or stream ESP32 vibration data.";
                }
                return "🚗 You haven't added any vehicles yet! Click **Vehicles** in the top navigation to add your car with automated NHTSA VIN decoding.";
            }
            return "🚗 Please log in to view your registered garage vehicles.";
        }

        // -------------------------------------------------------------
        // 2. User's Recent Diagnostic Results
        // -------------------------------------------------------------
        if (preg_match('/\b(my diagnostic|my last test|my recent test|my results|last scan|recent diagnostic|my test results?)\b/i', $q) ||
            preg_match('/^(my diagnostic|my last test|recent test)\??$/i', $q)) {
            if ($context['has_user'] && $context['latest_diagnostic']) {
                $recent = $context['latest_diagnostic'];
                $vName = $recent->vehicle ? "{$recent->vehicle->year} {$recent->vehicle->make} {$recent->vehicle->model}" : "Vehicle";
                $cond = ucwords(str_replace('_', ' ', $recent->ml_condition ?? 'Pending Analysis'));
                $conf = $recent->ml_confidence ? round($recent->ml_confidence * 100) . "%" : "N/A";
                $peak = $recent->vibration_peak_g ? "{$recent->vibration_peak_g}g" : "N/A";
                $date = $recent->created_at->format('M j, Y - g:i A');

                return "📊 **Your Latest Diagnostic Session (#{$recent->id}):**\n\n"
                    . "• **Vehicle:** {$vName}\n"
                    . "• **Status:** {$recent->status}\n"
                    . "• **AI Condition:** {$cond} ({$conf} confidence)\n"
                    . "• **Peak Vibration:** {$peak}\n"
                    . "• **Timestamp:** {$date}\n\n"
                    . "You can open the **Diagnostics** page to inspect full FFT frequency charts, raw accelerometer waveforms, and AI repair recommendations.";
            }
            return "📋 You don't have any diagnostic sessions recorded yet. Click **Start Diagnostic** to record 10 seconds of engine audio or upload ESP32 accelerometer telemetry!";
        }

        // -------------------------------------------------------------
        // 3. OBD-II Code Lookup (Precise DTC database + SAE J2012 Decoder)
        // -------------------------------------------------------------
        if (preg_match('/\b([pbc][0-3][0-9]{3})\b/i', $q, $matches)) {
            $code = strtoupper($matches[1]);
            return $this->lookupDtcCode($code);
        }

        // -------------------------------------------------------------
        // 4. Starting & Cranking Issues (Clicking, No-Start, Sluggish Crank)
        // -------------------------------------------------------------
        if (preg_match('/\b(won\'?t|wont|cannot|doesn\'?t|doesnt|unable to|fail(s|ed)? to)\s+.*?\b(start|crank|turn over|catch|fire up)\b/i', $q) ||
            preg_match('/\b(car|engine|vehicle)\s+.*?\b(won\'?t|wont|doesn\'?t|doesnt|not|unable to)\s+.*?\b(start|crank|turn over)\b/i', $q) ||
            (preg_match('/\b(start|crank|cranking)\b/i', $q) && preg_match('/\b(won\'?t|wont|doesn\'?t|doesnt|cannot|cant|not|hard to|trouble|fails?|clicking)\b/i', $q)) ||
            preg_match('/\b(no start|hard start|cranks but won\'?t start|cranks no start)\b/i', $q)) {

            if (preg_match('/\b(click|clicking|single click)\b/i', $q)) {
                return "⚡ **Engine Won't Crank (Clicking Sound):**\n\n"
                    . "A single click or rapid chatter usually points to electrical power delivery rather than engine damage:\n\n"
                    . "• **Single Loud Click:** Starter solenoid is energizing, but contacts are burned, starter motor brushes are stuck, or battery voltage drops below 10V under load.\n"
                    . "• **Rapid Machine-Gun Clicking:** Discharged battery (voltage < 12.0V) or heavy corrosion/loose battery terminals.\n"
                    . "• **Diagnostic Checks:**\n"
                    . "  1. Test resting battery voltage with a multimeter across posts (Healthy: **12.6V+**; Low: **<12.2V**).\n"
                    . "  2. Clean white lead-sulfate corrosion off battery terminals with baking soda and wire brush.\n"
                    . "  3. Gently tap starter motor housing with a wrench handle while someone holds key in START position.\n"
                    . "  4. Inspect engine block ground strap for fraying or rust.";
            }

            if (preg_match('/\b(cranks fine|turns over|cranking|cranks normal)\b/i', $q) || preg_match('/\b(cranks no start|won\'?t catch)\b/i', $q)) {
                return "🔄 **Engine Cranks Normally but Won't Start (Crank-No-Start):**\n\n"
                    . "The starter and battery are working. The combustion cycle is missing one of three fundamentals:\n\n"
                    . "1. **Fuel Delivery:** Turn ignition key to ON (without cranking) and listen near rear seats for a 2-second electric hum from the in-tank fuel pump. Check fuel pump fuse/relay.\n"
                    . "2. **Ignition Spark:** A failed Crankshaft Position Sensor (CKP) stops the ECU from sending spark and injector pulses. Tachometer needle staying at zero during cranking is a classic CKP symptom.\n"
                    . "3. **Air & Vacuum:** Inspect intake hose for splits downstream of the Mass Air Flow (MAF) sensor.\n"
                    . "4. **Anti-Theft / Immobilizer:** Check if a key icon is blinking rapidly on your instrument cluster.";
            }

            return "🔑 **Engine No-Start Diagnostics:**\n\n"
                . "To pinpoint why your vehicle will not start, identify the exact cranking behavior:\n\n"
                . "• **No Sound / Silence:** Dead battery (<9V), blown main ignition fuse, faulty neutral safety switch (try shifting to Neutral and starting), or bad ignition switch.\n"
                . "• **Clicking (Single or Rapid):** Weak battery, corroded terminals, or failing starter solenoid.\n"
                . "• **Cranks Slowly (Grr... Grr...):** Low Cold Cranking Amps (aged battery), loose ground cable, or thick oil in sub-zero weather.\n"
                . "• **Cranks Fast but Won't Fire:** Fuel pump failure, blown EFI fuse, or failed crankshaft position sensor (CKP).";
        }

        // -------------------------------------------------------------
        // 5. Smoke Colors & Exhaust Emissions
        // -------------------------------------------------------------
        if (preg_match('/\b(blue|bluish)\s+smoke\b/i', $q) || preg_match('/\bsmoke.*oil\b/i', $q)) {
            return "💨 **Blue Exhaust Smoke (Oil Burning):**\n\n"
                . "Blue or gray-blue smoke indicates motor oil is entering and burning inside the combustion chambers:\n\n"
                . "• **Smoke on Cold Start / After Idling:** Worn **valve stem seals**. When engine sits, oil seeps past dried rubber seals down the valve guides onto the piston crown.\n"
                . "• **Smoke Under Hard Acceleration / High RPM:** Worn **piston oil rings** or cylinder wall scoring (blow-by).\n"
                . "• **Clogged PCV Valve:** A stuck-shut PCV valve pressurizes the crankcase, forcing oil mist straight through the intake manifold into the cylinders.\n"
                . "• **Action:** Check dipstick weekly to avoid oil starvation, replace the PCV valve ($15 DIY), and perform a wet/dry cylinder compression test.";
        }

        if (preg_match('/\b(white|whitish)\s+smoke\b/i', $q) || preg_match('/\b(coolant|sweet)\s+smell.*exhaust\b/i', $q)) {
            return "💨 **Thick White Exhaust Smoke (Coolant Leak):**\n\n"
                . "• **Distinction:** Light white vapor that disappears within 2–3 feet on cold mornings is normal moisture condensation burning off.\n"
                . "• **Hazard (Sweet Thick White Billowing Smoke):** Engine coolant/antifreeze is leaking into the combustion chamber. This is caused by a **blown cylinder head gasket**, cracked cylinder head, or cracked engine block.\n"
                . "• **Symptoms to Check:**\n"
                . "  1. Sweet maple syrup odor from tailpipe.\n"
                . "  2. Bubbling in coolant overflow reservoir while engine runs.\n"
                . "  3. Milky 'chocolate milkshake' froth under oil filler cap or on dipstick (oil + coolant emulsification).\n"
                . "• **Urgency:** **Do not drive.** Coolant washes lubricating oil off cylinder walls, destroying rod bearings within miles.";
        }

        if (preg_match('/\b(black)\s+smoke\b/i', $q)) {
            return "💨 **Black Exhaust Smoke (Running Rich / Excess Fuel):**\n\n"
                . "Black smoke is unburnt fuel / soot caused by an excessively rich Air-Fuel Ratio (AFR):\n\n"
                . "• **Stuck Open Fuel Injector:** Injector is leaking raw gasoline directly into the cylinder.\n"
                . "• **Contaminated MAF Sensor:** Dirty hot-wire measures false high airflow, commanding the ECU to over-inject fuel.\n"
                . "• **Clogged Engine Air Filter:** Restricts incoming air, choking the intake charge.\n"
                . "• **Failing Fuel Pressure Regulator:** Internal diaphragm rupture dumping high-pressure fuel into the intake vacuum line.\n"
                . "• **Remedy:** Scan OBD-II fuel trim data (STFT/LTFT), clean MAF sensor with dedicated MAF spray, and inspect spark plugs for black dry carbon fouling.";
        }

        // -------------------------------------------------------------
        // 6. Engine Acoustics & Vibration Diagnostics (HAVEN Core Domain)
        // -------------------------------------------------------------
        // Knock vs Tick comparison
        if (preg_match('/\b(knock.*tick|tick.*knock|difference between.*tick|lifter vs rod)\b/i', $q)) {
            return "⚡ **Rod Knock vs. Lifter Tick (Diagnostic Comparison):**\n\n"
                . "| Characteristic | Lifter Tick (Valvetrain) | Rod Knock (Crankshaft Bearing) |\n"
                . "| :--- | :--- | :--- |\n"
                . "| **Sound Pitch** | High-pitched metallic clicking | Deep, hollow mechanical thud |\n"
                . "| **Frequency** | **1/2 Engine RPM** (Camshaft speed) | **1x Engine RPM** (Crankshaft speed) |\n"
                . "| **Behavior Under Load** | Quieter or unchanged under throttle | **Dramatically louder** under acceleration |\n"
                . "| **Cold vs Warm** | Often louder cold; softens when warm | Louder as engine warms and oil thins |\n"
                . "| **HAVEN Peak G** | < 2.0g Peak | **> 3.5g – 6.0g Peak shockwave** |\n"
                . "| **Urgency** | Inspect valve clearances / oil viscosity | **Immediate engine shutdown** to save crank |\n\n"
                . "Record 10 seconds of idle sound in HAVEN Diagnostics for instant automated classification.";
        }

        // Rod Knock / Engine Knock general
        if (preg_match('/\b(rod knock|crankshaft knock|spun bearing|main bearing knock)\b/i', $q) ||
            (str_contains($q, 'knock') && (str_contains($q, 'engine') || str_contains($q, 'loud') || str_contains($q, 'load') || str_contains($q, 'accelerat')))) {
            return "💥 **Engine Rod Knock (Critical Mechanical Hazard):**\n\n"
                . "Rod knock occurs when a connecting rod bearing wears thin or spins, creating excessive clearance between the rod journal and crankshaft. The piston and rod literally hammer against the crank journal at **1x engine RPM**.\n\n"
                . "• **Acoustic Signature:** Deep, dull metallic clunk that synchronizes with engine speed and grows violently louder under throttle.\n"
                . "• **HAVEN Telemetry:** High-frequency impact spikes with **Peak G > 3.5g** and Crest Factor > 4.5.\n"
                . "• **What To Do:** **Shut off engine immediately.** Do not drive. Continued running will throw a connecting rod through the engine block, destroying the entire engine.";
        }

        // Lifter Tick / Valve Lash
        if (preg_match('/\b(lifter tick|ticking sound|ticking noise|valve tick|tappet)\b/i', $q)) {
            return "🔧 **Lifter Tick / Valvetrain Ticking:**\n\n"
                . "A fast, metallic clicking or sewing-machine clatter coming from the top valve cover:\n\n"
                . "• **Causes:**\n"
                . "  1. Hydraulic lifter bleed-down (trapped air or varnished internal check valve failing to pump up).\n"
                . "  2. Low engine oil level or degraded oil viscosity failing to pressurize upper cylinder head.\n"
                . "  3. Exhaust manifold leak (a cracked cast manifold or blown graphite gasket sounds identical to a metallic tick and fades as metal heats up).\n"
                . "• **Next Steps:** Check engine oil level on dipstick. If oil is low or past 8,000 km, change oil and filter. Use an automotive stethoscope on the valve cover to isolate the specific cylinder lifter.";
        }

        // Belt Squeal / Screech
        if (preg_match('/\b(squeal|squealing|screech|chirping|serpentine belt|fan belt)\b/i', $q)) {
            return "🔊 **Belt Squeal & Pulley Diagnostics:**\n\n"
                . "A high-pitched screeching or chirping sound from the front of the engine:\n\n"
                . "• **Squeals on Cold Start or Heavy Steering:** Glazed or slipping serpentine belt. Power steering pump creates high hydraulic load at full steering lock.\n"
                . "• **Squeals when A/C is turned ON:** Slipping belt under A/C compressor load, or failing compressor clutch bearing.\n"
                . "• **Continuous Rhythmic Chirp:** Misaligned pulley, worn automatic belt tensioner spring, or seized idler pulley bearing.\n"
                . "• **Quick Diagnostic Test:** Spray a mist of clean water onto the ribbed side of the belt while running. If the noise disappears for 5 seconds and returns, the belt is glazed/loose. If noise doesn't change, an idler or alternator pulley bearing is failing.";
        }

        // Timing Chain Rattle on Cold Start
        if (preg_match('/\b(rattle|clatter).*(cold start|morning|chain|vvt|cam phaser)\b/i', $q)) {
            return "⚙️ **Cold Start Rattle (Timing Chain / VVT Cam Phasers):**\n\n"
                . "A harsh 2-to-5 second rattling clatter immediately upon cold engine start that disappears once oil pressure builds:\n\n"
                . "• **Root Cause:** Modern engines use hydraulic timing chain tensioners and Variable Valve Timing (VVT) cam gears that rely on oil pressure. Over time, internal tensioner ratchets wear or oil drain-back valves fail, allowing the timing chain to whip against plastic guide rails until oil reaches the top end.\n"
                . "• **Risk:** Slack timing chains can skip teeth on the cam sprockets, causing valve-to-piston collision on interference engines.\n"
                . "• **Check:** Inspect timing chain slack through oil filler cap with a wooden dowel (engine OFF); inspect oil filter for plastic guide debris.";
        }

        // Wheel Bearing Humming vs Tire Noise
        if (preg_match('/\b(hum|humming|roaring|growl).*(speed|highway|wheel|tire)\b/i', $q)) {
            return "🚗 **Wheel Bearing Humming vs. Cupped Tires:**\n\n"
                . "A deep droning or roaring sound that increases in pitch as vehicle road speed climbs:\n\n"
                . "• **Wheel Bearing Test (Sway Test):** Safely veer gently left and right at 35–45 mph. If veering right loads the left side and makes the roar louder, the **left wheel bearing** is failing.\n"
                . "• **Cupped / Feathered Tires:** Run your bare hand clockwise and counter-clockwise along the tire tread blocks. If edges feel like saw teeth, suspension alignment or worn shocks caused cupping, producing bearing-like tire roar.\n"
                . "• **Safety:** A failed wheel bearing can overheat and seize, or allow the wheel assembly to separate from the hub.";
        }

        // CV Joint Clicking
        if (preg_match('/\b(click|clicking|popping).*(turn|turning|corner|sharp turn|u-turn)\b/i', $q)) {
            return "🔄 **Clicking Sound When Turning (Worn CV Axle Joint):**\n\n"
                . "A rhythmic, metallic *click-click-click* heard only when making sharp low-speed turns or U-turns:\n\n"
                . "• **Cause:** The outer Constant Velocity (CV) joint on front-wheel-drive or AWD vehicles is worn. Usually triggered when the flexible rubber CV boot tears, slinging out lubricating grease and allowing road water and sand inside.\n"
                . "• **Inspection:** Turn wheels fully to lock and look behind the front wheel. If you see grease splattered all over the brake caliper and wheel well, the boot is torn and the half-shaft axle assembly must be replaced.";
        }

        // -------------------------------------------------------------
        // 7. Brakes, Steering & Suspension
        // -------------------------------------------------------------
        // Spongy Brake Pedal
        if (preg_match('/\b(spongy|soft|sinking|mushy|pedal to the floor)\s*(brake|pedal)?\b/i', $q) ||
            preg_match('/\bbrake.*(soft|spongy|sink|floor)\b/i', $q)) {
            return "🛑 **Spongy / Sinking Brake Pedal (Critical Safety Issue):**\n\n"
                . "If the brake pedal feels mushy or sinks slowly toward the floorboard while waiting at a red light:\n\n"
                . "• **Air in Brake Hydraulic Lines:** Air compresses under foot pressure whereas hydraulic fluid does not. Requires a 4-wheel brake fluid bleed.\n"
                . "• **Failing Brake Master Cylinder:** Internal rubber piston cups degrade, allowing high-pressure fluid to bypass internally without an external puddle.\n"
                . "• **Moisture Contamination (Boiling):** Glycol brake fluid absorbs atmospheric moisture over 2–3 years. Under heavy braking, water turns to steam bubbles (vapor lock).\n"
                . "• **Immediate Check:** Check brake fluid reservoir under hood. If fluid is low, inspect all 4 wheel calipers and rubber flexible brake lines for wet leaks.";
        }

        // Brake Squeal / Grinding
        if (preg_match('/\b(brake|brakes|pad|rotor)\s*(squeal|grind|noise|scraping)\b/i', $q) ||
            preg_match('/\b(grinding|squeaking)\s*(when braking|brakes)\b/i', $q)) {
            return "🛑 **Brake Noise Diagnostics (Squeal vs. Grind):**\n\n"
                . "• **High-Pitched Squeal:** Built-in acoustic wear indicator tabs (steel feeler gauges) are contacting the brake rotor to warn you that pad friction material has worn down to **< 2mm-3mm**. Time for replacement.\n"
                . "• **Harsh Metallic Grinding:** **Friction material is completely gone.** The steel brake pad backing plate is grinding bare against the steel rotor. Continuing to drive will destroy the rotors, overheat calipers, and drastically increase stopping distance.\n"
                . "• **Action:** Measure pad thickness and inspect rotors for deep radial scoring or heat discoloration.";
        }

        // Steering Shake / Vibration at Highway Speed
        if (preg_match('/\b(shake|shaking|vibrat|wobble).*(steering|wheel|highway|speed|60\s*mph|70\s*mph|100\s*km)\b/i', $q) ||
            preg_match('/\bsteering\s*(shakes|vibrates)\b/i', $q)) {
            return "🛞 **Steering Wheel Vibration Diagnostics:**\n\n"
                . "Identify precisely when the vibration occurs:\n\n"
                . "1. **Vibrates at 55–75 mph (Cruising):**\n"
                . "   • **Front Wheel Unbalance:** Thrown clip-on or stick-on wheel weight (rebalance front wheels).\n"
                . "   • **Separated Tire Belt:** Internal steel belt shifted inside tire casing (causes low-speed waddling too).\n"
                . "   • **Worn Tie Rod Ends:** Play in inner/outer tie rods translates road harmonics straight to steering rack.\n\n"
                . "2. **Vibrates ONLY When Applying Brakes:**\n"
                . "   • **Warped Front Brake Rotors:** Lateral runout (rotor thickness variation) pulses the brake pads and sends violent vibration through the brake caliper back up to the steering wheel.";
        }

        // Clunking Over Bumps
        if (preg_match('/\b(clunk|thump|rattle|knock).*(bump|rough road|pothole|uneven road)\b/i', $q)) {
            return "🔩 **Clunking Sound Over Bumps (Suspension Diagnosis):**\n\n"
                . "When going over speed bumps or rough asphalt:\n\n"
                . "• **Sway Bar End Links (Most Common):** Small ball-and-socket links lose grease and develop free play, producing a rapid hollow clunking over small washboard bumps ($20–$40 part).\n"
                . "• **Lower Ball Joints:** Critical safety component. A worn ball joint will clunk and risks wheel detachment if it snaps.\n"
                . "• **Control Arm Bushings:** Cracked or torn rubber bushings allow the suspension arm to slam metal-to-metal against the subframe.\n"
                . "• **Strut Top Mounts:** Worn upper thrust bearings clunk when turning steering wheel while stationary or rolling over curbs.";
        }

        // -------------------------------------------------------------
        // 8. Fluids & Maintenance Procedures
        // -------------------------------------------------------------
        // How to check engine oil
        if (preg_match('/\b(how.*check.*oil|check.*engine oil|read.*dipstick|inspect oil level)\b/i', $q)) {
            return "🛢️ **How to Check Your Engine Oil Properly (Step-by-Step):**\n\n"
                . "1. **Park on Level Ground:** An incline gives a false high or low dipstick reading.\n"
                . "2. **Warm then Rest Engine:** Warm engine to normal operating temperature, shut it OFF, and wait **5 to 10 minutes** to allow hot oil to drain down from the cylinder head into the oil pan.\n"
                . "3. **Pull and Wipe:** Pull the brightly colored dipstick out and wipe it completely clean with a lint-free cloth or paper towel.\n"
                . "4. **Re-insert Fully:** Push the dipstick all the way down until the handle rubber seal seats firmly.\n"
                . "5. **Read the Film:** Pull it out and inspect the wet oil level between the two hash marks (MIN / MAX or L / F). The gap between MIN and MAX is exactly **1.0 quart (approx 1 liter)**.\n"
                . "• **Oil Condition:** Amber/honey is fresh; dark brown/black is normal for detergents; milky froth indicates coolant contamination.";
        }

        // Oil Change Intervals
        if (preg_match('/\b(how often.*oil|oil change interval|when.*change.*oil|how many miles.*oil)\b/i', $q)) {
            return "⏱️ **Engine Oil Change Intervals (Factory Guidelines):**\n\n"
                . "• **Full Synthetic Oil:** Every **7,500 to 10,000 miles** (12,000–16,000 km) or **12 months**, whichever comes first.\n"
                . "• **Semi-Synthetic / Synthetic Blend:** Every **5,000 to 7,500 miles** (8,000–12,000 km).\n"
                . "• **Conventional Mineral Oil:** Every **3,000 to 5,000 miles** (5,000–8,000 km).\n"
                . "• **Severe Duty Exception:** If you frequently drive in stop-and-go city traffic, short trips under 5 miles, dusty environments, or tow heavy loads, cut the interval down to **5,000 miles (8,000 km)** regardless of oil type.";
        }

        // How to check transmission fluid (ATF)
        if (preg_match('/\b(transmission fluid|check.*atf|gearbox oil)\b/i', $q)) {
            return "⚙️ **How to Check Automatic Transmission Fluid (ATF):**\n\n"
                . "• **Key Difference from Motor Oil:** Most automatic transmissions must be checked with the **engine running and idling** at full operating temperature (160°F–180°F) in **Park (or Neutral on Hondas/Chryslers)** on level ground.\n"
                . "• **Steps:** Pull dipstick, wipe clean, re-insert fully for 3 seconds, pull out and check against the 'HOT' zone.\n"
                . "• **Color Guide:**\n"
                . "  - *Bright translucent pink/red:* Healthy fluid.\n"
                . "  - *Dark red / brownish:* Normal service wear (schedule a fluid drain & fill).\n"
                . "  - *Black with acrid burnt smell:* Burned clutch friction plates; internal transmission wear.";
        }

        // Spark Plug Maintenance
        if (preg_match('/\b(spark plug|spark plugs|gap|gapping|change plugs)\b/i', $q)) {
            return "⚡ **Spark Plug Inspection & Gapping Guide:**\n\n"
                . "• **Lifespan:**\n"
                . "  - *Copper Plugs:* 30,000 miles (50,000 km).\n"
                . "  - *Platinum Plugs:* 60,000 to 100,000 miles.\n"
                . "  - *Iridium / Ruthenium:* 100,000+ miles (160,000 km).\n"
                . "• **Spark Plug Gapping:** Always verify factory gap spec (typically **0.028\" – 0.044\"** / 0.7mm – 1.1mm) with a wire feeler gauge. **Caution:** Never pry against fine-wire iridium center electrodes with coin-style tools, as the brittle tip will snap.\n"
                . "• **Torque Spec:** Torque plugs into aluminum cylinder heads to **15–20 lb-ft (20–27 Nm)**; do not overtighten or strip head threads.";
        }

        // -------------------------------------------------------------
        // 9. Overheating & Cooling System (Precise context!)
        // -------------------------------------------------------------
        if (preg_match('/\b(overheat|overheating|coolant boiling|temp gauge.*hot|temperature.*red|steam from engine)\b/i', $q)) {
            return "🛑 **Engine Overheating Alert (Critical Thermal Emergency):**\n\n"
                . "Driving an overheating engine will warp aluminum cylinder heads, crack engine blocks, and blow head gaskets ($2,000+ repair) in under 2 minutes.\n\n"
                . "• **Immediate Action:**\n"
                . "  1. Pull over safely and **turn engine OFF immediately**.\n"
                . "  2. **NEVER open the radiator pressure cap while hot!** Scalding 230°F pressurized coolant will erupt causing severe burns. Wait at least 30–45 minutes.\n"
                . "• **Common Causes:**\n"
                . "  - Stuck-closed thermostat (upper radiator hose scalding hot, lower hose cool).\n"
                . "  - Failed electric cooling fan or fan relay (overheats at stoplights, cools down at highway speeds).\n"
                . "  - External coolant leak from cracked radiator plastic end-tanks, split radiator hose, or weeping water pump.\n"
                . "  - Blown head gasket pushing combustion gas into water jackets.";
        }

        // -------------------------------------------------------------
        // 10. Cylinder Misfires & Flashing Check Engine Light
        // -------------------------------------------------------------
        if (preg_match('/\b(misfire|misfiring|cylinder.*misfire|flashing check engine|cel flashing)\b/i', $q)) {
            return "⚡ **Cylinder Misfire & Flashing CEL Diagnostics:**\n\n"
                . "• **Warning (Flashing Check Engine Light):** If the CEL is **blinking/flashing**, a severe misfire is occurring right now. Raw unburnt fuel is being pumped directly into the exhaust pipe where it ignites inside the catalytic converter, melting the ceramic core ($1,200+ damage). Ease off throttle immediately and pull over.\n"
                . "• **Common Causes:**\n"
                . "  1. Defective Ignition Coil Pack (COP) with internal insulation arc-through.\n"
                . "  2. Heavily fouled or worn spark plug.\n"
                . "  3. Clogged or open-circuit fuel injector.\n"
                . "• **The 10-Minute Coil Swap Test:** If OBD-II scanner reads **P0301** (Cyl 1 misfire), swap Coil #1 with Coil #2. If the code moves to **P0302**, the ignition coil is 100% defective!";
        }

        // -------------------------------------------------------------
        // 11. Rough Idling & Vibration at Stoplights
        // -------------------------------------------------------------
        if (preg_match('/\b(rough idle|idling rough|shakes at stoplight|idle surge|engine vibrates at idle)\b/i', $q)) {
            return "🔄 **Rough Idle & Engine Shaking at Stop:**\n\n"
                . "When the engine runs smoothly while driving but shudders and shakes when stopped in Drive:\n\n"
                . "1. **Dirty Throttle Body & Carbon Deposits:** Carbon soot builds up around the throttle butterfly valve, restricting tiny idle airflow passages. Clean with aerosol throttle body cleaner and a microfiber towel.\n"
                . "2. **Intake Vacuum Leak:** Torn PCV hose, unmetered air leaking past intake manifold gaskets, or split rubber intake bellows leaning out the mixture at idle.\n"
                . "3. **Collapsed Motor Mounts:** Rubber or hydraulic liquid-filled engine mounts compress over time, transferring normal engine harmonics directly to the vehicle chassis and steering wheel.\n"
                . "4. **Dirty MAF Sensor:** Clean sensor filament with dedicated MAF cleaner spray.";
        }

        // -------------------------------------------------------------
        // 12. Transmission & Drivetrain Symptoms
        // -------------------------------------------------------------
        if (preg_match('/\b(transmission.*slip|slipping gear|rpm.*revs.*car doesn\'?t move|gear slipping)\b/i', $q)) {
            return "⚙️ **Transmission Slipping Diagnostics:**\n\n"
                . "Engine revs climb high but the car accelerates slowly or hesitates to engage:\n\n"
                . "• **Low Automatic Transmission Fluid (ATF):** The #1 cause. Hydraulic pressure drops, preventing clutch packs from clamping down firmly.\n"
                . "• **Worn Internal Clutch Friction Discs:** Burnt friction material can no longer hold engine torque under load.\n"
                . "• **Torque Converter Shudder:** Failing torque converter lockup clutch causing rumblestrip-like vibration at 35–50 mph.\n"
                . "• **Check:** Pull ATF dipstick while engine idles hot; inspect fluid level and smell for acrid burnt odors.";
        }

        // -------------------------------------------------------------
        // 13. Odors & Unexplained Smells
        // -------------------------------------------------------------
        if (preg_match('/\b(rotten egg|sulfur|sulphur)\s*smell\b/i', $q)) {
            return "👃 **Rotten Egg / Sulfur Smell (Catalytic Converter):**\n\n"
                . "Gasoline contains trace sulfur compounds (hydrogen sulfide). Under normal conditions, the catalytic converter oxidizes them into odorless sulfur dioxide.\n\n"
                . "• **Cause:** A rich fuel misfire or failing catalytic converter overloads the catalyst, causing hydrogen sulfide gas to pour out of the tailpipe.\n"
                . "• **Check:** Scan for misfire codes (P0300) and oxygen sensor codes (P0130–P0160). An overcharging car battery can also boil off sulfuric acid, emitting a sulfur smell under the hood.";
        }

        if (preg_match('/\b(sweet smell|syrup smell|pancake smell)\b/i', $q)) {
            return "👃 **Sweet Maple Syrup Smell (Coolant Leak):**\n\n"
                . "Ethylene glycol engine coolant has a distinctive sweet, syrupy smell when hot:\n\n"
                . "• **Smelled Inside Cabin with Foggy Windshield:** Leaking **heater core** behind the dashboard or leaking heater core inlet hoses.\n"
                . "• **Smelled Outside Engine Bay:** Radiator pinhole leak, water pump weep hole seepage, or coolant dripping onto hot exhaust pipes.";
        }

        if (preg_match('/\b(burning oil smell|smell.*burning oil)\b/i', $q)) {
            return "👃 **Burning Oil Smell Diagnostics:**\n\n"
                . "Motor oil is leaking externally and dripping directly onto hot exhaust components (exhaust manifold, catalytic converter, or downpipe):\n\n"
                . "• **Valve Cover Gasket:** Leaks down the backside of the cylinder head onto the exhaust manifold.\n"
                . "• **Oil Filter Housing / Cooler Gaskets:** Common failure point on modern European and domestic engines.\n"
                . "• **Oil Pan Gasket / Front Crank Seal:** Blows oil mist back onto hot exhaust pipes at highway speeds.";
        }

        if (preg_match('/\b(gasoline smell|fuel smell|gas smell|smell of gas)\b/i', $q)) {
            return "⛽ **Gasoline Smell Diagnostics:**\n\n"
                . "A raw gasoline smell represents a flammability hazard. Common failure points include:\n\n"
                . "• **Smell After Filling Up at Gas Station:** Saturated EVAP charcoal canister (usually caused by 'topping off' after the fuel nozzle clicks off), or a rusted fuel filler neck pipe.\n"
                . "• **Smell Inside the Cabin:** Fuel pump access port seal under the rear seat cushion has dried, cracked, or loosened.\n"
                . "• **Smell in Engine Bay (Under Hood):** Leaking fuel injector O-ring, loose high-pressure fuel rail fitting, or leaking fuel pulsation damper.\n"
                . "• **Safety:** Do not use open flames. Inspect fuel lines and undercarriage for wet stains immediately.";
        }

        // -------------------------------------------------------------
        // 14. Climate Control / Air Conditioning (HVAC)
        // -------------------------------------------------------------
        if (preg_match('/\b(a\/?c|air condition(ing)?|heater|climate control)\b/i', $q) &&
            preg_match('/\b(warm|hot|not cold|not cooling|blows? warm|driver side|passenger side|leak|freon|refrigerant)\b/i', $q)) {
            return "❄️ **Automotive Air Conditioning (A/C) Diagnostics:**\n\n"
                . "• **Warm on Driver Side, Cold on Passenger Side:**\n"
                . "  1. *Low R134a/R1234yf Refrigerant:* On many modern dual-core evaporators, low refrigerant first starves the driver-side passes while the remaining cold liquid pools on the passenger side.\n"
                . "  2. *Failed HVAC Blend Door Actuator:* The small electric servo motor that rotates the temperature flap behind the driver dash is stripped or binding.\n"
                . "• **Blows Warm Air on All Vents:**\n"
                . "  1. A/C compressor clutch not engaging (check A/C relay, 10A fuse, or low-pressure switch cutoff).\n"
                . "  2. Refrigerant leak from condenser (rock puncture) or compressor shaft seal.\n"
                . "• **Musty / Vinegar Smell:** Mold/mildew on moist evaporator fins; clean with foam evaporator cleaner and replace cabin air filter.";
        }

        // -------------------------------------------------------------
        // 14. Battery, Alternator & Electrical
        // -------------------------------------------------------------
        if (preg_match('/\b(battery light|alternator light|charging system|battery dying)\b/i', $q)) {
            return "🔋 **Battery / Alternator Light Diagnostics:**\n\n"
                . "The battery dashboard icon does NOT mean your battery is dead — it indicates that the **charging system voltage is too low** and the car is running purely off battery reserve power:\n\n"
                . "• **Alternator Failure:** Worn internal carbon brushes, failed voltage regulator, or burned rectifier diodes.\n"
                . "• **Broken / Slipping Serpentine Belt:** Alternator pulley is no longer spinning at speed.\n"
                . "• **Multimeter Test:**\n"
                . "  - *Engine OFF:* Healthy battery rests at **12.6V – 12.8V**.\n"
                . "  - *Engine Running:* Alternator charging output should be **13.8V to 14.4V** across battery posts. If voltage stays under 12.5V with engine running, the alternator has failed.";
        }

        // -------------------------------------------------------------
        // 15. HAVEN Vibration & Telemetry Metrics
        // -------------------------------------------------------------
        if (preg_match('/\b(peak g|rms g|crest factor|vibration metric|fft|spectrogram)\b/i', $q)) {
            return "📈 **Vibration Telemetry Metrics in HAVEN:**\n\n"
                . "HAVEN computes three core mechanical vibration indicators from ESP32 accelerometer sensors:\n\n"
                . "• **Peak G (Maximum Acceleration Force):** The highest instantaneous shock spike in Earth gravities (g). Smooth engines measure **< 1.8g**. Mechanical knocks and pre-ignition spikes register **> 3.5g – 6.0g**.\n"
                . "• **RMS G (Root-Mean-Square Energy):** Continuous average vibrational energy over the full time window. High baseline RMS indicates persistent rotational unbalance, broken motor mounts, or bearing wear.\n"
                . "• **Crest Factor (Peak G / RMS G):** Quantifies 'spikiness'. A high Crest Factor (**> 4.5**) isolates transient violent events (like detonation pings) from continuous engine hum.\n"
                . "• **FFT (Fast Fourier Transform):** Converts raw time-domain sound and vibration into distinct frequency bins to separate crankshaft rotation from camshaft valvetrain harmonics.";
        }

        // -------------------------------------------------------------
        // 16. How to Run a Test in HAVEN (Explicit App Usage Only)
        // -------------------------------------------------------------
        if (preg_match('/\b(how.*(use haven|run a test in haven|start a test in haven|test in haven|record in haven|upload esp32)|how does haven work)\b/i', $q)) {
            return "🚀 **How to Run a Diagnostic in HAVEN:**\n\n"
                . "1. Click **Diagnostics** in the navigation bar and select **Start Diagnostic**.\n"
                . "2. Choose your vehicle from your garage (or add one with instant VIN decoding).\n"
                . "3. **Step 2 (Acoustic Capture):** Hold your device microphone 20–30 cm from the engine bay (hood open or closed) and click Record for 10 seconds at idle.\n"
                . "4. **Step 3 (Vibration Telemetry):** Connect your ESP32 MPU6050 accelerometer, capture live data, or upload your session JSON/CSV file.\n"
                . "5. Click **Run Analysis** to execute HAVEN's trained machine learning model for instant condition diagnosis, FFT frequency spectrum, and safety repair plan.";
        }

        // -------------------------------------------------------------
        // 17. Greetings & Copilot Personality (Refined Brand Prompt)
        // -------------------------------------------------------------
        if (preg_match('/^(hello|hi|hey|good morning|good evening|good afternoon|greetings)\b/i', $q) || preg_match('/^(hi|hello|hey)\b/i', $q)) {
            return "Welcome to the Haven Experience! How may I assist you today with vehicle diagnostics, acoustic & vibration telemetry, or powertrain troubleshooting?";
        }
        if (preg_match('/\b(who are you|what can you do)\b/i', $q)) {
            return "Welcome to Haven. Where craftsmanship meets automotive intelligence. I can help you analyze engine acoustics, interpret vibration telemetry, troubleshoot DTC fault codes, or connect with diagnostic specialists. How may I assist you today?";
        }

        // -------------------------------------------------------------
        // 18. Structured ASE Master Mechanic Protocol (Intelligent Fallback)
        // -------------------------------------------------------------
        $safeQuestion = e(self::sanitizePromptText($question));
        return "🧠 **HAVEN Master Technician Analysis:**\n\n"
            . "To diagnose *\"{$safeQuestion}\"*, automotive diagnostics evaluates four fundamental powertrain systems:\n\n"
            . "1. **Airflow & Metering:** Check for vacuum leaks, contaminated MAF/MAP sensor wires, or a gummed throttle body.\n"
            . "2. **Fuel Delivery:** Verify fuel pressure, filter condition, and injector spray pattern across cylinders.\n"
            . "3. **Ignition & Spark:** Inspect spark plug electrode wear/gap, ignition coil insulation, and timing sensor signals (CKP/CMP).\n"
            . "4. **Mechanical & Compression:** Check oil level/viscosity, valvetrain clearance, and cylinder compression integrity.\n\n"
            . "💡 *To help me give you an exact diagnosis:* What is the **year, make, and model** of your vehicle, and does the symptom occur at **cold idle, under hard acceleration, or while cruising**?";
    }

    /**
     * Comprehensive OBD-II Trouble Code Directory & Decoder.
     */
    public function lookupDtcCode(string $code): string
    {
        $dtcDatabase = [
            // Fuel & Air Metering (P01xx)
            'P0101' => ['Mass Air Flow (MAF) Circuit Range/Performance', 'Dirty MAF sensor wire, torn intake boot downstream of sensor, or vacuum leak.'],
            'P0102' => ['Mass Air Flow (MAF) Circuit Low Input', 'Inspect MAF wiring connector, ground circuit, or replace failed MAF sensor.'],
            'P0113' => ['Intake Air Temperature (IAT) Circuit High Input', 'Open circuit in IAT sensor or disconnected intake air sensor harness.'],
            'P0115' => ['Engine Coolant Temperature (ECT) Circuit Malfunction', 'Inspect ECT sensor wiring; faulty sensor causes improper cold-start fuel enrichment.'],
            'P0128' => ['Coolant Thermostat Stuck Open / Below Regulating Temperature', 'Thermostat is stuck open; engine fails to reach operating temperature, leading to high fuel consumption.'],
            'P0130' => ['O2 Sensor Circuit Malfunction (Bank 1 Sensor 1)', 'Inspect upstream oxygen sensor wiring harness, exhaust leaks near manifold, or replace sensor.'],
            'P0135' => ['O2 Sensor Heater Circuit Malfunction (Bank 1 Sensor 1)', 'Internal heater element failed; sensor takes too long to enter closed-loop operation.'],
            'P0171' => ['System Too Lean (Bank 1)', 'Commonly caused by dirty MAF sensor, torn intake rubber boot, cracked vacuum hose, or weak fuel pump.'],
            'P0172' => ['System Too Rich (Bank 1)', 'Excess fuel or insufficient air. Check for leaking fuel injector, high fuel pressure, or stuck purge valve.'],
            'P0174' => ['System Too Lean (Bank 2)', 'Vacuum leak affecting Bank 2 intake runner or dirty MAF sensor on V6/V8 engines.'],
            'P0175' => ['System Too Rich (Bank 2)', 'Leaking Bank 2 injector or lazy Bank 2 pre-cat O2 sensor.'],

            // Ignition & Misfires (P03xx)
            'P0300' => ['Random / Multiple Cylinder Misfire Detected', 'Check ignition coils, fouled spark plugs, low fuel pressure, or vacuum leaks leaning out fuel trim.'],
            'P0301' => ['Cylinder 1 Misfire Detected', 'Inspect cylinder 1 spark plug, ignition coil (perform coil-swap test), and injector pulse.'],
            'P0302' => ['Cylinder 2 Misfire Detected', 'Swap cylinder 2 ignition coil with cylinder 1 to verify whether misfire follows coil.'],
            'P0303' => ['Cylinder 3 Misfire Detected', 'Inspect cylinder 3 spark plug porcelain for carbon tracking, coil pack, and injector spray.'],
            'P0304' => ['Cylinder 4 Misfire Detected', 'Inspect cylinder 4 compression, coil pack primary resistance, and spark plug gap.'],
            'P0305' => ['Cylinder 5 Misfire Detected', 'Inspect cylinder 5 ignition coil, spark plug, and perform relative compression test.'],
            'P0306' => ['Cylinder 6 Misfire Detected', 'Check cylinder 6 coil pack and fuel injector harness.'],
            'P0325' => ['Knock Sensor 1 Circuit Malfunction (Bank 1)', 'Inspect knock sensor sub-harness under intake manifold or replace fatigued piezo knock sensor.'],
            'P0335' => ['Crankshaft Position Sensor (CKP) A Circuit Malfunction', 'Critical sensor. Causes intermittent engine stalling or crank-no-start. Inspect sensor tip for metal debris and check wiring harness.'],
            'P0340' => ['Camshaft Position Sensor (CMP) Circuit Malfunction', 'Check CMP sensor wiring, connector corrosion, or stretched timing chain causing phase misalignment.'],

            // Auxiliary Emissions (P04xx)
            'P0401' => ['Exhaust Gas Recirculation (EGR) Flow Insufficient Detected', 'Clean carbon buildup from EGR passages and check EGR vacuum modulator.'],
            'P0420' => ['Catalytic Converter System Efficiency Below Threshold (Bank 1)', 'Catalytic converter ceramic core is degraded, usually as collateral damage from unburnt fuel during prolonged cylinder misfires.'],
            'P0430' => ['Catalytic Converter System Efficiency Below Threshold (Bank 2)', 'Bank 2 catalytic converter efficiency has fallen below ECU threshold.'],
            'P0440' => ['Evaporative Emission (EVAP) Control System Malfunction', 'Check gas cap seal, EVAP charcoal canister lines, and purge control valve.'],
            'P0442' => ['EVAP Control System Small Leak Detected', 'Inspect for loose gas cap or micro-cracks in fuel vapor purge lines.'],
            'P0455' => ['EVAP Control System Gross Leak / No Flow Detected', 'Gas cap missing/untightened, stuck open EVAP vent solenoid, or cracked charcoal canister.'],

            // Speed & Idle Control (P05xx)
            'P0500' => ['Vehicle Speed Sensor (VSS) Malfunction', 'Inspect transmission output speed sensor wiring; affects speedometer and transmission shift points.'],
            'P0505' => ['Idle Air Control (IAC) System Malfunction', 'Clean carbon deposits from throttle plate and IAC valve stepper motor passages.'],
            'P0507' => ['Idle Air Control System RPM Higher Than Expected', 'Caused by intake vacuum leak or dirty throttle body holding butterfly plate slightly open.'],

            // Computer & Output (P06xx) & Transmission (P07xx - P08xx)
            'P0606' => ['Engine Control Module (ECM) / PCM Processor Fault', 'Check main ECU grounds, battery supply voltage, or reprogram/replace powertrain computer.'],
            'P0700' => ['Transmission Control System (TCM) Malfunction Request', 'General transmission trouble indicator. Scan transmission TCM directly to retrieve specific internal gear or solenoid codes.'],
            'P0730' => ['Incorrect Gear Ratio Detected', 'Transmission is slipping or shifting out of spec. Check ATF fluid level immediately and inspect clutch packs.'],
            'P0841' => ['Transmission Fluid Pressure Sensor/Switch Circuit Performance', 'Check ATF fluid level, valve body pressure switch, and fluid contamination.'],
        ];

        if (isset($dtcDatabase[$code])) {
            return "🔍 **OBD-II Fault Code {$code} — {$dtcDatabase[$code][0]}:**\n\n"
                . "• **Technical Assessment:** This powertrain trouble code was triggered by the ECU after detecting out-of-boundary sensor readings.\n"
                . "• **Root Causes & Next Steps:** {$dtcDatabase[$code][1]}\n\n"
                . "You can also view freeze-frame sensor telemetry by running a diagnostic test in the **Diagnostics** menu.";
        }

        // Generic SAE J2012 Decoder for any unlisted P0/P1/P2 code
        $prefix = substr($code, 0, 1);
        $digit2 = substr($code, 1, 1);
        $digit3 = substr($code, 2, 1);

        $systemName = match ($digit3) {
            '1', '2' => 'Fuel & Air Metering',
            '3' => 'Ignition System or Cylinder Misfire',
            '4' => 'Auxiliary Emissions Controls (EVAP / Catalytic / EGR)',
            '5' => 'Vehicle Speed, Idle Control, & Auxiliary Inputs',
            '6' => 'Computer & Output Circuits (ECU/PCM)',
            '7', '8' => 'Transmission & Drivetrain Controls',
            default => 'Powertrain Subsystem',
        };

        return "🔍 **OBD-II Code {$code} ({$systemName}):**\n\n"
            . "• **System Category:** This diagnostic trouble code corresponds to the vehicle's **{$systemName}**.\n"
            . "• **Recommended Procedure:**\n"
            . "  1. Connect a live OBD-II scanner to review **Freeze Frame Data** (recorded engine RPM, coolant temp, and fuel trim when code set).\n"
            . "  2. Inspect wiring harness and electrical connectors for related sensors.\n"
            . "  3. Clear code, complete one full drive cycle, and observe if pending DTC returns.";
    }
}
