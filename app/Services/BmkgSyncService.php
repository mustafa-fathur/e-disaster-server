<?php

namespace App\Services;

use App\Models\Disaster;
use App\Models\DisasterVolunteer;
use App\Models\User;
use App\Enums\DisasterTypeEnum;
use App\Enums\DisasterStatusEnum;
use App\Enums\DisasterSourceEnum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BmkgSyncService
{
    /**
     * Sync latest earthquake data from BMKG and store as disasters
     */
    public function syncLatestEarthquake(): array
    {
        try {
            $earthquakeData = $this->fetchLatestEarthquake();
            
            if (!$earthquakeData) {
                return [
                    'success' => false,
                    'message' => 'No earthquake data available from BMKG',
                    'data' => null
                ];
            }

            // Check if disaster already exists (by coordinates, magnitude, and date)
            $existingDisaster = $this->findExistingDisaster($earthquakeData);
            
            if ($existingDisaster) {
                return [
                    'success' => true,
                    'message' => 'Latest earthquake already exists in database',
                    'data' => $existingDisaster,
                    'skipped' => true
                ];
            }

            $disaster = $this->createDisasterFromBmkgData($earthquakeData);
            
            return [
                'success' => true,
                'message' => 'Latest earthquake synced successfully',
                'data' => $disaster,
                'created' => true
            ];

        } catch (\Exception $e) {
            Log::error('BMKG Sync Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to sync latest earthquake: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }


    /**
     * Fetch latest earthquake data from BMKG
     */
    private function fetchLatestEarthquake(): ?array
    {
        $response = Http::timeout(10)->get('https://data.bmkg.go.id/DataMKG/TEWS/autogempa.json');
        
        if (!$response->successful()) {
            throw new \Exception("BMKG API returned status: {$response->status()}");
        }

        $data = $response->json();
        
        if (!$data || !isset($data['Infogempa']['gempa'])) {
            return null;
        }

        return $this->formatBmkgData($data['Infogempa']['gempa']);
    }


    /**
     * Format BMKG data to standardized format
     */
    private function formatBmkgData(array $earthquake): array
    {
        $iso = $earthquake['DateTime'] ?? null; // ISO8601 UTC
        $coordinatesStr = $earthquake['Coordinates'] ?? null; // "-3.35,101.26"

        // Parse Lintang (lat) and Bujur (long) with Indonesian directions
        $lat = null;
        $long = null;
        if (!empty($earthquake['Lintang']) && preg_match('/([\d.]+)\s*(LU|LS)/i', $earthquake['Lintang'], $m)) {
            $v = (float) $m[1];
            $lat = strtoupper($m[2]) === 'LS' ? -$v : $v;
        }
        if (!empty($earthquake['Bujur']) && preg_match('/([\d.]+)\s*(BT|BB)/i', $earthquake['Bujur'], $m)) {
            $v = (float) $m[1];
            $long = strtoupper($m[2]) === 'BB' ? -$v : $v;
        }

        if (($lat === null || $long === null) && $coordinatesStr && strpos($coordinatesStr, ',') !== false) {
            [$latStr, $longStr] = array_map('trim', explode(',', $coordinatesStr));
            if ($lat === null) $lat = (float) $latStr;
            if ($long === null) $long = (float) $longStr;
        }

        // Magnitude and depth (strip units like "54 km")
        $magnitude = isset($earthquake['Magnitude']) ? (float) $earthquake['Magnitude'] : 0.0;
        $depth = 0.0;
        if (!empty($earthquake['Kedalaman']) && preg_match('/([\d.]+)/', $earthquake['Kedalaman'], $m)) {
            $depth = (float) $m[1];
        }

        $shakemapUrl = null;
        if (!empty($earthquake['Shakemap'])) {
            $shakemapUrl = 'https://data.bmkg.go.id/DataMKG/TEWS/' . ltrim($earthquake['Shakemap'], '/');
        }

        return [
            'datetime' => $earthquake['Tanggal'] ?? null,
            'datetime_utc' => $iso,
            'coordinate_string' => $coordinatesStr,
            'coordinates' => [
                'latitude' => $lat,
                'longitude' => $long,
            ],
            'magnitude' => $magnitude,
            'depth' => $depth,
            'region' => $earthquake['Wilayah'] ?? null,
            'tsunami_potential' => $earthquake['Potensi'] ?? null,
            'felt' => $earthquake['Dirasakan'] ?? null,
            'shakemap_url' => $shakemapUrl,
        ];
    }

    /**
     * Create disaster from BMKG earthquake data
     */
    private function createDisasterFromBmkgData(array $earthquakeData): ?Disaster
    {
        try {
            // Basic validation: require region, coordinates, magnitude, and a parsable date/time
            $region = $earthquakeData['region'] ?? null;
            $coords = $earthquakeData['coordinates'] ?? null;
            $latOk = is_array($coords) && isset($coords['latitude']) && is_numeric($coords['latitude']);
            $longOk = is_array($coords) && isset($coords['longitude']) && is_numeric($coords['longitude']);
            $magOk = isset($earthquakeData['magnitude']) && is_numeric($earthquakeData['magnitude']);
            if (!$region || !$latOk || !$longOk || !$magOk) {
                Log::warning('Skipping BMKG disaster creation due to incomplete data', [
                    'region' => $region,
                    'coordinates' => $coords,
                    'magnitude' => $earthquakeData['magnitude'] ?? null,
                ]);
                return null;
            }
            // Prefer ISO DateTime (UTC) and convert to Asia/Jakarta for date & time
            if (!empty($earthquakeData['datetime_utc'])) {
                $dt = Carbon::parse($earthquakeData['datetime_utc'])->setTimezone('Asia/Jakarta');
                $dateTime = [
                    'date' => $dt->format('Y-m-d'),
                    'time' => $dt->format('H:i:s'),
                ];
            } else {
                $dateTime = $this->parseBmkgDateTime($earthquakeData['datetime'] ?? '');
            }
            
            // Create disaster title and enforce DB column length (45)
            $title = sprintf(
                'Gempa Bumi M%.1f - %s',
                $earthquakeData['magnitude'],
                $earthquakeData['region'] ?? 'Lokasi Tidak Diketahui'
            );
            $title = \Illuminate\Support\Str::limit($title, 45, '');

            // Create disaster description
            $description = sprintf(
                "Gempa bumi dengan magnitudo %.1f terjadi di %s pada kedalaman %.1f km. %s",
                $earthquakeData['magnitude'],
                $earthquakeData['region'] ?? 'lokasi tidak diketahui',
                $earthquakeData['depth'],
                $earthquakeData['felt'] ? "Dirasakan: " . $earthquakeData['felt'] : ""
            );

            if ($earthquakeData['tsunami_potential']) {
                $description .= " Potensi Tsunami: " . $earthquakeData['tsunami_potential'];
            }

            $disaster = Disaster::create([
                'title' => $title,
                'description' => trim($description),
                'source' => DisasterSourceEnum::BMKG,
                'types' => DisasterTypeEnum::EARTHQUAKE,
                'status' => DisasterStatusEnum::ONGOING,
                'date' => $dateTime['date'],
                'time' => $dateTime['time'],
                // Enforce location length (45)
                'location' => \Illuminate\Support\Str::limit($region, 45, ''),
                'coordinate' => $earthquakeData['coordinate_string'] ?? sprintf(
                    '%s, %s',
                    $coords['latitude'],
                    $coords['longitude']
                ),
                'lat' => $coords['latitude'],
                'long' => $coords['longitude'],
                'magnitude' => $earthquakeData['magnitude'],
                'depth' => $earthquakeData['depth'],
                'reported_by' => null, // BMKG data, not reported by user
            ]);

            // Assign system admin as volunteer for BMKG disasters (if admin exists)
            $this->assignSystemAdminToDisaster($disaster);

            Log::info("Created disaster from BMKG data", [
                'disaster_id' => $disaster->id,
                'title' => $disaster->title,
                'magnitude' => $disaster->magnitude,
                'region' => $disaster->location,
                'coordinate' => $disaster->coordinate
            ]);

            return $disaster;

        } catch (\Exception $e) {
            Log::error("Failed to create disaster from BMKG data", [
                'error' => $e->getMessage(),
                'earthquake_data' => $earthquakeData
            ]);
            return null;
        }
    }

    /**
     * Find existing disaster based on BMKG data
     */
    private function findExistingDisaster(array $earthquakeData): ?Disaster
    {
        // Derive date/time the same way as creation to ensure consistent dedupe
        if (!empty($earthquakeData['datetime_utc'])) {
            $dt = Carbon::parse($earthquakeData['datetime_utc'])->setTimezone('Asia/Jakarta');
            $dateTime = [
                'date' => $dt->format('Y-m-d'),
                'time' => $dt->format('H:i:s'),
            ];
        } else {
            $dateTime = $this->parseBmkgDateTime($earthquakeData['datetime'] ?? '');
        }
        
        return Disaster::where('source', DisasterSourceEnum::BMKG)
            ->where('types', DisasterTypeEnum::EARTHQUAKE)
            ->where('lat', $earthquakeData['coordinates']['latitude'])
            ->where('long', $earthquakeData['coordinates']['longitude'])
            ->where('magnitude', $earthquakeData['magnitude'])
            ->where('date', $dateTime['date'])
            ->where('time', $dateTime['time'])
            ->first();
    }

    /**
     * Parse BMKG datetime format to separate date and time
     */
    private function parseBmkgDateTime(string $bmkgDateTime): array
    {
        try {
            // Support ISO or Indonesian month names (fallback)
            if (str_contains($bmkgDateTime, 'T')) {
                $dt = Carbon::parse($bmkgDateTime)->setTimezone('Asia/Jakarta');
                return [
                    'date' => $dt->format('Y-m-d'),
                    'time' => $dt->format('H:i:s')
                ];
            }

            // Try to convert Indonesian month abbreviations
            $map = ['Jan'=>'Jan','Feb'=>'Feb','Mar'=>'Mar','Apr'=>'Apr','Mei'=>'May','Jun'=>'Jun','Jul'=>'Jul','Agu'=>'Aug','Sep'=>'Sep','Okt'=>'Oct','Nov'=>'Nov','Des'=>'Dec'];
            $converted = preg_replace_callback('/\b(Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Sep|Okt|Nov|Des)\b/u', function($m) use ($map){return $map[$m[1]] ?? $m[1];}, $bmkgDateTime);
            $converted = str_replace([' WIB',' WITA',' WIT'], '', $converted);
            if (!str_contains($converted, ',')) {
                $converted .= ', 00:00:00';
            }
            $dateTime = Carbon::createFromFormat('d M Y, H:i:s', trim($converted), 'Asia/Jakarta');
            
            return [
                'date' => $dateTime->format('Y-m-d'),
                'time' => $dateTime->format('H:i:s')
            ];
        } catch (\Exception $e) {
            // Fallback to current date/time if parsing fails
            $now = now();
            return [
                'date' => $now->format('Y-m-d'),
                'time' => $now->format('H:i:s')
            ];
        }
    }

    /**
     * Assign system admin to BMKG disasters
     */
    private function assignSystemAdminToDisaster(Disaster $disaster): void
    {
        try {
            // Find first active admin user
            $admin = User::where('type', 'admin')
                ->where('status', 'active')
                ->first();

            if ($admin) {
                DisasterVolunteer::create([
                    'disaster_id' => $disaster->id,
                    'user_id' => $admin->id,
                ]);

                Log::info("Assigned admin to BMKG disaster", [
                    'disaster_id' => $disaster->id,
                    'admin_id' => $admin->id
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to assign admin to BMKG disaster", [
                'disaster_id' => $disaster->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
