<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Store;
use App\Models\ShiftStore;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PresenceController extends Controller
{
    /**
     * Get attendance history for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);
        
        $activeTenantId = $request->active_tenant_id ?? $user->tenant_id;
        
        $attendances = Attendance::where('created_by_id', $userKey)
            ->whereHas('store', function ($query) use ($activeTenantId) {
                // Use withoutGlobalScope if necessary, but store should belong to tenant
                $query->withoutGlobalScope('tenant')->where('tenant_id', $activeTenantId);
            })
            ->with([
                'store:id,name,nickname,address,phone,radius,latitude,longitude',
                'shiftStore:id,name,shift_start_time,shift_end_time',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        $attendances->setCollection(
            $attendances->getCollection()->map(fn ($attendance) => $this->transformAttendance($attendance))
        );

        return response()->json([
            'success' => true,
            ...$this->formatPaginated($attendances),
        ]);
    }

    /**
     * Check-in attendance (create attendance record)
     */
    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);

        Log::info('Attendance check-in request received', [
            'user_id' => $user?->id,
            'user_uuid' => $user?->uuid,
            'user_tenant_id' => $user?->tenant_id,
            'active_tenant_id' => $request->active_tenant_id ?? null,
            'store_id' => $request->input('store_id'),
            'shift_store_id' => $request->input('shift_store_id'),
            'has_photo_file' => $request->hasFile('photo'),
            'photo_is_valid' => $request->file('photo')?->isValid(),
            'photo_mime' => $request->file('photo')?->getMimeType(),
            'photo_size' => $request->file('photo')?->getSize(),
        ]);

        $validator = Validator::make($request->all(), [
            'store_id' => 'required|exists:stores,id',
            'shift_store_id' => 'required|exists:shift_stores,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            Log::warning('Attendance check-in validation failed', [
                'user_id' => $user?->id,
                'user_uuid' => $user?->uuid,
                'active_tenant_id' => $request->active_tenant_id ?? null,
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate store belongs to active tenant
        $activeTenantId = $request->active_tenant_id ?? $user->tenant_id;

        // Check if user has approved leave for today
        $today = now()->format('Y-m-d');
        $hasApprovedLeave = LeaveRequest::where('user_id', $userKey)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->exists();

        if ($hasApprovedLeave) {
            Log::info('Attendance check-in blocked by approved leave', [
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'active_tenant_id' => $activeTenantId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Anda sedang dalam masa cuti yang disetujui. Tidak dapat melakukan check-in.'
            ], 403);
        }

        $store = Store::withoutGlobalScope('tenant')
            ->where('id', $request->store_id)
            ->where('tenant_id', $activeTenantId)
            ->first();
        
        if (!$store) {
            Log::warning('Attendance check-in store not found for active tenant', [
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'active_tenant_id' => $activeTenantId,
                'store_id' => $request->store_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $shift = ShiftStore::withoutGlobalScope('tenant')
            ->where('id', $request->shift_store_id)
            ->where('tenant_id', $activeTenantId)
            ->first();

        if (!$shift) {
            Log::warning('Attendance check-in shift not found for active tenant', [
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'active_tenant_id' => $activeTenantId,
                'shift_store_id' => $request->shift_store_id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Shift not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Handle photo upload
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('attendances', 'public');
            }

            $attendance = Attendance::create([
                'store_id' => $request->store_id,
                'check_in_store_id' => $request->store_id,
                'shift_store_id' => $request->shift_store_id,
                'status' => 'pending',
                'image_in' => $photoPath,
                'check_in' => now(),
                'latitude_in' => $request->latitude,
                'longitude_in' => $request->longitude,
                'created_by_id' => $userKey,
            ]);

            // Calculate and save was_late after creating attendance
            $wasLate = $this->calculateWasLate($attendance->fresh(['shiftStore']));
            $attendance->update(['was_late' => $wasLate]);

            DB::commit();

            $payload = $attendance->load(['store:id,name,nickname,address,phone,radius,latitude,longitude', 'shiftStore:id,name,shift_start_time,shift_end_time']);
            Log::info('Attendance check-in succeeded', [
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'active_tenant_id' => $activeTenantId,
                'attendance_id' => $attendance->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Check-in berhasil',
                'attendance' => $this->transformAttendance($payload),
                'data' => $this->transformAttendance($payload),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Attendance check-in failed', [
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'active_tenant_id' => $activeTenantId,
                'store_id' => $request->store_id,
                'shift_store_id' => $request->shift_store_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check in: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check-out attendance (update attendance record)
     */
    public function checkOut(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);
        
        $request->validate([
            'attendance_id' => 'required|exists:attendances,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'accuracy' => 'nullable|numeric',
            'store_id' => 'required|exists:stores,id',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $attendance = Attendance::where('id', $request->attendance_id)
            ->where('created_by_id', $userKey)
            ->whereNull('check_out')
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found or already checked out'
            ], 404);
        }

        // Allow check-out at any valid store
        $store = Store::withoutGlobalScope('tenant')->where('id', $request->store_id)->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        try {
            // Handle photo upload
            $photoPath = $attendance->image_out;
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($photoPath) {
                    Storage::disk('public')->delete($photoPath);
                }
                $photoPath = $request->file('photo')->store('attendances', 'public');
            }

            $attendance->update([
                'image_out' => $photoPath,
                'check_out' => now(),
                'latitude_out' => $request->latitude,
                'longitude_out' => $request->longitude,
                'check_out_store_id' => $request->store_id,
            ]);

            $payload = $attendance->load(['store:id,name,nickname,address,phone,radius,latitude,longitude', 'shiftStore:id,name,shift_start_time,shift_end_time']);
            return response()->json([
                'success' => true,
                'message' => 'Check-out berhasil',
                'attendance' => $this->transformAttendance($payload),
                'data' => $this->transformAttendance($payload),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check out: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance history with filters
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);
        
        $activeTenantId = $request->active_tenant_id ?? $user->tenant_id;

        $query = Attendance::where('created_by_id', $userKey)
            ->whereHas('store', function ($q) use ($activeTenantId) {
                $q->withoutGlobalScope('tenant')->where('tenant_id', $activeTenantId);
            })
            ->with(['store:id,name,nickname,address,phone,radius,latitude,longitude']);
        
        // Apply filters
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        
        if ($request->has('start_date')) {
            $query->whereDate('check_in', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->whereDate('check_in', '<=', $request->end_date);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $attendances = $query->orderBy('check_in', 'desc')
            ->paginate($request->get('per_page', 10));

        $attendances->setCollection(
            $attendances->getCollection()->map(fn ($attendance) => $this->transformAttendance($attendance))
        );

        return response()->json([
            'success' => true,
            ...$this->formatPaginated($attendances),
        ]);
    }

    /**
     * Get attendance details
     */
    public function show(Request $request, string $attendanceId): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);
        
        $attendance = Attendance::where('id', $attendanceId)
            ->where('created_by_id', $userKey)
            ->with(['store:id,name,nickname,address,phone,radius,latitude,longitude', 'shiftStore:id,name,shift_start_time,shift_end_time'])
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformAttendance($attendance)
        ]);
    }

    /**
     * Get leave requests for the authenticated user
     */
    public function leaveRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);
        
        $activeTenantId = $request->active_tenant_id ?? $user->tenant_id;
        
        $query = LeaveRequest::where('user_id', $userKey)
            ->where('tenant_id', $activeTenantId);
        
        // Apply status filter if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $leaveRequests = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $leaveRequests
        ]);
    }

    /**
     * Submit leave request
     */
    public function submitLeaveRequest(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);
        
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'leave_type' => 'required|in:annual,sick,emergency',
            'reason' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);
            $duration = $startDate->diffInDays($endDate) + 1;

            // Create leave request
            $activeTenantId = $request->active_tenant_id ?? $user->tenant_id;

            $leaveRequest = LeaveRequest::create([
                'user_id' => $userKey,
                'tenant_id' => $activeTenantId,
                'type' => $request->leave_type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'duration' => $duration,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $leaveRequest,
                'message' => 'Leave request submitted successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Leave request submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'user_uuid' => $user->uuid,
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit leave request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific leave request details
     */
    public function showLeaveRequest(Request $request, string $leaveRequestId): JsonResponse
    {
        $user = $request->user();
        $userKey = $this->userKey($user);
        
        $leaveRequest = LeaveRequest::where('id', $leaveRequestId)
            ->where('user_id', $userKey)
            ->first();

        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $leaveRequest
        ]);
    }

    /**
     * Normalize attendance payload for mobile apps.
     */
    private function transformAttendance(Attendance $attendance): array
    {
        $store = $attendance->store;
        $shift = $attendance->shiftStore;

        return [
            'id' => $attendance->id,
            'store_id' => $attendance->store_id,
            'check_in_store_id' => $attendance->check_in_store_id,
            'check_out_store_id' => $attendance->check_out_store_id,
            'store' => $store ? [
                'id' => $store->id,
                'name' => $store->name,
                'nickname' => $store->nickname,
                'address' => $store->address,
                'phone' => $store->phone,
                'radius' => $store->radius,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
            ] : null,
            'shift_store_id' => $attendance->shift_store_id,
            'shift_store' => $shift ? [
                'id' => $shift->id,
                'name' => $shift->name,
                'shift_start_time' => optional($shift->shift_start_time)->toIso8601String(),
                'shift_end_time' => optional($shift->shift_end_time)->toIso8601String(),
                'duration' => $shift->duration,
            ] : null,
            'status' => $attendance->status,
            'image_in' => $this->resolveImageUrl($attendance->image_in),
            'check_in' => optional($attendance->check_in)->toIso8601String(),
            'location_in' => ($attendance->latitude_in !== null && $attendance->longitude_in !== null)
                ? [
                    'latitude' => (float) $attendance->latitude_in,
                    'longitude' => (float) $attendance->longitude_in,
                ]
                : null,
            'image_out' => $this->resolveImageUrl($attendance->image_out),
            'check_out' => optional($attendance->check_out)->toIso8601String(),
            'location_out' => ($attendance->latitude_out !== null && $attendance->longitude_out !== null)
                ? [
                    'latitude' => (float) $attendance->latitude_out,
                    'longitude' => (float) $attendance->longitude_out,
                ]
                : null,
            'auto_checked_out_at' => null,
            'created_at' => optional($attendance->created_at)->toIso8601String(),
            'updated_at' => optional($attendance->updated_at)->toIso8601String(),
            'was_late' => $this->calculateWasLate($attendance),
            'was_early_leave' => $this->calculateWasEarlyLeave($attendance),
        ];
    }

    /**
     * Attendance and leave tables in sagansa_2025 reference users by UUID.
     */
    private function userKey($user): string
    {
        return (string) ($user->uuid ?: $user->id);
    }

    /**
     * Calculate if check-in was late compared to shift start time
     */
    private function calculateWasLate(Attendance $attendance): bool
    {
        // If no check-in, can't be late
        if (!$attendance->check_in) {
            return false;
        }

        // If no shift assigned, can't determine lateness
        $shift = $attendance->shiftStore;
        if (!$shift || !$shift->shift_start_time) {
            return false;
        }

        // Compare check-in time with shift start time
        // Convert check-in to Asia/Jakarta as shift times are in local time
        $checkInTime = $attendance->check_in->setTimezone('Asia/Jakarta')->format('H:i:s');
        $shiftStartTime = $shift->shift_start_time instanceof \DateTime 
            ? $shift->shift_start_time->format('H:i:s')
            : $shift->shift_start_time;

        return $checkInTime > $shiftStartTime;
    }

    /**
     * Calculate if check-out was early compared to shift end time
     */
    private function calculateWasEarlyLeave(Attendance $attendance): bool
    {
        // If no check-out, can't be early leave
        if (!$attendance->check_out) {
            return false;
        }

        // If no shift assigned, can't determine early leave
        $shift = $attendance->shiftStore;
        if (!$shift || !$shift->shift_end_time) {
            return false;
        }

        // Compare check-out time with shift end time
        // Convert check-out to Asia/Jakarta as shift times are in local time
        $checkOutTime = $attendance->check_out->setTimezone('Asia/Jakarta')->format('H:i:s');
        $shiftEndTime = $shift->shift_end_time instanceof \DateTime 
            ? $shift->shift_end_time->format('H:i:s')
            : $shift->shift_end_time;

        return $checkOutTime < $shiftEndTime;
    }

    /**
     * Resolve an image path to a full URL using the img service.
     */
    private function resolveImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Already a full URL
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Image stored in the dedicated img service
        $imgBaseUrl = rtrim(env('IMG_SERVICE_URL', 'https://img.sagansa.id'), '/');

        return "{$imgBaseUrl}/storage/{$path}";
    }

    /**
     * Convert paginator to API-friendly pagination structure.
     */
    private function formatPaginated($paginator): array
    {
        return [
            'data' => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
