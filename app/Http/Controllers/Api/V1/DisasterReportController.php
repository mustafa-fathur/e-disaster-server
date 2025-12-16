<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\PictureTypeEnum;
use App\Enums\NotificationTypeEnum;
use App\Models\Disaster;
use App\Models\DisasterReport;
use App\Models\DisasterVolunteer;
use App\Models\Notification;
use App\Models\Picture;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DisasterReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/disasters/{id}/reports",
     *     summary="Get disaster reports",
     *     description="Get paginated list of reports for a specific disaster (assigned users only)",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
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
     *         description="Search term for title or description",
     *         required=false,
     *         @OA\Schema(type="string", example="damage")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reports retrieved successfully",
    *         @OA\JsonContent(
    *             @OA\Property(property="data", type="array", @OA\Items(type="object",
    *                 @OA\Property(property="id", type="string"),
    *                 @OA\Property(property="disaster_id", type="string"),
    *                 @OA\Property(property="reported_by", type="string"),
    *                 @OA\Property(property="title", type="string"),
    *                 @OA\Property(property="description", type="string"),
    *                 @OA\Property(property="lat", type="number", format="float", nullable=true, example=-6.2088),
    *                 @OA\Property(property="long", type="number", format="float", nullable=true, example=106.8456),
    *                 @OA\Property(property="is_final_stage", type="boolean"),
    *                 @OA\Property(property="created_at", type="string", format="date-time"),
    *                 @OA\Property(property="updated_at", type="string", format="date-time")
    *             )),
    *             @OA\Property(property="pagination", type="object")
    *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - not assigned to disaster",
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
    public function getDisasterReports(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = DisasterReport::where('disaster_id', $id)
            ->with(['disaster', 'reporter.user']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $reports = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $reportData = $reports->items();
        $mappedReports = collect($reportData)->map(function ($report) {
            return [
                'id' => $report->id,
                'disaster_id' => $report->disaster_id,
                'disaster_title' => $report->disaster->title,
                'title' => $report->title,
                'description' => $report->description,
                'lat' => $report->lat,
                'long' => $report->long,
                'is_final_stage' => $report->is_final_stage,
                'reported_by' => $report->reported_by,
                'reporter_name' => $report->reporter->user->name ?? 'Unknown',
                'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'data' => $mappedReports,
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'from' => $reports->firstItem(),
                'to' => $reports->lastItem(),
            ]
        ], 200);
    }

    /**
     * Create new disaster report
     */
    /**
     * @OA\Post(
     *     path="/disasters/{id}/reports",
     *     summary="Create disaster report",
     *     description="Create a new report for a specific disaster (assigned users only). After successful creation, database notifications and FCM push notifications will be sent to all volunteers assigned to this disaster (except the creator). If marked as final stage, the disaster will be completed and status change notifications will also be sent.",
     *     tags={"Reports"},
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
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"title","description"},
    *                 @OA\Property(property="title", type="string", example="Damage Assessment Report"),
    *                 @OA\Property(property="description", type="string", example="Detailed report on building damage and casualties"),
    *                 @OA\Property(property="is_final_stage", type="boolean", example=false, description="Mark as final stage (completes disaster)"),
    *                 @OA\Property(property="lat", type="number", format="float", nullable=true, example=-6.2088),
    *                 @OA\Property(property="long", type="number", format="float", nullable=true, example=106.8456)
    *             )
    *         ),
    *         @OA\MediaType(
    *             mediaType="multipart/form-data",
    *             @OA\Schema(
    *                 required={"title","description"},
    *                 @OA\Property(property="title", type="string", example="Damage Assessment Report"),
    *                 @OA\Property(property="description", type="string", example="Detailed report on building damage and casualties"),
    *                 @OA\Property(property="is_final_stage", type="boolean", example=false, description="Mark as final stage (completes disaster)"),
    *                 @OA\Property(property="images[]", type="array", @OA\Items(type="string", format="binary"), description="Optional images to attach to the report"),
    *                 @OA\Property(property="caption", type="string", example="Report photo"),
    *                 @OA\Property(property="alt_text", type="string", example="Photo showing damages")
    *             )
    *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Report created successfully",
     *         @OA\JsonContent(
    *             @OA\Property(property="message", type="string", example="Disaster report created successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - not assigned to disaster",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied. You are not assigned to this disaster.")
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
    public function createDisasterReport(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:45',
            'description' => 'required|string',
            'is_final_stage' => 'nullable|boolean',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
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

        // Get the disaster volunteer assignment for this user
        $disasterVolunteer = DisasterVolunteer::where('disaster_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$disasterVolunteer) {
            return response()->json([
                'message' => 'You are not assigned to this disaster.'
            ], 403);
        }

        $report = DisasterReport::create([
            'disaster_id' => $id,
            'title' => $request->title,
            'description' => $request->description,
            'lat' => $request->lat,
            'long' => $request->long,
            'is_final_stage' => $request->is_final_stage ?? false,
            'reported_by' => $disasterVolunteer->id, // Reference to disaster_volunteers table
        ]);

        // If images provided, save them now
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('pictures/disaster_report', $fileName, 'public');

                Picture::create([
                    'foreign_id' => $report->id,
                    'type' => PictureTypeEnum::DISASTER_REPORT,
                    'caption' => $request->caption,
                    'file_path' => $filePath,
                    'mine_type' => $file->getMimeType(),
                    'alt_text' => $request->alt_text,
                ]);
            }
        }

        // Send notifications to all assigned volunteers (except creator) about new report
        try {
            $assignedVolunteers = DisasterVolunteer::where('disaster_id', $id)
                ->where('user_id', '!=', $user->id)
                ->with('user')
                ->get();

            // Create database notifications
            foreach ($assignedVolunteers as $volunteer) {
                Notification::create([
                    'user_id' => $volunteer->user_id,
                    'title' => 'New Disaster Report',
                    'message' => "A new report has been added to {$disaster->title}: {$report->title}",
                    'category' => NotificationTypeEnum::NEW_DISASTER_REPORT,
                    'is_read' => false,
                    'sent_at' => now(),
                ]);
            }

            // Send FCM push notifications
            $fcmService = app(FcmService::class);
            if ($fcmService->isEnabled()) {
                $fcmResult = $fcmService->sendToDisasterVolunteers(
                    disasterId: $id,
                    title: 'New Disaster Report',
                    body: "A new report has been added to {$disaster->title}",
                    data: [
                        'type' => 'new_disaster_report',
                        'disaster_id' => $disaster->id,
                        'report_id' => $report->id,
                        'disaster_title' => $disaster->title,
                        'report_title' => $report->title,
                    ],
                    excludeUserId: $user->id
                );

                if (!$fcmResult['success']) {
                    Log::warning('FCM notification failed for disaster report', [
                        'disaster_id' => $id,
                        'report_id' => $report->id,
                        'error' => $fcmResult['message'] ?? 'Unknown error'
                    ]);
                }
            } else {
                Log::info('FCM service is not enabled. Database notifications created but push notifications skipped.');
            }
        } catch (\Exception $e) {
            Log::error('Failed to send notifications for disaster report', [
                'disaster_id' => $id,
                'report_id' => $report->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // If this is a final stage report, update disaster status to completed
        if ($request->is_final_stage) {
            $disaster->update([
                'status' => \App\Enums\DisasterStatusEnum::COMPLETED,
                'completed_at' => now(),
                'completed_by' => $disasterVolunteer->id
            ]);

            // Send notifications about disaster completion
            try {
                $assignedVolunteers = DisasterVolunteer::where('disaster_id', $id)
                    ->where('user_id', '!=', $user->id)
                    ->with('user')
                    ->get();

                // Create database notifications for status change
                foreach ($assignedVolunteers as $volunteer) {
                    Notification::create([
                        'user_id' => $volunteer->user_id,
                        'title' => 'Disaster Status Changed',
                        'message' => "Disaster '{$disaster->title}' has been marked as completed",
                        'category' => NotificationTypeEnum::DISASTER_STATUS_CHANGED,
                        'is_read' => false,
                        'sent_at' => now(),
                    ]);
                }

                // Send FCM push notifications for status change
                $fcmService = app(FcmService::class);
                if ($fcmService->isEnabled()) {
                    $fcmResult = $fcmService->sendToDisasterVolunteers(
                        disasterId: $id,
                        title: 'Disaster Status Changed',
                        body: "Disaster '{$disaster->title}' has been marked as completed",
                        data: [
                            'type' => 'disaster_status_changed',
                            'disaster_id' => $disaster->id,
                            'disaster_title' => $disaster->title,
                            'status' => 'completed',
                        ],
                        excludeUserId: $user->id
                    );

                    if (!$fcmResult['success']) {
                        Log::warning('FCM notification failed for disaster completion', [
                            'disaster_id' => $id,
                            'error' => $fcmResult['message'] ?? 'Unknown error'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send notifications for disaster completion', [
                    'disaster_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return response()->json([
            'message' => 'Disaster report created successfully.',
            'data' => [
                'id' => $report->id,
                'disaster_id' => $report->disaster_id,
                'title' => $report->title,
                'description' => $report->description,
                'lat' => $report->lat,
                'long' => $report->long,
                'is_final_stage' => $report->is_final_stage,
                'reported_by' => $report->reported_by,
                'reporter_name' => $report->reporter->user->name ?? 'Unknown',
                'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                'images_attached' => $request->hasFile('images'),
            ]
        ], 201);
    }

    /**
     * Get specific disaster report
     */
    /**
     * @OA\Get(
     *     path="/disasters/{id}/reports/{reportId}",
     *     summary="Get disaster report details",
     *     description="Get detailed information about a specific disaster report (assigned users only)",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="reportId",
     *         in="path",
     *         description="Report ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
    *                 @OA\Property(property="disaster_id", type="string"),
    *                 @OA\Property(property="disaster_title", type="string"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="description", type="string"),
    *                 @OA\Property(property="is_final_stage", type="boolean"),
    *                 @OA\Property(property="lat", type="number", format="float", nullable=true, example=-6.2088),
    *                 @OA\Property(property="long", type="number", format="float", nullable=true, example=106.8456),
    *                 @OA\Property(property="reported_by", type="string"),
    *                 @OA\Property(property="reporter_name", type="string"),
     *                 @OA\Property(property="pictures", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - not assigned to disaster",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied. You are not assigned to this disaster.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Report not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster report not found.")
     *         )
     *     )
     * )
     */
    public function getDisasterReport(Request $request, $id, $reportId)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $report = DisasterReport::where('disaster_id', $id)
            ->where('id', $reportId)
            ->with(['disaster', 'reporter.user'])
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Disaster report not found.'
            ], 404);
        }

        // Get pictures for this report
        $pictures = Picture::where('foreign_id', $report->id)
            ->where('type', PictureTypeEnum::DISASTER_REPORT->value)
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
                'id' => $report->id,
                'disaster_id' => $report->disaster_id,
                'disaster_title' => $report->disaster->title,
                'title' => $report->title,
                'description' => $report->description,
                'lat' => $report->lat,
                'long' => $report->long,
                'is_final_stage' => $report->is_final_stage,
                'reported_by' => $report->reported_by,
                'reporter_name' => $report->reporter->user->name ?? 'Unknown',
                'pictures' => $pictures,
                'created_at' => $report->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Update disaster report
     */
    /**
     * @OA\Put(
     *     path="/disasters/{id}/reports/{reportId}",
     *     summary="Update disaster report",
     *     description="Update a specific disaster report (assigned users only). If the report is updated to final stage, the disaster will be completed and status change notifications will be sent to all volunteers assigned to this disaster (except the creator).",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="reportId",
     *         in="path",
     *         description="Report ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Damage Assessment Report"),
     *             @OA\Property(property="description", type="string", example="Updated detailed report on building damage and casualties"),
    *             @OA\Property(property="is_final_stage", type="boolean", example=true, description="Mark as final stage (completes disaster)"),
    *             @OA\Property(property="lat", type="number", format="float", nullable=true, example=-6.2088),
    *             @OA\Property(property="long", type="number", format="float", nullable=true, example=106.8456)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster report updated successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - not assigned to disaster",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied. You are not assigned to this disaster.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Report not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster report not found.")
     *         )
     *     )
     * )
     */
    public function updateDisasterReport(Request $request, $id, $reportId)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $report = DisasterReport::where('disaster_id', $id)
            ->where('id', $reportId)
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Disaster report not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:45',
            'description' => 'sometimes|required|string',
            'is_final_stage' => 'nullable|boolean',
            'lat' => 'nullable|numeric|between:-90,90',
            'long' => 'nullable|numeric|between:-180,180',
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

        $updateData = $request->only(['title', 'description', 'is_final_stage', 'lat', 'long']);
        $report->update($updateData);

        // If this is a final stage report, update disaster status to completed
        if ($request->has('is_final_stage') && $request->is_final_stage) {
            $disaster->update([
                'status' => \App\Enums\DisasterStatusEnum::COMPLETED,
                'completed_at' => now(),
                'completed_by' => $disasterVolunteer->id
            ]);

            // Send notifications about disaster completion
            try {
                $assignedVolunteers = DisasterVolunteer::where('disaster_id', $id)
                    ->where('user_id', '!=', $user->id)
                    ->with('user')
                    ->get();

                // Create database notifications for status change
                foreach ($assignedVolunteers as $volunteer) {
                    Notification::create([
                        'user_id' => $volunteer->user_id,
                        'title' => 'Disaster Status Changed',
                        'message' => "Disaster '{$disaster->title}' has been marked as completed",
                        'category' => NotificationTypeEnum::DISASTER_STATUS_CHANGED,
                        'is_read' => false,
                        'sent_at' => now(),
                    ]);
                }

                // Send FCM push notifications for status change
                $fcmService = app(FcmService::class);
                if ($fcmService->isEnabled()) {
                    $fcmResult = $fcmService->sendToDisasterVolunteers(
                        disasterId: $id,
                        title: 'Disaster Status Changed',
                        body: "Disaster '{$disaster->title}' has been marked as completed",
                        data: [
                            'type' => 'disaster_status_changed',
                            'disaster_id' => $disaster->id,
                            'disaster_title' => $disaster->title,
                            'status' => 'completed',
                        ],
                        excludeUserId: $user->id
                    );

                    if (!$fcmResult['success']) {
                        Log::warning('FCM notification failed for disaster completion', [
                            'disaster_id' => $id,
                            'error' => $fcmResult['message'] ?? 'Unknown error'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to send notifications for disaster completion', [
                    'disaster_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return response()->json([
            'message' => 'Disaster report updated successfully.',
            'data' => [
                'id' => $report->id,
                'disaster_id' => $report->disaster_id,
                'title' => $report->title,
                'description' => $report->description,
                'lat' => $report->lat,
                'long' => $report->long,
                'is_final_stage' => $report->is_final_stage,
                'reported_by' => $report->reported_by,
                'updated_at' => $report->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Delete disaster report
     */
    /**
     * @OA\Delete(
     *     path="/disasters/{id}/reports/{reportId}",
     *     summary="Delete disaster report",
     *     description="Delete a specific disaster report (assigned users only)",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="reportId",
     *         in="path",
     *         description="Report ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster report deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - not assigned to disaster",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied. You are not assigned to this disaster.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Report not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster report not found.")
     *         )
     *     )
     * )
     */
    public function deleteDisasterReport(Request $request, $id, $reportId)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $report = DisasterReport::where('disaster_id', $id)
            ->where('id', $reportId)
            ->first();

        if (!$report) {
            return response()->json([
                'message' => 'Disaster report not found.'
            ], 404);
        }

        $report->delete();

        return response()->json([
            'message' => 'Disaster report deleted successfully.'
        ], 200);
    }
}
