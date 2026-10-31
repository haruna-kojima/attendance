<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AttendanceRecordController extends Controller
{
    public function index(IndexAttendanceRecordRequest $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);

        $query = Attendance::with('user', 'breaksLogs');

        $query->when($request->filled('user_id'), function ($q) use ($request) {
            return $q->where('user_id', $request->input('user_id'));
        })->when($request->filled('date'), function ($q) use ($request) {
            return $q->where('date', $request->input('date'));
        })->when($request->filled('month'), function ($q) use ($request) {
            return $q->where('date', 'like', $request->input('month') . '%');
        });

        $paginator = $query->latest('date')->paginate($perPage);

        return response()->json([
            'data' => AttendanceRecordResource::collection($paginator->items()),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from'         => $paginator->firstItem(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'to'           => $paginator->lastItem(),
                'total'        => $paginator->total(),
            ]
        ], 200);
    }

    public function show($id): JsonResponse
    {
        $record = Attendance::with(['user', 'breaksLogs', 'attendanceCorrections'])->find($id);

        if (!$record) {
            return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
        }

        return response()->json(['data' => new AttendanceRecordResource($record)], 200);
    }

    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $dateStr = $request->input('date');

        $record = Attendance::create([
            'user_id' => auth()->id(),
            'date' => $dateStr,
            'clock_in_at' => Carbon::parse($dateStr . ' ' . $request->input('clock_in')),
            'clock_out_at' => $request->input('clock_out') ? Carbon::parse($dateStr . ' ' . $request->input('clock_out')) : null,
            'remarks' => $request->input('comment'),
            'status' => $request->input('clock_out') ? '退勤済' : '出勤中',
        ]);

        $record->load(['user', 'breaksLogs']);

        return response()->json(['data' => new AttendanceRecordResource($record)], 201);
    }

    public function update(UpdateAttendanceRecordRequest $request, $id): JsonResponse
    {
        $record = Attendance::find($id);
        if (!$record) {
            return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
        }

        $this->authorize('update', $record);

        $dateStr = $request->input('date', $record->date);
        $updateData = [];

        if ($request->has('date')) $updateData['date'] = $dateStr;
        if ($request->has('clock_in')) $updateData['clock_in_at'] = Carbon::parse($dateStr . ' ' . $request->input('clock_in'));
        if ($request->has('clock_out')) {
            $updateData['clock_out_at'] = $request->input('clock_out') ? Carbon::parse($dateStr . ' ' . $request->input('clock_out')) : null;
            $updateData['status'] = $request->input('clock_out') ? '退勤済' : '出勤中';
        }
        if ($request->has('comment')) $updateData['remarks'] = $request->input('comment');

        $record->update($updateData);
        $record->load(['user', 'breaksLogs']);

        return response()->json(['data' => new AttendanceRecordResource($record)], 200);
    }

    public function destroy($id): JsonResponse
    {
        $record = Attendance::find($id);
        if (!$record) {
            return response()->json(['error' => '勤怠情報が見つかりませんでした。'], 404);
        }

        $this->authorize('destroy', $record);

        $record->delete();

        return response()->json(null, 204);
    }
}
