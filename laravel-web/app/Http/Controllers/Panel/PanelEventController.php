<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Event;
use Illuminate\Http\Request;

class PanelEventController extends BaseController
{
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $events = Event::whereIn('parent_id', $parentIds)
            ->orderBy('start_date', 'desc')
            ->get();

        return view('panel.events.index', compact('events'));
    }

    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $event = Event::create([
            'parent_id' => $pid,
            'title' => $request->title,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description ?? '',
            'status' => 1,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start_date' => $event->start_date->format('Y-m-d'),
                    'end_date' => $event->end_date->format('Y-m-d'),
                    'description' => $event->description,
                ]
            ]);
        }

        return redirect()->route('panel.events.index')->with('success', 'Event created');
    }

    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $event = Event::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $event->update([
            'title' => $request->title,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description ?? '',
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start_date' => $event->start_date->format('Y-m-d'),
                    'end_date' => $event->end_date->format('Y-m-d'),
                    'description' => $event->description,
                ]
            ]);
        }

        return redirect()->route('panel.events.index')->with('success', 'Event updated');
    }

    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $event = Event::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();
        $event->delete();

        $isAjax = request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json(['success' => true, 'message' => 'Event deleted']);
        }

        return redirect()->route('panel.events.index')->with('success', 'Event deleted');
    }
}
