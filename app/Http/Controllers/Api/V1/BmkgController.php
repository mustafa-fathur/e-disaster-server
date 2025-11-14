<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BmkgSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BmkgController extends Controller
{
    /**
     * @OA\Get(
     *     path="/bmkg/earthquakes",
     *     summary="Get latest earthquake data from BMKG",
     *     description="Fetch real-time earthquake data from BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Type of earthquake data",
     *         required=false,
     *         @OA\Schema(type="string", enum={"latest", "recent", "felt"}, default="latest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Earthquake data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Earthquake data retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to fetch earthquake data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to fetch earthquake data from BMKG"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getEarthquakeData(Request $request)
    {
        $type = $request->query('type', 'latest');
        
        try {
            $data = $this->fetchBmkgData($type);
            
            return response()->json([
                'success' => true,
                'message' => 'Earthquake data retrieved successfully',
                'data' => $data,
                'source' => 'BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)',
                'last_updated' => now()->format('Y-m-d H:i:s')
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('BMKG API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch earthquake data from BMKG',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/bmkg/earthquakes/latest",
     *     summary="Get latest single earthquake from BMKG",
     *     description="Fetch the most recent earthquake data from BMKG",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Latest earthquake data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Latest earthquake data retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getLatestEarthquake(Request $request)
    {
        try {
            $data = $this->fetchBmkgData('latest');
            
            return response()->json([
                'success' => true,
                'message' => 'Latest earthquake data retrieved successfully',
                'data' => $data,
                'source' => 'BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)',
                'last_updated' => now()->format('Y-m-d H:i:s')
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('BMKG Latest API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch latest earthquake data from BMKG',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/bmkg/earthquakes/recent",
     *     summary="Get recent earthquakes (M 5.0+) from BMKG",
     *     description="Fetch list of recent earthquakes with magnitude 5.0+ from BMKG",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Recent earthquakes data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recent earthquakes data retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getRecentEarthquakes(Request $request)
    {
        try {
            $data = $this->fetchBmkgData('recent');
            
            return response()->json([
                'success' => true,
                'message' => 'Recent earthquakes data retrieved successfully',
                'data' => $data,
                'source' => 'BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)',
                'last_updated' => now()->format('Y-m-d H:i:s')
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('BMKG Recent API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent earthquakes data from BMKG',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/bmkg/earthquakes/felt",
     *     summary="Get felt earthquakes from BMKG",
     *     description="Fetch list of earthquakes that were felt by people from BMKG",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Felt earthquakes data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Felt earthquakes data retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getFeltEarthquakes(Request $request)
    {
        try {
            $data = $this->fetchBmkgData('felt');
            
            return response()->json([
                'success' => true,
                'message' => 'Felt earthquakes data retrieved successfully',
                'data' => $data,
                'source' => 'BMKG (Badan Meteorologi, Klimatologi, dan Geofisika)',
                'last_updated' => now()->format('Y-m-d H:i:s')
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('BMKG Felt API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch felt earthquakes data from BMKG',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch earthquake data from BMKG API
     */
    private function fetchBmkgData($type = 'latest')
    {
        $endpoints = [
            'latest' => 'https://data.bmkg.go.id/DataMKG/TEWS/autogempa.json',
            'recent' => 'https://data.bmkg.go.id/DataMKG/TEWS/gempaterkini.json',
            'felt' => 'https://data.bmkg.go.id/DataMKG/TEWS/gempadirasakan.json'
        ];

        if (!isset($endpoints[$type])) {
            throw new \InvalidArgumentException("Invalid earthquake data type: {$type}");
        }

        $response = Http::timeout(10)->get($endpoints[$type]);

        if (!$response->successful()) {
            throw new \Exception("BMKG API returned status: {$response->status()}");
        }

        $data = $response->json();

        if (!$data) {
            throw new \Exception("Invalid JSON response from BMKG API");
        }

        return $this->formatBmkgData($data, $type);
    }

    /**
     * Format BMKG data to standardized format
     */
    private function formatBmkgData($data, $type)
    {
        switch ($type) {
            case 'latest':
                return $this->formatLatestEarthquake($data);
            case 'recent':
                return $this->formatRecentEarthquakes($data);
            case 'felt':
                return $this->formatFeltEarthquakes($data);
            default:
                return $data;
        }
    }

    /**
     * Format latest earthquake data
     */
    private function formatLatestEarthquake($data)
    {
        if (isset($data['Infogempa']['gempa'])) {
            $earthquake = $data['Infogempa']['gempa'];
            
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
        
        return $data;
    }

    /**
     * Format recent earthquakes data
     */
    private function formatRecentEarthquakes($data)
    {
        $earthquakes = [];
        
        if (isset($data['Infogempa']['gempa'])) {
            $gempaData = $data['Infogempa']['gempa'];
            
            // Handle single earthquake or array of earthquakes
            if (isset($gempaData['Tanggal'])) {
                $gempaData = [$gempaData];
            }
            
            foreach ($gempaData as $earthquake) {
                $earthquakes[] = [
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
        }
        
        return $earthquakes;
    }

    /**
     * Format felt earthquakes data
     */
    private function formatFeltEarthquakes($data)
    {
        $earthquakes = [];
        
        if (isset($data['Infogempa']['gempa'])) {
            $gempaData = $data['Infogempa']['gempa'];
            
            // Handle single earthquake or array of earthquakes
            if (isset($gempaData['Tanggal'])) {
                $gempaData = [$gempaData];
            }
            
            foreach ($gempaData as $earthquake) {
                $earthquakes[] = [
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
        }
        
        return $earthquakes;
    }

    /**
     * @OA\Post(
     *     path="/bmkg/sync/latest",
     *     summary="Sync latest earthquake from BMKG to database",
     *     description="Fetch latest earthquake data from BMKG and store it as a disaster in the database",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Latest earthquake synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Latest earthquake synced successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to sync earthquake data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to sync latest earthquake"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function syncLatestEarthquake(Request $request)
    {
        $syncService = new BmkgSyncService();
        $result = $syncService->syncLatestEarthquake();
        
        $statusCode = $result['success'] ? 200 : 500;
        
        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Post(
     *     path="/bmkg/sync/recent",
     *     summary="Sync recent earthquakes from BMKG to database",
     *     description="Fetch recent earthquakes (M 5.0+) from BMKG and store them as disasters in the database",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Recent earthquakes synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Synced 5 recent earthquakes. 2 already existed and were skipped."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="created", type="integer"),
     *                 @OA\Property(property="skipped", type="integer"),
     *                 @OA\Property(property="total_processed", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function syncRecentEarthquakes(Request $request)
    {
        $syncService = new BmkgSyncService();
        $result = $syncService->syncRecentEarthquakes();
        
        $statusCode = $result['success'] ? 200 : 500;
        
        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Post(
     *     path="/bmkg/sync/felt",
     *     summary="Sync felt earthquakes from BMKG to database",
     *     description="Fetch felt earthquakes from BMKG and store them as disasters in the database",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Felt earthquakes synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Synced 3 felt earthquakes. 1 already existed and was skipped."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="stats", type="object",
     *                 @OA\Property(property="created", type="integer"),
     *                 @OA\Property(property="skipped", type="integer"),
     *                 @OA\Property(property="total_processed", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function syncFeltEarthquakes(Request $request)
    {
        $syncService = new BmkgSyncService();
        $result = $syncService->syncFeltEarthquakes();
        
        $statusCode = $result['success'] ? 200 : 500;
        
        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Post(
     *     path="/bmkg/sync/all",
     *     summary="Sync all earthquake data from BMKG to database",
     *     description="Fetch all earthquake data (latest, recent, felt) from BMKG and store them as disasters in the database",
     *     tags={"BMKG"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All earthquake data synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sync completed. Created 8 new disasters, skipped 3 existing ones."),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_created", type="integer"),
     *                 @OA\Property(property="total_skipped", type="integer"),
     *                 @OA\Property(property="sync_types", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function syncAllEarthquakes(Request $request)
    {
        $syncService = new BmkgSyncService();
        $result = $syncService->syncAllEarthquakes();
        
        $statusCode = $result['success'] ? 200 : 500;
        
        return response()->json($result, $statusCode);
    }
}
