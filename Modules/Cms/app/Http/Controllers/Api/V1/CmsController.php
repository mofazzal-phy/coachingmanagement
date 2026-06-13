<?php

namespace Modules\Cms\app\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Cms\app\Models\Slider;
use Modules\Cms\app\Models\Event;
use Modules\Core\app\Http\Controllers\BaseApiController;

class CmsController extends BaseApiController
{
    // === Sliders ===
    public function sliders(): JsonResponse
    {
        return $this->collectionResponse(Slider::where('status', 'active')->orderBy('sort_order')->get());
    }

    public function storeSlider(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|string',
            'link' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
        ]);
        return $this->created(Slider::create($validated));
    }

    public function updateSlider(Request $request, string $id): JsonResponse
    {
        $slider = Slider::find($id);
        if (!$slider) return $this->notFound();
        $slider->update($request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'sometimes|string',
            'link' => 'nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,inactive',
        ]));
        return $this->success($slider->fresh());
    }

    public function showSlider(string $id): JsonResponse
    {
        $slider = Slider::find($id);
        if (!$slider) return $this->notFound();
        return $this->success($slider);
    }

    public function destroySlider(string $id): JsonResponse
    {
        $slider = Slider::find($id);
        if (!$slider) return $this->notFound();
        $slider->delete();
        return $this->noContent();
    }

    // === Events ===
    public function events(Request $request): JsonResponse
    {
        $perPage = $this->getPerPage($request);
        return $this->paginatedResponse(
            Event::search($request->search)->filter($request->only(['status']))->orderBy('event_date')->paginate($perPage)
        );
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'nullable|string',
            'event_date' => 'required|date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'image' => 'nullable|string',
        ]);
        return $this->created(Event::create($validated));
    }

    public function updateEvent(Request $request, string $id): JsonResponse
    {
        $event = Event::find($id);
        if (!$event) return $this->notFound();
        $event->update($request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'nullable|string',
            'event_date' => 'sometimes|date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'image' => 'nullable|string',
            'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled',
        ]));
        return $this->success($event->fresh());
    }

    public function showEvent(string $id): JsonResponse
    {
        $event = Event::find($id);
        if (!$event) return $this->notFound();
        return $this->success($event);
    }

    public function destroyEvent(string $id): JsonResponse
    {
        $event = Event::find($id);
        if (!$event) return $this->notFound();
        $event->delete();
        return $this->noContent();
    }

}
