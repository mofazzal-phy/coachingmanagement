<?php

namespace Modules\Academic\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\app\Models\Room;
use Modules\Core\app\Http\Controllers\BaseApiController;

class RoomController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Room::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('building', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('building')) {
            $query->where('building', $request->building);
        }

        $rooms = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return $this->success($rooms, 'Rooms retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:rooms,code',
            'capacity' => 'nullable|integer|min:1',
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:50',
            'has_multimedia' => 'boolean',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $room = Room::create($validated);

        return $this->success($room, 'Room created successfully', 201);
    }

    public function show(string $id): JsonResponse
    {
        $room = Room::find($id);
        if (!$room) return $this->notFound();
        return $this->success($room, 'Room retrieved successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $room = Room::find($id);
        if (!$room) return $this->notFound();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50|unique:rooms,code,' . $id,
            'capacity' => 'nullable|integer|min:1',
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:50',
            'has_multimedia' => 'boolean',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        $room->update($validated);

        return $this->success($room, 'Room updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        $room = Room::find($id);
        if (!$room) return $this->notFound();
        $room->delete();

        return $this->noContent();
    }

    public function listAll(): JsonResponse
    {
        $rooms = Room::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'building']);

        return $this->success($rooms, 'Rooms list retrieved successfully');
    }
}
