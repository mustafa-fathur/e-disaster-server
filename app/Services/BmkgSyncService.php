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
     * Sync recent earthquakes (M 5.0+) from BMKG
     */
    public function syncRecentEarthquakes(): array
    {
        try {
            $earthquakesData = $this->fetchRecentEarthquakes();
            
            if (!$earthquakesData || empty($earthquakesData)) {
                return [
                    'success' => false,
                    'message' => 'No recent earthquake data available from BMKG',
                    'data' => []
                ];
            }

            $createdDisasters = [];
            $skippedCount = 0;

            foreach ($earthquakesData as $earthquakeData) {
                // Check if disaster already exists (by coordinates, magnitude, and date)
                $existingDisaster = $this->findExistingDisaster($earthquakeData);
                
                if ($existingDisaster) {
                    $skippedCount++;
                    continue;
                }

                $disaster = $this->createDisasterFromBmkgData($earthquakeData);
                if ($disaster) {
                    $createdDisasters[] = $disaster;
                }
            }

            return [
                'success' => true,
                'message' => sprintf(
                    'Synced %d recent earthquakes. %d already existed and were skipped.',
                    count($createdDisasters),
                    $skippedCount
                ),
                'data' => $createdDisasters,
                'stats' => [
                    'created' => count($createdDisasters),
                    'skipped' => $skippedCount,
                    'total_processed' => count($earthquakesData)
                ]
            ];

        } catch (\Exception $e) {
            Log::error('BMKG Recent Sync Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to sync recent earthquakes: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Sync felt earthquakes from BMKG
     */
    public function syncFeltEarthquakes(): array
    {
        try {
            $earthquakesData = $this->fetchFeltEarthquakes();
            
            if (!$earthquakesData || empty($earthquakesData)) {
                return [
                    'success' => false,
                    'message' => 'No felt earthquake data available from BMKG',
                    'data' => []
                ];
            }

            $createdDisasters = [];
            $skippedCount = 0;

            foreach ($earthquakesData as $earthquakeData) {
                // Check if disaster already exists
                $existingDisaster = $this->findExistingDisaster($earthquakeData);
                
                if ($existingDisaster) {
                    $skippedCount++;
                    continue;
                }

                $disaster = $this->createDisasterFromBmkgData($earthquakeData);
                if ($disaster) {
                    $createdDisasters[] = $disaster;
                }
            }

            return [
                'success' => true,
                'message' => sprintf(
                    'Synced %d felt earthquakes. %d already existed and were skipped.',
                    count($createdDisasters),
                    $skippedCount
                ),
                'data' => $createdDisasters,
                'stats' => [
                    'created' => count($createdDisasters),
                    'skipped' => $skippedCount,
                    'total_processed' => count($earthquakesData)
                ]
            ];

        } catch (\Exception $e) {
            Log::error('BMKG Felt Sync Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to sync felt earthquakes: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Sync all earthquake data from BMKG
     */
    public function syncAllEarthquakes(): array
    {
        $results = [
            'latest' => $this->syncLatestEarthquake(),
            'recent' => $this->syncRecentEarthquakes(),
            'felt' => $this->syncFeltEarthquakes()
        ];

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($results as $type => $result) {
            if ($result['success'] && isset($result['stats'])) {
                $totalCreated += $result['stats']['created'];
                $totalSkipped += $result['stats']['skipped'];
            }
        }

        return [
            'success' => true,
            'message' => sprintf(
                'Sync completed. Created %d new disasters, skipped %d existing ones.',
                $totalCreated,
                $totalSkipped
            ),
            'data' => $results,
            'summary' => [
                'total_created' => $totalCreated,
                'total_skipped' => $totalSkipped,
                'sync_types' => array_keys($results)
            ]
        ];
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
     * Fetch recent earthquakes from BMKG
     */
    private function fetchRecentEarthquakes(): array
    {
        $response = Http::timeout(10)->get('https://data.bmkg.go.id/DataMKG/TEWS/gempaterkini.json');
        
        if (!$response->successful()) {
            throw new \Exception("BMKG API returned status: {$response->status()}");
        }

        $data = $response->json();
        
        if (!$data || !isset($data['Infogempa']['gempa'])) {
            return [];
        }

        $gempaData = $data['Infogempa']['gempa'];
        
        // Handle single earthquake or array of earthquakes
        if (isset($gempaData['Tanggal'])) {
            $gempaData = [$gempaData];
        }

        return array_map([$this, 'formatBmkgData'], $gempaData);
    }

    /**
     * Fetch felt earthquakes from BMKG
     */
    private function fetchFeltEarthquakes(): array
    {
        $response = Http::timeout(10)->get('https://data.bmkg.go.id/DataMKG/TEWS/gempadirasakan.json');
        
        if (!$response->successful()) {
            throw new \Exception("BMKG API returned status: {$response->status()}");
        }

        $data = $response->json();
        
        if (!$data || !isset($data['Infogempa']['gempa'])) {
            return [];
        }

        $gempaData = $data['Infogempa']['gempa'];
        
        // Handle single earthquake or array of earthquakes
        if (isset($gempaData['Tanggal'])) {
            $gempaData = [$gempaData];
        }

        return array_map([$this, 'formatBmkgData'], $gempaData);
    }

    /**
     * Format BMKG data to standardized format
     */
    private function formatBmkgData(array $earthquake): array
    {
        return [
            'datetime' => $earthquake['Tanggal'] ?? null,
            'datetime_utc' => $earthquake['DateTime'] ?? null,
            'coordinates' => [
                'latitude' => floatval($earthquake['point']['coordinates'][1] ?? 0),
                'longitude' => floatval($earthquake['point']['coordinates'][0] ?? 0)
            ],
            'magnitude' => floatval($earthquake['Magnitude'] ?? 0),
            'depth' => floatval($earthquake['Kedalaman'] ?? 0),
            'region' => $earthquake['Wilayah'] ?? null,
            'tsunami_potential' => $earthquake['Potensi'] ?? null,
            'felt' => $earthquake['Dirasakan'] ?? null,
            'shakemap_url' => isset($earthquake['Shakemap']) ? 
                "https://static.bmkg.go.id/{$earthquake['Shakemap']}.jpg" : null
        ];
    }

    /**
     * Create disaster from BMKG earthquake data
     */
    private function createDisasterFromBmkgData(array $earthquakeData): ?Disaster
    {
        try {
            // Parse date and time from BMKG format
            $dateTime = $this->parseBmkgDateTime($earthquakeData['datetime']);
            
            // Create disaster title
            $title = sprintf(
                'Gempa Bumi M%.1f - %s',
                $earthquakeData['magnitude'],
                $earthquakeData['region'] ?? 'Lokasi Tidak Diketahui'
            );

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
                'location' => $earthquakeData['region'],
                'coordinate' => sprintf(
                    "%s, %s",
                    $earthquakeData['coordinates']['latitude'],
                    $earthquakeData['coordinates']['longitude']
                ),
                'lat' => $earthquakeData['coordinates']['latitude'],
                'long' => $earthquakeData['coordinates']['longitude'],
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
                'region' => $disaster->location
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
        $dateTime = $this->parseBmkgDateTime($earthquakeData['datetime']);
        
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
            // BMKG format: "21 Okt 2025, 15:57:01 WIB"
            $dateTime = Carbon::createFromFormat('d M Y, H:i:s T', $bmkgDateTime);
            
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
