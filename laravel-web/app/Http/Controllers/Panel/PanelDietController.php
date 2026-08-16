<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\DietTemplate;
use App\Models\DietTemplateMeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// D8: Gym Owner web-panel diet template management.
class PanelDietController extends BaseController
{
    public function index()
    {
        $this->requireGymOwner();
        $templates = DietTemplate::where('parent_id', $this->getParentId())->with('meals')->latest()->get();
        return view('panel.diets.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $this->requireGymOwner();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'goal' => 'nullable|string|max:120',
            'diet_type' => 'nullable|string|max:60',
            'daily_calories' => 'nullable|integer|min:0',
            'protein_target' => 'nullable|integer|min:0',
            'water_target' => 'nullable|integer|min:0',
            'general_instructions' => 'nullable|string',
            'meals' => 'required|array|min:1',
            'meals.*.meal_name' => 'required|string|max:120',
        ]);
        $template = DB::transaction(function () use ($data) {
            $template = DietTemplate::create([
                'parent_id' => $this->getParentId(), 'created_by_user_id' => auth()->id(),
                'created_by_type' => auth()->user()->type, 'title' => $data['title'],
                'goal' => $data['goal'] ?? null, 'diet_type' => $data['diet_type'] ?? null,
                'daily_calories' => $data['daily_calories'] ?? null, 'protein_target' => $data['protein_target'] ?? null,
                'water_target' => $data['water_target'] ?? null, 'general_instructions' => $data['general_instructions'] ?? null,
                'is_shared' => true, 'is_active' => true,
            ]);
            $this->saveMeals($template, $data['meals']);
            return $template;
        });
        return redirect()->route('panel.diets.index')->with('success', 'Diet template created');
    }

    public function update(Request $request, int $id)
    {
        $this->requireGymOwner();
        $template = DietTemplate::where('id', $id)->where('parent_id', $this->getParentId())->firstOrFail();
        $data = $request->validate([
            'title' => 'required|string|max:255', 'goal' => 'nullable|string|max:120',
            'diet_type' => 'nullable|string|max:60', 'daily_calories' => 'nullable|integer|min:0',
            'protein_target' => 'nullable|integer|min:0', 'water_target' => 'nullable|integer|min:0',
            'general_instructions' => 'nullable|string', 'meals' => 'required|array|min:1',
            'meals.*.meal_name' => 'required|string|max:120',
        ]);
        DB::transaction(function () use ($template, $data) {
            $template->update([
                'title' => $data['title'], 'goal' => $data['goal'] ?? null, 'diet_type' => $data['diet_type'] ?? null,
                'daily_calories' => $data['daily_calories'] ?? null, 'protein_target' => $data['protein_target'] ?? null,
                'water_target' => $data['water_target'] ?? null, 'general_instructions' => $data['general_instructions'] ?? null,
            ]);
            $template->meals()->delete();
            $this->saveMeals($template, $data['meals']);
        });
        $template->load('meals');
        if ($request->expectsJson() || $request->ajax()) return response()->json(['success' => true, 'message' => 'Diet template updated', 'template' => $template]);
        return redirect()->route('panel.diets.index')->with('success', 'Diet template updated');
    }

    public function destroy(Request $request, int $id)
    {
        $this->requireGymOwner();
        $template = DietTemplate::where('id', $id)->where('parent_id', $this->getParentId())->firstOrFail();
        $template->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Diet template deleted']);
        }

        return redirect()->route('panel.diets.index')->with('success', 'Diet template deleted');
    }

    private function saveMeals(DietTemplate $template, array $meals): void
    {
        foreach ($meals as $index => $meal) {
            DietTemplateMeal::create([
                'diet_template_id' => $template->id, 'sort_order' => $index,
                'meal_time' => $meal['meal_time'] ?? null, 'meal_name' => $meal['meal_name'],
                'food_items' => $meal['food_items'] ?? null, 'quantity' => $meal['quantity'] ?? null,
                'notes' => $meal['notes'] ?? null,
            ]);
        }
    }

    private function requireGymOwner(): void
    {
        $user = auth()->user();
        if (!$user || !in_array($user->type, ['admin', 'owner'], true)) abort(403, 'Only gym owner can manage diet templates');
    }
}
