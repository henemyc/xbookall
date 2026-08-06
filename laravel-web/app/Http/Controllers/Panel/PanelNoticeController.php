<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\NoticeBoard;
use Illuminate\Http\Request;

class PanelNoticeController extends BaseController
{
    /**
     * List notices
     */
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $notices = NoticeBoard::whereIn('parent_id', $parentIds)
            ->orderBy('created_at', 'desc')
            ->get();   // Use collection for full AJAX

        return view('panel.notices.index', compact('notices'));
    }

    /**
     * Store notice
     */
    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $notice = NoticeBoard::create([
            'title' => $request->title,
            'description' => $request->description ?? '',
            'parent_id' => $pid,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Notice created successfully',
                'notice' => [
                    'id' => $notice->id,
                    'title' => $notice->title,
                    'description' => $notice->description,
                    'created_at' => $notice->created_at->format('d M Y'),
                ]
            ]);
        }

        return redirect()->route('panel.notices.index')->with('success', 'Notice created');
    }

    /**
     * Edit notice
     */
    public function edit(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $notice = NoticeBoard::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        return view('panel.notices.edit', compact('notice'));
    }

    /**
     * Update notice (AJAX supported)
     */
    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $notice = NoticeBoard::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $notice->update([
            'title' => $request->title,
            'description' => $request->description ?? '',
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Notice updated successfully',
                'notice' => [
                    'id' => $notice->id,
                    'title' => $notice->title,
                    'description' => $notice->description,
                    'created_at' => $notice->created_at->format('d M Y'),
                ]
            ]);
        }

        return redirect()->route('panel.notices.index')->with('success', 'Notice updated');
    }

    /**
     * Delete notice (AJAX supported)
     */
    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $notice = NoticeBoard::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();
        $notice->delete();

        $isAjax = request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json(['success' => true, 'message' => 'Notice deleted']);
        }

        return redirect()->route('panel.notices.index')->with('success', 'Notice deleted');
    }
}
