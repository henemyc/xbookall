<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventController extends BaseController
{
    // Phase 3: event actions below require their exact event permission.
    /**
     * List events
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('events.view')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $events = Event::whereIn('parent_id', $parentIds)
            ->with('eventType')
            ->orderBy('start_date', 'desc')
            ->get();

        return $this->success(['events' => $events]);
    }

    /**
     * Create event
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('events.create')) {
            return $this->error('Permission denied', 403);
        }

        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $event = Event::create([
            'event_type_id' => $request->event_type_id,
            'parent_id' => $pid,
            'title' => $request->title,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description ?? '',
            'status' => $request->status ?? 1,
        ]);

        return $this->success([
            'id' => $event->id,
            'event' => $event,
        ], 'Event created', 201);
    }

    /**
     * Update event
     */
    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('events.edit')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $event = Event::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $event->update([
            'event_type_id' => $request->event_type_id ?? $event->event_type_id,
            'title' => $request->title ?? $event->title,
            'start_date' => $request->start_date ?? $event->start_date,
            'end_date' => $request->end_date ?? $event->end_date,
            'description' => $request->description ?? $event->description,
            'status' => $request->status ?? $event->status,
        ]);

        return $this->success([], 'Event updated');
    }

    /**
     * Delete event
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('events.delete')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $event = Event::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $event->delete();

        return $this->success([], 'Event deleted');
    }

    /**
     * List event types
     */
    public function types(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('events.view')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymAndGlobalParentIds();

        $types = EventType::whereIn('parent_id', $parentIds)
            ->orderBy('name')
            ->get();

        return $this->success(['types' => $types]);
    }

    /**
     * Create event type
     */
    public function storeType(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('events.create')) {
            return $this->error('Permission denied', 403);
        }

        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $type = EventType::create([
            'name' => $request->name,
            'parent_id' => $pid,
        ]);

        return $this->success([
            'id' => $type->id,
            'type' => $type,
        ], 'Event type created', 201);
    }
}
