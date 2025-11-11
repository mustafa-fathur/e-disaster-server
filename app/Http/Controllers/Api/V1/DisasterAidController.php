<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\PictureTypeEnum;
use App\Models\Disaster;
use App\Models\DisasterAid;
use App\Models\DisasterVolunteer;
use App\Models\Picture;
use App\Enums\DisasterAidCategoryEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DisasterAidController extends Controller
{
    /**
     * @OA\Get(
     *     path="/disasters/{id}/aids",
     *     summary="Get disaster aids",
     *     description="Get paginated list of aids for a specific disaster (assigned users only)",
     *     tags={"Aids"},
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
     *         description="Search term for name or description",
     *         required=false,
     *         @OA\Schema(type="string", example="food")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by aid category",
     *         required=false,
     *         @OA\Schema(type="string", enum={"makanan","obat","pakaian","shelter","transportasi","lainnya"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aids retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
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
    public function getDisasterAids(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = DisasterAid::where('disaster_id', $id)
            ->with(['disaster', 'reporter.user']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $aids = $query->paginate($perPage);

        return response()->json([
            'data' => $aids->items(),
            'pagination' => [
                'current_page' => $aids->currentPage(),
                'per_page' => $aids->perPage(),
                'total' => $aids->total(),
                'last_page' => $aids->lastPage(),
                'from' => $aids->firstItem(),
                'to' => $aids->lastItem(),
            ]
        ], 200);
    }

    /**
     * Create new disaster aid
     */
    /**
     * @OA\Post(
     *     path="/disasters/{id}/aids",
     *     summary="Create disaster aid record",
     *     description="Create a new aid record for a specific disaster (assigned users only)",
     *     tags={"Aids"},
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
     *             required={"name","category","quantity"},
     *             @OA\Property(property="name", type="string", example="Emergency Food Pack"),
     *             @OA\Property(property="category", type="string", enum={"makanan","obat","pakaian","shelter","transportasi","lainnya"}, example="makanan"),
     *             @OA\Property(property="quantity", type="integer", example=100),
     *             @OA\Property(property="description", type="string", example="Ready-to-eat meals for disaster victims"),
     *             @OA\Property(property="location", type="string", example="Distribution Center A"),
     *             @OA\Property(property="lat", type="number", example=-6.2088),
     *             @OA\Property(property="long", type="number", example=106.8456)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Aid record created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster aid created successfully."),
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
    public function createDisasterAid(Request $request, $id)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:45',
            'description' => 'nullable|string',
            'category' => 'required|in:food,clothing,housing',
            'quantity' => 'required|integer|min:1',
            'unit' => 'required|string|max:45',
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

        $aid = DisasterAid::create([
            'disaster_id' => $id,
            'title' => $request->title,
            'description' => $request->description,
            'category' => DisasterAidCategoryEnum::from($request->category),
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'reported_by' => $disasterVolunteer->id,
        ]);

        return response()->json([
            'message' => 'Disaster aid created successfully.',
            'data' => [
                'id' => $aid->id,
                'disaster_id' => $aid->disaster_id,
                'title' => $aid->title,
                'description' => $aid->description,
                'category' => $aid->category->value,
                'quantity' => $aid->quantity,
                'unit' => $aid->unit,
                'reported_by' => $aid->reported_by,
                'created_at' => $aid->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * Get specific disaster aid
     */
    /**
     * @OA\Get(
     *     path="/disasters/{id}/aids/{aidId}",
     *     summary="Get disaster aid details",
     *     description="Get detailed information about a specific disaster aid (assigned users only)",
     *     tags={"Aids"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="aidId",
     *         in="path",
     *         description="Aid ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aid details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="category", type="string"),
     *                 @OA\Property(property="quantity", type="integer"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="lat", type="number"),
     *                 @OA\Property(property="long", type="number"),
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
     *         description="Aid not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster aid not found.")
     *         )
     *     )
     * )
     */
    public function getDisasterAid(Request $request, $id, $aidId)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $aid = DisasterAid::where('disaster_id', $id)
            ->where('id', $aidId)
            ->with(['disaster', 'reporter.user'])
            ->first();

        if (!$aid) {
            return response()->json([
                'message' => 'Disaster aid not found.'
            ], 404);
        }

        // Get pictures for this aid
        $pictures = Picture::where('foreign_id', $aid->id)
            ->where('type', PictureTypeEnum::DISASTER_AID->value)
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
                'id' => $aid->id,
                'disaster_id' => $aid->disaster_id,
                'disaster_title' => $aid->disaster->title,
                'title' => $aid->title,
                'description' => $aid->description,
                'category' => $aid->category->value,
                'quantity' => $aid->quantity,
                'unit' => $aid->unit,
                'reported_by' => $aid->reported_by,
                'reporter_name' => $aid->reporter->user->name ?? 'Unknown',
                'pictures' => $pictures,
                'created_at' => $aid->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $aid->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Update disaster aid
     */
    /**
     * @OA\Put(
     *     path="/disasters/{id}/aids/{aidId}",
     *     summary="Update disaster aid record",
     *     description="Update a specific disaster aid record (assigned users only)",
     *     tags={"Aids"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="aidId",
     *         in="path",
     *         description="Aid ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Emergency Food Pack"),
     *             @OA\Property(property="category", type="string", enum={"makanan","obat","pakaian","shelter","transportasi","lainnya"}, example="makanan"),
     *             @OA\Property(property="quantity", type="integer", example=150),
     *             @OA\Property(property="description", type="string", example="Updated ready-to-eat meals for disaster victims"),
     *             @OA\Property(property="location", type="string", example="Updated Distribution Center A"),
     *             @OA\Property(property="lat", type="number", example=-6.2088),
     *             @OA\Property(property="long", type="number", example=106.8456)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aid record updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster aid updated successfully."),
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
     *         description="Aid not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster aid not found.")
     *         )
     *     )
     * )
     */
    public function updateDisasterAid(Request $request, $id, $aidId)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $aid = DisasterAid::where('disaster_id', $id)
            ->where('id', $aidId)
            ->first();

        if (!$aid) {
            return response()->json([
                'message' => 'Disaster aid not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:45',
            'description' => 'nullable|string',
            'category' => 'sometimes|required|in:food,clothing,housing',
            'quantity' => 'sometimes|required|integer|min:1',
            'unit' => 'sometimes|required|string|max:45',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'title', 'description', 'quantity', 'unit'
        ]);

        // Handle enum field
        if ($request->has('category')) {
            $updateData['category'] = DisasterAidCategoryEnum::from($request->category);
        }

        $aid->update($updateData);

        return response()->json([
            'message' => 'Disaster aid updated successfully.',
            'data' => [
                'id' => $aid->id,
                'disaster_id' => $aid->disaster_id,
                'title' => $aid->title,
                'description' => $aid->description,
                'category' => $aid->category->value,
                'quantity' => $aid->quantity,
                'unit' => $aid->unit,
                'updated_at' => $aid->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * Delete disaster aid
     */
    /**
     * @OA\Delete(
     *     path="/disasters/{id}/aids/{aidId}",
     *     summary="Delete disaster aid record",
     *     description="Delete a specific disaster aid record (assigned users only)",
     *     tags={"Aids"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Disaster ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="aidId",
     *         in="path",
     *         description="Aid ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Aid record deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster aid deleted successfully.")
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
     *         description="Aid not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster aid not found.")
     *         )
     *     )
     * )
     */
    public function deleteDisasterAid(Request $request, $id, $aidId)
    {
        $disaster = Disaster::find($id);

        if (!$disaster) {
            return response()->json([
                'message' => 'Disaster not found.'
            ], 404);
        }

        $aid = DisasterAid::where('disaster_id', $id)
            ->where('id', $aidId)
            ->first();

        if (!$aid) {
            return response()->json([
                'message' => 'Disaster aid not found.'
            ], 404);
        }

        $aid->delete();

        return response()->json([
            'message' => 'Disaster aid deleted successfully.'
        ], 200);
    }
}