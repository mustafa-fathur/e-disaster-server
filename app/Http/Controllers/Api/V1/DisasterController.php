<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\PictureTypeEnum;
use App\Models\Disaster;
use App\Models\DisasterReport;
use App\Models\DisasterVictim;
use App\Models\DisasterAid;
use App\Models\DisasterVolunteer;
use App\Models\Notification;
use App\Models\Picture;
use App\Enums\DisasterTypeEnum;
use App\Enums\DisasterStatusEnum;
use App\Enums\DisasterSourceEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DisasterController extends Controller
{
    /**
     * @OA\Get(
     *     path="/dashboard",
     *     summary="Get dashboard data",
     *     description="Get dashboard statistics and data for authenticated user",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="assigned_disasters", type="integer"),
     *             @OA\Property(property="total_reports", type="integer"),
     *             @OA\Property(property="total_victims", type="integer"),
     *             @OA\Property(property="total_aids", type="integer"),
     *             @OA\Property(property="recent_disasters", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="recent_reports", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function dashboard(Request $request)
    {
        $user = auth('sanctum')->user();
        
        // Get user's assigned disasters
        $assignedDisasters = DisasterVolunteer::where('user_id', $user->id)
            ->with('disaster')
            ->get()
            ->pluck('disaster')
            ->filter(); // Remove null disasters

        $assignedDisasterIds = $assignedDisasters->pluck('id')->toArray();

        // Dashboard statistics
        $stats = [
            'total_disasters' => Disaster::count(),
            'assigned_disasters' => $assignedDisasters->count(),
            'ongoing_disasters' => $assignedDisasters->where('status', 'ongoing')->count(),
            'completed_disasters' => $assignedDisasters->where('status', 'completed')->count(),
            'total_reports' => DisasterReport::whereIn('disaster_id', $assignedDisasterIds)->count(),
            'total_victims' => DisasterVictim::whereIn('disaster_id', $assignedDisasterIds)->count(),
            'total_aids' => DisasterAid::whereIn('disaster_id', $assignedDisasterIds)->count(),
        ];

        // Recent disasters (assigned to user)
        $recentDisasters = $assignedDisasters
            ->sortByDesc('created_at')
            ->take(5)
            ->map(function ($disaster) {
                return [
                    'id' => $disaster->id,
                    'title' => $disaster->title,
                    'status' => $disaster->status->value,
                    'type' => $disaster->types->value,
                    'location' => $disaster->location,
                    'created_at' => $disaster->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Recent victims from assigned disasters
        $recentVictims = DisasterVictim::whereIn('disaster_id', $assignedDisasterIds)
            ->with('disaster')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($victim) {
                return [
                    'id' => $victim->id,
                    'name' => $victim->name,
                    'status' => $victim->status->value,
                    'is_evacuated' => $victim->is_evacuated,
                    'disaster_title' => $victim->disaster->title,
                    'disaster_id' => $victim->disaster_id,
                    'created_at' => $victim->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Recent aids from assigned disasters
        $recentAids = DisasterAid::whereIn('disaster_id', $assignedDisasterIds)
            ->with('disaster')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($aid) {
                return [
                    'id' => $aid->id,
                    'title' => $aid->title,
                    'category' => $aid->category->value,
                    'quantity' => $aid->quantity,
                    'unit' => $aid->unit,
                    'disaster_title' => $aid->disaster->title,
                    'disaster_id' => $aid->disaster_id,
                    'created_at' => $aid->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Unread notifications count
        $unreadNotifications = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'type' => $user->type->value,
                'status' => $user->status->value,
            ],
            'stats' => $stats,
            'recent_disasters' => $recentDisasters,
            'recent_reports' => $recentReports,
            'recent_victims' => $recentVictims,
            'recent_aids' => $recentAids,
            'unread_notifications' => $unreadNotifications,
        ], 200);
    }

    /**
     * Get all disasters (with pagination)
     */
    /**
     * @OA\Get(
     *     path="/disasters",
     *     summary="List all disasters",
     *     description="Get paginated list of all disasters with optional filtering",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for title or location",
     *         required=false,
     *         @OA\Schema(type="string", example="earthquake")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by disaster status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"ongoing", "completed", "cancelled"}, example="ongoing")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by disaster type",
     *         required=false,
     *         @OA\Schema(type="string", example="gempa bumi")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Disasters retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');
        $type = $request->get('type');
        $search = $request->get('search');

        $query = Disaster::query();

        // Apply filters
        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('types', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $disasters = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $disasters->items(),
            'pagination' => [
                'current_page' => $disasters->currentPage(),
                'last_page' => $disasters->lastPage(),
                'per_page' => $disasters->perPage(),
                'total' => $disasters->total(),
                'from' => $disasters->firstItem(),
                'to' => $disasters->lastItem(),
            ]
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/disasters/{id}",
     *     summary="Get disaster details",
     *     description="Get detailed information about a specific disaster including volunteers and pictures",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Disaster details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="source", type="string"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="lat", type="number"),
     *                 @OA\Property(property="long", type="number"),
     *                 @OA\Property(property="magnitude", type="number"),
     *                 @OA\Property(property="depth", type="number"),
     *                 @OA\Property(property="date", type="string", format="date"),
     *                 @OA\Property(property="time", type="string"),
     *                 @OA\Property(property="cancelled_reason", type="string"),
     *                 @OA\Property(property="cancelled_at", type="string", format="date-time"),
     *                 @OA\Property(property="completed_at", type="string", format="date-time"),
     *                 @OA\Property(property="pictures", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Disaster not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster not found.")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        // Get pictures for this disaster
        $pictures = Picture::where('foreign_id', $disaster->id)
            ->where('type', PictureTypeEnum::DISASTER->value)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($picture) {
                return [
                    'id' => $picture->id,
                    'caption' => $picture->caption,
                    'file_path' => $picture->file_path,
                    'url' => \Illuminate\Support\Facades\Storage::url($picture->file_path),
                    'mine_type' => $picture->mine_type,
                    'alt_text' => $picture->alt_text,
                    'created_at' => $picture->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'data' => [
                'id' => $disaster->id,
                'title' => $disaster->title,
                'description' => $disaster->description,
                'source' => $disaster->source->value,
                'type' => $disaster->types->value,
                'status' => $disaster->status->value,
                'date' => $disaster->date->format('Y-m-d'),
                'time' => $disaster->time->format('H:i:s'),
                'location' => $disaster->location,
                'coordinate' => $disaster->coordinate,
                'lat' => $disaster->lat,
                'long' => $disaster->long,
                'magnitude' => $disaster->magnitude,
                'depth' => $disaster->depth,
                'reported_by' => $disaster->reported_by,
                'cancelled_reason' => $disaster->cancelled_reason,
                'cancelled_at' => $disaster->cancelled_at?->format('Y-m-d H:i:s'),
                'cancelled_by' => $disaster->cancelled_by,
                'completed_at' => $disaster->completed_at?->format('Y-m-d H:i:s'),
                'completed_by' => $disaster->completed_by,
                'pictures' => $pictures,
                'created_at' => $disaster->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $disaster->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Create new disaster (Officer only)
     */
    /**
     * @OA\Post(
     *     path="/disasters",
     *     summary="Create new disaster",
     *     description="Create a new disaster and automatically assign creator as volunteer",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"title","type","location"},
    *                 @OA\Property(property="title", type="string", example="Earthquake in Jakarta"),
    *                 @OA\Property(property="description", type="string", example="Strong earthquake felt in Jakarta area"),
    *                 @OA\Property(property="type", type="string", enum={"gempa bumi","tsunami","gunung meletus","banjir","kekeringan","angin topan","tahan longsor","bencana non alam","bencana sosial"}, example="gempa bumi"),
    *                 @OA\Property(property="source", type="string", enum={"BMKG","manual"}, example="BMKG"),
    *                 @OA\Property(property="location", type="string", example="Jakarta, Indonesia"),
    *                 @OA\Property(property="lat", type="number", example=-6.2088),
    *                 @OA\Property(property="long", type="number", example=106.8456),
    *                 @OA\Property(property="magnitude", type="number", example=6.5),
    *                 @OA\Property(property="depth", type="number", example=10.5),
    *                 @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
    *                 @OA\Property(property="time", type="string", example="14:30:00")
    *             )
    *         ),
    *         @OA\MediaType(
    *             mediaType="multipart/form-data",
    *             @OA\Schema(
    *                 required={"title","type","location"},
    *                 @OA\Property(property="title", type="string", example="Earthquake in Jakarta"),
    *                 @OA\Property(property="description", type="string", example="Strong earthquake felt in Jakarta area"),
    *                 @OA\Property(property="type", type="string", enum={"gempa bumi","tsunami","gunung meletus","banjir","kekeringan","angin topan","tahan longsor","bencana non alam","bencana sosial"}, example="gempa bumi"),
    *                 @OA\Property(property="source", type="string", enum={"BMKG","manual"}, example="BMKG"),
    *                 @OA\Property(property="location", type="string", example="Jakarta, Indonesia"),
    *                 @OA\Property(property="lat", type="number", example=-6.2088),
    *                 @OA\Property(property="long", type="number", example=106.8456),
    *                 @OA\Property(property="magnitude", type="number", example=6.5),
    *                 @OA\Property(property="depth", type="number", example=10.5),
    *                 @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
    *                 @OA\Property(property="time", type="string", example="14:30:00"),
    *                 @OA\Property(
    *                     property="images[]",
    *                     type="array",
    *                     @OA\Items(type="string", format="binary"),
    *                     description="Optional images to attach to the disaster"
    *                 ),
    *                 @OA\Property(property="caption", type="string", example="Disaster scene"),
    *                 @OA\Property(property="alt_text", type="string", example="Photo showing earthquake damage")
    *             )
    *         )
    *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Disaster created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function createDisaster(Request $request)
    {
        // Normalize localized input values to enum keys (accept Indonesian labels)
        $typeMap = [
            'gempa bumi' => 'earthquake',
            'tsunami' => 'tsunami',
            'gunung meletus' => 'volcanic_eruption',
            'banjir' => 'flood',
            'kekeringan' => 'drought',
            'angin topan' => 'tornado',
            'tahan longsor' => 'landslide',
            'tanah longsor' => 'landslide',
            'bencana non alam' => 'non_natural_disaster',
            'bencana sosial' => 'social_disaster',
        ];
        $sourceMap = [
            'bmkg' => 'bmkg',
            'BMKG' => 'bmkg',
            'bmk' => 'bmkg', // common shorthand accepted
            'BMK' => 'bmkg',
            'manual' => 'manual',
        ];
        $statusMap = [
            'berlangsung' => 'ongoing',
            'selesai' => 'completed',
            'dibatalkan' => 'cancelled',
        ];

        $input = $request->all();
        if (!empty($input['type']) && isset($typeMap[strtolower($input['type'])])) {
            $input['type'] = $typeMap[strtolower($input['type'])];
        }
        if (!empty($input['source'])) {
            $srcKey = $input['source'];
            // try exact then lowercase key
            if (isset($sourceMap[$srcKey])) {
                $input['source'] = $sourceMap[$srcKey];
            } elseif (isset($sourceMap[strtolower($srcKey)])) {
                $input['source'] = $sourceMap[strtolower($srcKey)];
            }
        }
        if (!empty($input['status']) && isset($statusMap[strtolower($input['status'])])) {
            $input['status'] = $statusMap[strtolower($input['status'])];
        }

        $validator = Validator::make($input, [
            'title' => 'required|string|max:45',
            'description' => 'nullable|string',
            'source' => 'required|in:bmkg,manual',
            'type' => 'required|in:earthquake,tsunami,volcanic_eruption,flood,drought,tornado,landslide,non_natural_disaster,social_disaster',
            'status' => 'nullable|in:cancelled,ongoing,completed',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i:s',
            'location' => 'nullable|string|max:45',
            'coordinate' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
            'magnitude' => 'nullable|numeric|min:0',
            'depth' => 'nullable|numeric|min:0',
            // optional images in same request
            'images' => 'nullable|array',
            'images.*' => 'file|image|max:2048',
            'caption' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('sanctum')->user();

        $validated = $validator->validated();

        $disaster = Disaster::create([
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'source' => DisasterSourceEnum::from($validated['source']),
            'types' => DisasterTypeEnum::from($validated['type']),
            'status' => DisasterStatusEnum::from($validated['status'] ?? 'ongoing'),
            'date' => $validated['date'] ?? null,
            'time' => $validated['time'] ?? null,
            'location' => $validated['location'] ?? null,
            'coordinate' => $validated['coordinate'] ?? null,
            'lat' => $validated['lat'] ?? null,
            'long' => $validated['long'] ?? null,
            'magnitude' => $validated['magnitude'] ?? null,
            'depth' => $validated['depth'] ?? null,
            'reported_by' => $user->id,
        ]);

        // Automatically assign the creator as a volunteer
        DisasterVolunteer::create([
            'disaster_id' => $disaster->id,
            'user_id' => $user->id,
        ]);

        // If images provided, save them now
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('pictures/disaster', $fileName, 'public');

                Picture::create([
                    'foreign_id' => $disaster->id,
                    'type' => PictureTypeEnum::DISASTER,
                    'caption' => $request->caption,
                    'file_path' => $filePath,
                    'mine_type' => $file->getMimeType(),
                    'alt_text' => $request->alt_text,
                ]);
            }
        }

        return response()->json([
            'message' => 'Disaster created successfully. You have been automatically assigned as a volunteer.',
            'data' => [
                'id' => $disaster->id,
                'title' => $disaster->title,
                'type' => $disaster->types->value,
                'status' => $disaster->status->value,
                'location' => $disaster->location,
                'date' => $disaster->date->format('Y-m-d'),
                'time' => $disaster->time->format('H:i:s'),
                'created_at' => $disaster->created_at->format('Y-m-d H:i:s'),
                'auto_assigned' => true,
                'images_attached' => $request->hasFile('images'),
            ]
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/disasters/{id}",
     *     summary="Update disaster",
     *     description="Update a specific disaster (assigned users only)",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Earthquake in Jakarta"),
     *             @OA\Property(property="description", type="string", example="Updated strong earthquake felt in Jakarta area"),
     *             @OA\Property(property="type", type="string", enum={"gempa bumi","tsunami","gunung meletus","banjir","kekeringan","angin topan","tahan longsor","bencana non alam","bencana sosial"}, example="gempa bumi"),
     *             @OA\Property(property="source", type="string", enum={"BMKG","manual"}, example="BMKG"),
     *             @OA\Property(property="location", type="string", example="Jakarta, Indonesia"),
     *             @OA\Property(property="lat", type="number", example=-6.2088),
     *             @OA\Property(property="long", type="number", example=106.8456),
     *             @OA\Property(property="magnitude", type="number", example=6.5),
     *             @OA\Property(property="depth", type="number", example=10.5),
     *             @OA\Property(property="date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="time", type="string", example="14:30:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Disaster updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster updated successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied or disaster cannot be modified",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied. You are not assigned to this disaster.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Disaster not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster not found.")
     *         )
     *     )
     * )
     */
    public function updateDisaster(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        // Check if disaster is cancelled or completed - cannot be modified
        if (in_array($disaster->status, [DisasterStatusEnum::CANCELLED, DisasterStatusEnum::COMPLETED])) {
            return response()->json([
                'message' => 'Cannot modify disaster. Disaster is already ' . $disaster->status->value . '.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:45',
            'description' => 'nullable|string',
            'source' => 'sometimes|required|in:bmkg,manual',
            'type' => 'sometimes|required|in:earthquake,tsunami,volcanic_eruption,flood,drought,tornado,landslide,non_natural_disaster,social_disaster',
            'status' => 'sometimes|required|in:cancelled,ongoing,completed',
            'date' => 'sometimes|required|date',
            'time' => 'sometimes|required|date_format:H:i:s',
            'location' => 'nullable|string|max:45',
            'coordinate' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
            'magnitude' => 'nullable|numeric|min:0',
            'depth' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'title', 'description', 'date', 'time', 'location', 
            'coordinate', 'lat', 'long', 'magnitude', 'depth'
        ]);

        // Handle enum fields
        if ($request->has('source')) {
            $updateData['source'] = DisasterSourceEnum::from($request->source);
        }
        if ($request->has('type')) {
            $updateData['types'] = DisasterTypeEnum::from($request->type);
        }
        if ($request->has('status')) {
            $updateData['status'] = DisasterStatusEnum::from($request->status);
        }

        $disaster->update($updateData);

        return response()->json([
            'message' => 'Disaster updated successfully.',
            'data' => [
                'id' => $disaster->id,
                'title' => $disaster->title,
                'type' => $disaster->types->value,
                'status' => $disaster->status->value,
                'location' => $disaster->location,
                'date' => $disaster->date->format('Y-m-d'),
                'time' => $disaster->time->format('H:i:s'),
                'updated_at' => $disaster->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/disasters/{id}/cancel",
     *     summary="Cancel disaster",
     *     description="Cancel a specific disaster with reason (assigned users only)",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"cancelled_reason"},
     *             @OA\Property(property="cancelled_reason", type="string", example="False alarm - no actual disaster occurred", description="Reason for cancelling the disaster")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Disaster cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster cancelled successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Disaster cannot be cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Only ongoing disasters can be cancelled.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - not assigned to disaster",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are not assigned to this disaster.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Disaster not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function cancelDisaster(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        // Only ongoing disasters can be cancelled
        if ($disaster->status !== DisasterStatusEnum::ONGOING) {
            return response()->json([
                'message' => 'Only ongoing disasters can be cancelled. Current status: ' . $disaster->status->value
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'cancelled_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the disaster volunteer assignment for this user
        $user = auth('sanctum')->user();
        $disasterVolunteer = DisasterVolunteer::where('disaster_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$disasterVolunteer) {
            return response()->json([
                'message' => 'You are not assigned to this disaster.'
            ], 403);
        }

        $disaster->update([
            'status' => DisasterStatusEnum::CANCELLED,
            'cancelled_reason' => $request->cancelled_reason,
            'cancelled_at' => now(),
            'cancelled_by' => $disasterVolunteer->id
        ]);

        return response()->json([
            'message' => 'Disaster cancelled successfully.',
            'data' => [
                'id' => $disaster->id,
                'title' => $disaster->title,
                'status' => $disaster->status->value,
                'cancelled_reason' => $disaster->cancelled_reason,
                'cancelled_at' => $disaster->cancelled_at->format('Y-m-d H:i:s'),
                'cancelled_by' => $disasterVolunteer->id
            ]
        ], 200);
    }

    /**
     * Get volunteers assigned to a specific disaster
     */
    /**
     * @OA\Get(
     *     path="/disasters/{id}/volunteers",
     *     summary="List disaster volunteers",
     *     description="Get paginated list of volunteers assigned to a specific disaster",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by volunteer name or email",
     *         required=false,
     *         @OA\Schema(type="string", example="fathur")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Volunteers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="disaster_id", type="string"),
     *                 @OA\Property(property="user_id", type="string"),
     *                 @OA\Property(property="user_name", type="string"),
     *                 @OA\Property(property="user_email", type="string"),
     *                 @OA\Property(property="user_type", type="string"),
     *                 @OA\Property(property="user_status", type="string"),
     *                 @OA\Property(property="assigned_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Disaster not found")
     * )
     */
    public function getDisasterVolunteers(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = DisasterVolunteer::where('disaster_id', $id)
            ->with('user');

        // Apply search filter
        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $volunteers = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $volunteerData = $volunteers->items();
        $mappedVolunteers = collect($volunteerData)->map(function ($assignment) {
            return [
                'id' => $assignment->id,
                'disaster_id' => $assignment->disaster_id,
                'user_id' => $assignment->user_id,
                'user_name' => $assignment->user->name,
                'user_email' => $assignment->user->email,
                'user_type' => $assignment->user->type->value,
                'user_status' => $assignment->user->status->value,
                'assigned_at' => $assignment->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'data' => $mappedVolunteers,
            'pagination' => [
                'current_page' => $volunteers->currentPage(),
                'last_page' => $volunteers->lastPage(),
                'per_page' => $volunteers->perPage(),
                'total' => $volunteers->total(),
                'from' => $volunteers->firstItem(),
                'to' => $volunteers->lastItem(),
            ]
        ], 200);
    }

        /**
     * @OA\Get(
     *     path="/disasters/{id}/volunteer-check",
     *     summary="Check if the current user is assigned to the disaster",
     *     description="Checks if the authenticated user is in the disaster_volunteers table for the given disaster.",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Check successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="assigned", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Disaster not found"
     *     )
     * )
     */
    public function checkVolunteerAssignment(Request $request, $id)
    {
        $user = auth('sanctum')->user();

        $isAssigned = DisasterVolunteer::where('disaster_id', $id)
            ->where('user_id', $user->id)
            ->exists();

        return response()->json([
            'assigned' => $isAssigned
        ], 200);
    }


    /**
     * Self-assign to disaster (VOLUNTEER FOR THIS DISASTER button)
     */
    /**
     * @OA\Post(
     *     path="/disasters/{id}/volunteers",
     *     summary="Self-assign to disaster",
     *     description="Assign the authenticated user as a volunteer to the specified disaster",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully volunteered for the disaster",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully volunteered for this disaster."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="disaster_id", type="string"),
     *                 @OA\Property(property="user_id", type="string"),
     *                 @OA\Property(property="user_name", type="string"),
     *                 @OA\Property(property="user_email", type="string"),
     *                 @OA\Property(property="assigned_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Disaster not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Already volunteering for the disaster",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You are already volunteering for this disaster.")
     *         )
     *     )
     * )
     */
    public function assignVolunteerToDisaster(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $user = auth('sanctum')->user();

        // Check if user is already assigned to this disaster
        $existingAssignment = DisasterVolunteer::where('disaster_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingAssignment) {
            return response()->json([
                'message' => 'You are already volunteering for this disaster.'
            ], 422);
        }

        $assignment = DisasterVolunteer::create([
            'disaster_id' => $id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'Successfully volunteered for this disaster.',
            'data' => [
                'id' => $assignment->id,
                'disaster_id' => $assignment->disaster_id,
                'user_id' => $assignment->user_id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'assigned_at' => $assignment->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * Self-unassign from disaster (STOP VOLUNTEERING button)
     */
    /**
     * @OA\Delete(
     *     path="/disasters/{id}/volunteers/{volunteerId}",
     *     summary="Self-unassign from disaster",
     *     description="Remove the authenticated user from volunteering for the specified disaster",
     *     tags={"Disasters"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="volunteerId",
     *         in="path",
     *         description="Authenticated user ID (must match)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully stopped volunteering",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully stopped volunteering for this disaster.")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Disaster not found or not volunteering"),
     *     @OA\Response(
     *         response=403,
     *         description="Cannot remove other users",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You can only remove yourself from volunteering.")
     *         )
     *     )
     * )
     */
    public function removeVolunteerFromDisaster(Request $request, $id, $volunteerId)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $user = auth('sanctum')->user();

        // Users can only remove themselves
        if ($volunteerId !== $user->id) {
            return response()->json([
                'message' => 'You can only remove yourself from volunteering.'
            ], 403);
        }

        $assignment = DisasterVolunteer::where('disaster_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$assignment) {
            return response()->json([
                'message' => 'You are not volunteering for this disaster.'
            ], 404);
        }

        $assignment->delete();

        return response()->json([
            'message' => 'Successfully stopped volunteering for this disaster.'
        ], 200);
    }
}
