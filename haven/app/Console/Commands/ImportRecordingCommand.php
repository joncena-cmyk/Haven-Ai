<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeDiagnosticJob;
use App\Models\Diagnostic;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportRecordingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'haven:import-recording
                            {folder : Path to the validated offline recording folder}
                            {--user-id= : User ID to associate with the imported diagnostic}
                            {--vehicle-id= : Override the vehicle ID from metadata.json}
                            {--verified-by= : Name of mechanic/supervisor verifying ground truth}
                            {--analyze : Also run ML inference on the imported diagnostic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import a validated offline recording folder (audio.wav + vibration.csv + metadata.json) into diagnostics with ground-truth label and is_training_sample=true';

    public function handle(): int
    {
        $folder = rtrim((string) $this->argument('folder'), '/\\');
        if (! is_dir($folder)) {
            $this->error("Recording folder does not exist: {$folder}");

            return self::FAILURE;
        }

        $metaPath = $folder.DIRECTORY_SEPARATOR.'metadata.json';
        $vibPath = $folder.DIRECTORY_SEPARATOR.'vibration.csv';
        $audioPath = $folder.DIRECTORY_SEPARATOR.'audio.wav';

        foreach (['metadata.json' => $metaPath, 'vibration.csv' => $vibPath, 'audio.wav' => $audioPath] as $name => $path) {
            if (! is_file($path)) {
                $this->error("Missing required file in recording folder: {$name}");

                return self::FAILURE;
            }
        }

        $meta = json_decode((string) file_get_contents($metaPath), true);
        if (! is_array($meta)) {
            $this->error('Failed to decode metadata.json.');

            return self::FAILURE;
        }

        $condition = strtolower(trim((string) ($meta['condition'] ?? '')));
        if (! in_array($condition, Diagnostic::TRAINING_CONDITIONS, true)) {
            $this->error(
                "Invalid ground-truth condition '{$condition}'. Must be one of: "
                .implode(', ', Diagnostic::TRAINING_CONDITIONS)
            );

            return self::FAILURE;
        }

        // Resolve vehicle
        $vehicleIdOption = $this->option('vehicle-id') ?? ($meta['vehicle_id'] ?? null);
        $vehicle = null;
        if (is_numeric($vehicleIdOption)) {
            $vehicle = Vehicle::find((int) $vehicleIdOption);
        }
        if (! $vehicle && ! empty($vehicleIdOption)) {
            $vehicle = Vehicle::where('plate_number', $vehicleIdOption)->first();
        }
        if (! $vehicle) {
            $this->error("Could not find Vehicle for identifier '{$vehicleIdOption}'. Pass --vehicle-id=<id>.");

            return self::FAILURE;
        }

        $userId = $this->option('user-id') ? (int) $this->option('user-id') : $vehicle->user_id;

        // Parse and validate vibration.csv
        [$sampleCount, $peakG, $rmsG, $achievedRateHz, $csvError] = $this->parseAndValidateVibrationCsv($vibPath);
        if ($csvError !== null) {
            $this->error("Vibration CSV validation failed: {$csvError}");

            return self::FAILURE;
        }

        // Copy audio.wav and vibration.csv into private local disk
        $uuid = Str::uuid()->toString();
        $storedAudioPath = "diagnostics/{$vehicle->id}/audio/{$uuid}.wav";
        $storedVibPath = "diagnostics/{$vehicle->id}/vibration/{$uuid}.csv";

        Storage::disk('local')->put($storedAudioPath, (string) file_get_contents($audioPath));
        Storage::disk('local')->put($storedVibPath, (string) file_get_contents($vibPath));

        $targetRpm = isset($meta['rpm_target']) && is_numeric($meta['rpm_target'])
            ? (int) $meta['rpm_target']
            : null;
        $recordingMode = ($targetRpm !== null && $targetRpm >= 1500) ? 'revving' : 'idle';
        $sampleRateHz = $achievedRateHz > 0
            ? $achievedRateHz
            : (isset($meta['vibration_achieved_rate_hz']) ? (int) round((float) $meta['vibration_achieved_rate_hz']) : null);

        $diagnostic = Diagnostic::create([
            'user_id' => $userId,
            'vehicle_id' => $vehicle->id,
            'audio_path' => $storedAudioPath,
            'audio_original_name' => 'audio.wav',
            'audio_duration_sec' => (int) round((float) ($meta['audio_duration_sec'] ?? $meta['duration_target_sec'] ?? 10)),
            'vibration_path' => $storedVibPath,
            'vibration_source' => 'adxl345_live',
            'vibration_sample_count' => $sampleCount,
            'vibration_peak_g' => $peakG,
            'vibration_rms_g' => $rmsG,
            'recording_mode' => $recordingMode,
            'notes' => $meta['notes'] ?? 'Imported via haven:import-recording',
            'status' => 'completed',
            'true_condition' => $condition,
            'session_id' => $meta['session_id'] ?? null,
            'fault_detail' => $meta['fault_detail'] ?? null,
            'target_rpm' => $targetRpm,
            'load_state' => $meta['load_state'] ?? null,
            'sensor_position' => $meta['sensor_position'] ?? null,
            'mic_position' => $meta['mic_position'] ?? null,
            'operator' => $meta['operator'] ?? null,
            'verified_by' => $this->option('verified-by') ?: ($meta['verified_by'] ?? null),
            'sample_rate_hz' => $sampleRateHz,
            'is_training_sample' => true,
        ]);

        if ($this->option('analyze')) {
            AnalyzeDiagnosticJob::dispatchSync($diagnostic);
            $diagnostic->refresh();
        }

        $this->info(
            "Imported Diagnostic #{$diagnostic->id} "
            ."[true_condition={$diagnostic->true_condition}, is_training_sample=true, "
            ."samples={$sampleCount}, rate={$sampleRateHz}Hz, peak_g={$peakG}, rms_g={$rmsG}]"
        );

        return self::SUCCESS;
    }

    /**
     * Parse 't_us,ax,ay,az' CSV, subtract per-axis DC gravity mean, and compute summary metrics.
     *
     * @return array{0:int, 1:float, 2:float, 3:int, 4:?string}
     */
    private function parseAndValidateVibrationCsv(string $vibPath): array
    {
        $lines = file($vibPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! $lines) {
            return [0, 0.0, 0.0, 0, 'Empty vibration.csv'];
        }

        $timestamps = [];
        $samples3d = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, 't_us')) {
                continue;
            }
            $cols = str_getcsv($trimmed);
            if (count($cols) < 4) {
                continue;
            }
            if (! is_numeric($cols[0]) || ! is_numeric($cols[1]) || ! is_numeric($cols[2]) || ! is_numeric($cols[3])) {
                return [0, 0.0, 0.0, 0, "Non-numeric row encountered: {$trimmed}"];
            }
            $t = (float) $cols[0];
            $ax = (float) $cols[1];
            $ay = (float) $cols[2];
            $az = (float) $cols[3];
            $timestamps[] = $t;
            $samples3d[] = [$ax, $ay, $az];
        }

        $n = count($samples3d);
        if ($n < 50) {
            return [$n, 0.0, 0.0, 0, "Too few vibration samples ({$n} < 50)"];
        }

        $dts = [];
        for ($i = 1; $i < $n; $i++) {
            $dt = $timestamps[$i] - $timestamps[$i - 1];
            if ($dt <= 0) {
                return [$n, 0.0, 0.0, 0, 'Non-monotonic timestamps in vibration.csv'];
            }
            $dts[] = $dt;
        }

        sort($dts);
        $medianDtUs = $dts[(int) floor(count($dts) / 2)];
        $achievedHz = $medianDtUs > 0 ? (int) round(1_000_000.0 / $medianDtUs) : 0;

        $meanX = array_sum(array_column($samples3d, 0)) / $n;
        $meanY = array_sum(array_column($samples3d, 1)) / $n;
        $meanZ = array_sum(array_column($samples3d, 2)) / $n;

        $peakG = 0.0;
        $sumSq = 0.0;
        foreach ($samples3d as [$ax, $ay, $az]) {
            $dx = $ax - $meanX;
            $dy = $ay - $meanY;
            $dz = $az - $meanZ;
            $mag = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
            if ($mag > $peakG) {
                $peakG = $mag;
            }
            $sumSq += $mag * $mag;
        }

        $rmsG = sqrt($sumSq / $n);

        return [$n, round($peakG, 4), round($rmsG, 4), $achievedHz, null];
    }
}
