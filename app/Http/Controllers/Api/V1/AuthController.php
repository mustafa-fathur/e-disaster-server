<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Picture;
use App\Enums\UserTypeEnum;
use App\Enums\UserStatusEnum;
use App\Enums\PictureTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/login",
     *     summary="User login",
     *     description="Authenticate user and return access token. Optionally store FCM token for push notifications.",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="fathur@edisaster.test"),
     *             @OA\Property(property="password", type="string", format="password", example="password", minLength=6),
     *             @OA\Property(property="fcm_token", type="string", description="Firebase Cloud Messaging token for push notifications", example="dK3jH8f9L2mN4pQ5rS6tU7vW8xY9zA0bC1dE2fG3hI4jK5lM6nO7pQ8rS9tU0vW1xY2zA3bC4dE5f"),
     *             @OA\Property(property="platform", type="string", enum={"android","ios","web"}, description="Device platform", example="android"),
     *             @OA\Property(property="device_id", type="string", maxLength=100, description="Optional device identifier", example="device_12345"),
     *             @OA\Property(property="device_name", type="string", maxLength=100, description="Optional device name", example="Samsung Galaxy S21"),
     *             @OA\Property(property="app_version", type="string", maxLength=20, description="Optional app version", example="1.0.0")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="string", example="0199cfbc-eab1-7262-936e-72f9a6c5f659"),
     *                 @OA\Property(property="name", type="string", example="Fathur"),
     *                 @OA\Property(property="email", type="string", example="fathur@edisaster.test"),
     *                 @OA\Property(property="type", type="string", example="officer"),
     *                 @OA\Property(property="status", type="string", example="active")
     *             ),
     *             @OA\Property(property="token", type="string", example="1|QLeddZ0IkK8cMP1ARfjPnpRpIbuhiMHGIlufQspye1556327")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Account not active",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Account is not active. Please contact administrator.")
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
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'fcm_token' => 'nullable|string|max:255',
            'platform' => 'nullable|in:android,ios,web',
            'device_id' => 'nullable|string|max:100',
            'device_name' => 'nullable|string|max:100',
            'app_version' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }   

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if user is active
        if ($user->status !== UserStatusEnum::ACTIVE) {
            return response()->json([
                'message' => 'Account is not active. Please contact administrator.'
            ], 403);
        }

        // Store or update FCM token if provided
        if ($request->has('fcm_token') && !empty($request->fcm_token)) {
            $platform = $request->input('platform', 'android');
            
            UserDevice::updateOrCreate(
                ['fcm_token' => $request->fcm_token],
                [
                    'user_id' => $user->id,
                    'platform' => $platform,
                    'device_id' => $request->input('device_id'),
                    'device_name' => $request->input('device_name'),
                    'app_version' => $request->input('app_version'),
                    'is_active' => true,
                    'last_used_at' => now(),
                ]
            );
        }

        // Create token
        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type->value,
                'status' => $user->status->value,
            ],
            'token' => $token
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     summary="User registration",
     *     description="Register a new volunteer user (requires admin approval)",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation","nik","phone","address","gender","date_of_birth"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="nik", type="string", example="1234567890123456"),
     *             @OA\Property(property="phone", type="string", example="+6281234567890"),
     *             @OA\Property(property="address", type="string", example="Jl. Example No. 123"),
     *             @OA\Property(property="gender", type="boolean", example=true),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *             @OA\Property(property="reason_to_join", type="string", example="Want to help disaster victims")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration successful. Please wait for admin approval."),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="type", type="string", example="volunteer"),
     *                 @OA\Property(property="status", type="string", example="registered"),
     *                 @OA\Property(property="nik", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="gender", type="boolean"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date")
     *             )
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
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'nik' => 'required|string|max:45|unique:users',
            'phone' => 'required|string|max:45',
            'address' => 'required|string',
            'gender' => 'required|boolean',
            'date_of_birth' => 'required|date|before:today',
            'reason_to_join' => 'nullable|string|max:245',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => UserTypeEnum::VOLUNTEER,
            'status' => UserStatusEnum::REGISTERED,
            'email_verified' => false,
            'nik' => $request->nik,
            'phone' => $request->phone,
            'address' => $request->address,
            'gender' => $request->gender,
            'date_of_birth' => $request->date_of_birth,
            'reason_to_join' => $request->reason_to_join,
            'registered_at' => now(),
        ]);

        return response()->json([
            'message' => 'Registration successful. Please wait for admin approval.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type->value,
                'status' => $user->status->value,
                'nik' => $user->nik,
                'phone' => $user->phone,
                'address' => $user->address,
                'gender' => $user->gender,
                'date_of_birth' => $user->date_of_birth->format('Y-m-d'),
            ]
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="User logout",
     *     description="Logout user and invalidate access token. Optionally delete FCM token to stop receiving push notifications.",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="fcm_token", type="string", description="FCM token to delete from user devices", example="dK3jH8f9L2mN4pQ5rS6tU7vW8xY9zA0bC1dE2fG3hI4jK5lM6nO7pQ8rS9tU0vW1xY2zA3bC4dE5f")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function logout(Request $request)
    {
        $user = auth('sanctum')->user();
        
        // Delete FCM token if provided
        if ($request->has('fcm_token') && !empty($request->fcm_token)) {
            UserDevice::where('fcm_token', $request->fcm_token)
                ->where('user_id', $user->id)
                ->delete();
        }

        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/me",
     *     summary="Get current user profile",
     *     description="Get authenticated user's profile information including profile picture",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="email_verified", type="boolean"),
     *                 @OA\Property(property="nik", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="gender", type="boolean"),
     *                 @OA\Property(property="date_of_birth", type="string", format="date"),
     *                 @OA\Property(property="reason_to_join", type="string"),
     *                 @OA\Property(property="registered_at", type="string", format="date-time"),
     *                 @OA\Property(property="approved_at", type="string", format="date-time"),
     *                 @OA\Property(property="timezone", type="string"),
     *                 @OA\Property(property="location", type="string"),
     *                 @OA\Property(property="lat", type="number"),
     *                 @OA\Property(property="long", type="number"),
     *                 @OA\Property(property="profile_picture", type="object", nullable=true,
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="url", type="string"),
     *                     @OA\Property(property="caption", type="string"),
     *                     @OA\Property(property="alt_text", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = auth('sanctum')->user();
        
        // Get profile picture
        $profilePicture = Picture::where('foreign_id', $user->id)
            ->where('type', PictureTypeEnum::PROFILE)
            ->first();
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type->value,
                'status' => $user->status->value,
                'email_verified' => $user->email_verified,
                'nik' => $user->nik,
                'phone' => $user->phone,
                'address' => $user->address,
                'gender' => $user->gender,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'reason_to_join' => $user->reason_to_join,
                'registered_at' => $user->registered_at?->format('Y-m-d H:i:s'),
                'approved_at' => $user->approved_at?->format('Y-m-d H:i:s'),
                'timezone' => $user->timezone,
                'location' => $user->location,
                'lat' => $user->lat,
                'long' => $user->long,
                'profile_picture' => $profilePicture ? [
                    'id' => $profilePicture->id,
                    'url' => Storage::url($profilePicture->file_path),
                    'caption' => $profilePicture->caption,
                    'alt_text' => $profilePicture->alt_text,
                ] : null,
            ]
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/profile",
     *     summary="Update user profile",
     *     description="Update authenticated user's profile information",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="nik", type="string", example="1234567890123456"),
     *             @OA\Property(property="phone", type="string", example="+6281234567890"),
     *             @OA\Property(property="address", type="string", example="Jl. Example No. 123"),
     *             @OA\Property(property="gender", type="boolean", example=true),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *             @OA\Property(property="reason_to_join", type="string", example="Want to help disaster victims"),
     *             @OA\Property(property="timezone", type="string", example="Asia/Jakarta"),
     *             @OA\Property(property="location", type="string", example="Jakarta"),
     *             @OA\Property(property="lat", type="number", example=-6.2088),
     *             @OA\Property(property="long", type="number", example=106.8456)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="user", type="object")
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
    public function updateProfile(Request $request)
    {
        $user = auth('sanctum')->user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'nik' => 'sometimes|required|string|max:45|unique:users,nik,' . $user->id,
            'phone' => 'sometimes|required|string|max:45',
            'address' => 'sometimes|required|string',
            'gender' => 'sometimes|required|boolean',
            'date_of_birth' => 'sometimes|required|date|before:today',
            'reason_to_join' => 'sometimes|nullable|string|max:245',
            'timezone' => 'sometimes|nullable|string|max:50',
            'location' => 'sometimes|nullable|string|max:255',
            'lat' => 'sometimes|nullable|numeric|between:-90,90',
            'long' => 'sometimes|nullable|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user profile
        $user->update($validator->validated());

        // Get updated profile picture
        $profilePicture = Picture::where('foreign_id', $user->id)
            ->where('type', PictureTypeEnum::PROFILE)
            ->first();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type->value,
                'status' => $user->status->value,
                'email_verified' => $user->email_verified,
                'nik' => $user->nik,
                'phone' => $user->phone,
                'address' => $user->address,
                'gender' => $user->gender,
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'reason_to_join' => $user->reason_to_join,
                'registered_at' => $user->registered_at?->format('Y-m-d H:i:s'),
                'approved_at' => $user->approved_at?->format('Y-m-d H:i:s'),
                'timezone' => $user->timezone,
                'location' => $user->location,
                'lat' => $user->lat,
                'long' => $user->long,
                'profile_picture' => $profilePicture ? [
                    'id' => $profilePicture->id,
                    'url' => Storage::url($profilePicture->file_path),
                    'caption' => $profilePicture->caption,
                    'alt_text' => $profilePicture->alt_text,
                ] : null,
            ]
        ], 200);
    }

    /**
     * @OA\Put(
     *     path="/profile/password",
     *     summary="Update user password",
     *     description="Change authenticated user's password",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed or incorrect current password",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Current password is incorrect")
     *         )
     *     )
     * )
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('sanctum')->user();

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/profile/picture",
     *     summary="Upload profile picture",
     *     description="Upload or update user's profile picture",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"image"},
     *                 @OA\Property(property="image", type="string", format="binary", description="Image file (max 2MB)"),
     *                 @OA\Property(property="caption", type="string", example="My profile picture"),
     *                 @OA\Property(property="alt_text", type="string", example="Profile photo of John Doe")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile picture updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile picture updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="caption", type="string"),
     *                 @OA\Property(property="alt_text", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
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
    public function updateProfilePicture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpeg,png,jpg,gif|max:2048',
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

        // Handle file upload
        $file = $request->file('image');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('pictures/profile', $fileName, 'public');

        // Delete existing profile picture if exists
        $existingPicture = Picture::where('foreign_id', $user->id)
            ->where('type', PictureTypeEnum::PROFILE)
            ->first();

        if ($existingPicture) {
            Storage::disk('public')->delete($existingPicture->file_path);
            $existingPicture->delete();
        }

        // Create new profile picture record
        $picture = Picture::create([
            'foreign_id' => $user->id,
            'type' => PictureTypeEnum::PROFILE,
            'caption' => $request->caption,
            'file_path' => $filePath,
            'mine_type' => $file->getMimeType(),
            'alt_text' => $request->alt_text,
        ]);

        return response()->json([
            'message' => 'Profile picture updated successfully',
            'data' => [
                'id' => $picture->id,
                'url' => Storage::url($picture->file_path),
                'caption' => $picture->caption,
                'alt_text' => $picture->alt_text,
                'created_at' => $picture->created_at->format('Y-m-d H:i:s'),
            ]
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/profile/picture",
     *     summary="Delete profile picture",
     *     description="Delete user's profile picture",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile picture deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile picture deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Profile picture not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile picture not found")
     *         )
     *     )
     * )
     */
    public function deleteProfilePicture(Request $request)
    {
        $user = auth('sanctum')->user();

        $picture = Picture::where('foreign_id', $user->id)
            ->where('type', PictureTypeEnum::PROFILE)
            ->first();

        if (!$picture) {
            return response()->json([
                'message' => 'Profile picture not found'
            ], 404);
        }

        Storage::disk('public')->delete($picture->file_path);
        $picture->delete();

        return response()->json([
            'message' => 'Profile picture deleted successfully'
        ], 200);
    }
}
