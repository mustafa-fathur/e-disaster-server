<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Picture;
use App\Enums\PictureTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class PictureController extends Controller
{
    /**
     * @OA\Post(
     *     path="/pictures/{modelType}/{modelId}",
     *     summary="Upload images",
     *     description="Upload one or more images for a specific model (disaster, disaster_report, disaster_victim, disaster_aid). Use multiple files under the `images[]` field.",
     *     tags={"Pictures"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="modelType",
     *         in="path",
     *         description="Model type",
     *         required=true,
     *         @OA\Schema(type="string", enum={"disaster","disaster_report","disaster_victim","disaster_aid"})
     *     ),
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         description="Model ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"images"},
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary"),
     *                     description="One or more image files (max 2MB each)"
     *                 ),
     *                 @OA\Property(property="caption", type="string", example="Disaster scene photo"),
     *                 @OA\Property(property="alt_text", type="string", example="Photo showing earthquake damage")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Images uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Images uploaded successfully."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
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
    public function uploadImage(Request $request, $modelType, $modelId)
    {
        $validator = Validator::make($request->all(), [
            // Support both single and multiple file fields
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048',
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpeg,png,jpg,gif|max:2048',
            'caption' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate model type
        $validTypes = ['disaster', 'disaster_report', 'disaster_victim', 'disaster_aid'];
        if (!in_array($modelType, $validTypes)) {
            return response()->json([
                'message' => 'Invalid model type. Must be one of: ' . implode(', ', $validTypes)
            ], 400);
        }

        // Validate that the model exists
        $modelClass = $this->getModelClass($modelType);
        $model = $modelClass::find($modelId);
        
        if (!$model) {
            return response()->json([
                'message' => ucfirst(str_replace('_', ' ', $modelType)) . ' not found.'
            ], 404);
        }

        // Normalize to an array of files
        $files = [];
        if ($request->hasFile('images')) {
            $files = $request->file('images');
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        }

        if (empty($files)) {
            return response()->json([
                'message' => 'No image files provided. Use `images[]` for multiple or `image` for single file.'
            ], 422);
        }

        $saved = [];
        foreach ($files as $file) {
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('pictures/' . $modelType, $fileName, 'public');

            $picture = Picture::create([
                'foreign_id' => $modelId,
                'type' => PictureTypeEnum::from($modelType),
                'caption' => $request->caption,
                'file_path' => $filePath,
                'mine_type' => $file->getMimeType(),
                'alt_text' => $request->alt_text,
            ]);

            $saved[] = [
                'id' => $picture->id,
                'foreign_id' => $picture->foreign_id,
                'type' => $picture->type->value,
                'caption' => $picture->caption,
                'file_path' => $picture->file_path,
                'url' => Storage::url($picture->file_path),
                'mine_type' => $picture->mine_type,
                'alt_text' => $picture->alt_text,
                'created_at' => $picture->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json([
            'message' => 'Images uploaded successfully.',
            'data' => $saved
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/pictures/{modelType}/{modelId}",
     *     summary="Get images for model",
     *     description="Get all images associated with a specific model",
     *     tags={"Pictures"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="modelType",
     *         in="path",
     *         description="Model type",
     *         required=true,
     *         @OA\Schema(type="string", enum={"disaster","disaster_report","disaster_victim","disaster_aid"})
     *     ),
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         description="Model ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Images retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid model type",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid model type. Must be one of: disaster, disaster_report, disaster_victim, disaster_aid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Model not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Disaster not found.")
     *         )
     *     )
     * )
     */
    public function getImages(Request $request, $modelType, $modelId)
    {
        // Validate model type
        $validTypes = ['disaster', 'disaster_report', 'disaster_victim', 'disaster_aid'];
        if (!in_array($modelType, $validTypes)) {
            return response()->json([
                'message' => 'Invalid model type. Must be one of: ' . implode(', ', $validTypes)
            ], 400);
        }

        // Validate that the model exists
        $modelClass = $this->getModelClass($modelType);
        $model = $modelClass::find($modelId);
        
        if (!$model) {
            return response()->json([
                'message' => ucfirst(str_replace('_', ' ', $modelType)) . ' not found.'
            ], 404);
        }

        $pictures = Picture::where('foreign_id', $modelId)
            ->where('type', PictureTypeEnum::from($modelType))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $pictures->map(function ($picture) {
                return [
                    'id' => $picture->id,
                    'foreign_id' => $picture->foreign_id,
                    'type' => $picture->type->value,
                    'caption' => $picture->caption,
                    'file_path' => $picture->file_path,
                    'url' => Storage::url($picture->file_path),
                    'mine_type' => $picture->mine_type,
                    'alt_text' => $picture->alt_text,
                    'created_at' => $picture->created_at->format('Y-m-d H:i:s'),
                ];
            })
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/pictures/{modelType}/{modelId}/{imageId}",
     *     summary="Get specific image",
     *     description="Get details of a specific image",
     *     tags={"Pictures"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="modelType",
     *         in="path",
     *         description="Model type",
     *         required=true,
     *         @OA\Schema(type="string", enum={"disaster","disaster_report","disaster_victim","disaster_aid"})
     *     ),
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         description="Model ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="imageId",
     *         in="path",
     *         description="Image ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="foreign_id", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="caption", type="string"),
     *                 @OA\Property(property="file_path", type="string"),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="mine_type", type="string"),
     *                 @OA\Property(property="alt_text", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid model type",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid model type. Must be one of: disaster, disaster_report, disaster_victim, disaster_aid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Image not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image not found.")
     *         )
     *     )
     * )
     */
    public function getImage(Request $request, $modelType, $modelId, $imageId)
    {
        // Validate model type
        $validTypes = ['disaster', 'disaster_report', 'disaster_victim', 'disaster_aid'];
        if (!in_array($modelType, $validTypes)) {
            return response()->json([
                'message' => 'Invalid model type. Must be one of: ' . implode(', ', $validTypes)
            ], 400);
        }

        $picture = Picture::where('foreign_id', $modelId)
            ->where('id', $imageId)
            ->where('type', PictureTypeEnum::from($modelType))
            ->first();

        if (!$picture) {
            return response()->json([
                'message' => 'Image not found.'
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $picture->id,
                'foreign_id' => $picture->foreign_id,
                'type' => $picture->type->value,
                'caption' => $picture->caption,
                'file_path' => $picture->file_path,
                'url' => Storage::url($picture->file_path),
                'mine_type' => $picture->mine_type,
                'alt_text' => $picture->alt_text,
                'created_at' => $picture->created_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/pictures/{modelType}/{modelId}/{imageId}",
     *     summary="Update image details",
     *     description="Update metadata for a specific image",
     *     tags={"Pictures"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="modelType",
     *         in="path",
     *         description="Model type",
     *         required=true,
     *         @OA\Schema(type="string", enum={"disaster","disaster_report","disaster_victim","disaster_aid"})
     *     ),
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         description="Model ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="imageId",
     *         in="path",
     *         description="Image ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="caption", type="string", example="Updated disaster scene photo"),
     *             @OA\Property(property="alt_text", type="string", example="Updated photo showing earthquake damage")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image updated successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid model type",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid model type. Must be one of: disaster, disaster_report, disaster_victim, disaster_aid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Image not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image not found.")
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
    public function updateImage(Request $request, $modelType, $modelId, $imageId)
    {
        $validator = Validator::make($request->all(), [
            'caption' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate model type
        $validTypes = ['disaster', 'disaster_report', 'disaster_victim', 'disaster_aid'];
        if (!in_array($modelType, $validTypes)) {
            return response()->json([
                'message' => 'Invalid model type. Must be one of: ' . implode(', ', $validTypes)
            ], 400);
        }

        $picture = Picture::where('foreign_id', $modelId)
            ->where('id', $imageId)
            ->where('type', PictureTypeEnum::from($modelType))
            ->first();

        if (!$picture) {
            return response()->json([
                'message' => 'Image not found.'
            ], 404);
        }

        $picture->update($request->only(['caption', 'alt_text']));

        return response()->json([
            'message' => 'Image updated successfully.',
            'data' => [
                'id' => $picture->id,
                'foreign_id' => $picture->foreign_id,
                'type' => $picture->type->value,
                'caption' => $picture->caption,
                'file_path' => $picture->file_path,
                'url' => Storage::url($picture->file_path),
                'mine_type' => $picture->mine_type,
                'alt_text' => $picture->alt_text,
                'updated_at' => $picture->updated_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/pictures/{modelType}/{modelId}/{imageId}",
     *     summary="Delete image",
     *     description="Delete a specific image and its file from storage",
     *     tags={"Pictures"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="modelType",
     *         in="path",
     *         description="Model type",
     *         required=true,
     *         @OA\Schema(type="string", enum={"disaster","disaster_report","disaster_victim","disaster_aid"})
     *     ),
     *     @OA\Parameter(
     *         name="modelId",
     *         in="path",
     *         description="Model ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659")
     *     ),
     *     @OA\Parameter(
     *         name="imageId",
     *         in="path",
     *         description="Image ID",
     *         required=true,
     *         @OA\Schema(type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f660")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid model type",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid model type. Must be one of: disaster, disaster_report, disaster_victim, disaster_aid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Image not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Image not found.")
     *         )
     *     )
     * )
     */
    public function deleteImage(Request $request, $modelType, $modelId, $imageId)
    {
        // Validate model type
        $validTypes = ['disaster', 'disaster_report', 'disaster_victim', 'disaster_aid'];
        if (!in_array($modelType, $validTypes)) {
            return response()->json([
                'message' => 'Invalid model type. Must be one of: ' . implode(', ', $validTypes)
            ], 400);
        }

        $picture = Picture::where('foreign_id', $modelId)
            ->where('id', $imageId)
            ->where('type', PictureTypeEnum::from($modelType))
            ->first();

        if (!$picture) {
            return response()->json([
                'message' => 'Image not found.'
            ], 404);
        }

        // Delete file from storage
        if (Storage::disk('public')->exists($picture->file_path)) {
            Storage::disk('public')->delete($picture->file_path);
        }

        $picture->delete();

        return response()->json([
            'message' => 'Image deleted successfully.'
        ], 200);
    }

    /**
     * Get model class based on type
     */
    private function getModelClass($modelType)
    {
        $modelMap = [
            'disaster' => \App\Models\Disaster::class,
            'disaster_report' => \App\Models\DisasterReport::class,
            'disaster_victim' => \App\Models\DisasterVictim::class,
            'disaster_aid' => \App\Models\DisasterAid::class,
        ];

        return $modelMap[$modelType];
    }
}
