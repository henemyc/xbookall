<?php

namespace App\Http\Controllers;

use App\Models\DietTemplate;
use App\Models\DietTemplateMeal;
use App\Models\MemberDiet;
use App\Models\MemberDietMeal;
use App\Models\TraineeDetail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// D3: secure diet templates, member assignment and trainer member-scope API.
class DietController extends BaseController
{
    public function templates(Request $request): JsonResponse
    {
        if (!$this->canUseDiet('diets.view')) return $this->error('Permission denied', 403);

        $actor = $this->currentUser();
        $query = DietTemplate::whereIn('parent_id', $this->getGymParentIds())
            ->where('is_active', true)
            ->with('meals');

        // Trainer-created templates are private unless the Gym Owner marks
        // them shared. Owner/admin and authorized staff can see all templates.
        if ($actor->type === 'trainer') {
            $query->where(function ($q) use ($actor) {
                $q->where('created_by_user_id', $actor->id)
                    ->orWhere('is_shared', true);
            });
        }

        return $this->success(['templates' => $query->latest()->get()]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        if (!$this->canUseDiet('diets.create')) return $this->error('Permission denied', 403);
        $data = $this->validateTemplate($request);
        $actor = $this->currentUser();

        $template = DB::transaction(function () use ($data, $actor) {
            $template = DietTemplate::create([
                'parent_id' => $this->getParentId(),
                'created_by_user_id' => $actor->id,
                'created_by_type' => $actor->type,
                'title' => $data['title'],
                'goal' => $data['goal'] ?? null,
                'diet_type' => $data['diet_type'] ?? null,
                'daily_calories' => $data['daily_calories'] ?? null,
                'protein_target' => $data['protein_target'] ?? null,
                'water_target' => $data['water_target'] ?? null,
                'general_instructions' => $data['general_instructions'] ?? null,
                // Trainers cannot make templates shared themselves.
                'is_shared' => $actor->type === 'trainer' ? false : (bool) ($data['is_shared'] ?? true),
                'is_active' => true,
            ]);
            $this->replaceTemplateMeals($template, $data['meals']);
            return $template->load('meals');
        });

        return $this->success(['template' => $template], 'Diet template created', 201);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        if (!$this->canUseDiet('diets.edit')) return $this->error('Permission denied', 403);
        $template = $this->findTemplate($id);
        if (!$template || !$this->canManageTemplate($template)) return $this->error('Diet template not found', 404);
        $data = $this->validateTemplate($request);
        $actor = $this->currentUser();

        DB::transaction(function () use ($template, $data, $actor) {
            $template->update([
                'title' => $data['title'],
                'goal' => $data['goal'] ?? null,
                'diet_type' => $data['diet_type'] ?? null,
                'daily_calories' => $data['daily_calories'] ?? null,
                'protein_target' => $data['protein_target'] ?? null,
                'water_target' => $data['water_target'] ?? null,
                'general_instructions' => $data['general_instructions'] ?? null,
                'is_shared' => $actor->type === 'trainer' ? $template->is_shared : (bool) ($data['is_shared'] ?? $template->is_shared),
            ]);
            $this->replaceTemplateMeals($template, $data['meals']);
        });

        return $this->success(['template' => $template->fresh()->load('meals')], 'Diet template updated');
    }

    public function destroyTemplate(int $id): JsonResponse
    {
        if (!$this->canUseDiet('diets.delete')) return $this->error('Permission denied', 403);
        $template = $this->findTemplate($id);
        if (!$template || !$this->canManageTemplate($template)) return $this->error('Diet template not found', 404);
        $template->delete();
        return $this->success([], 'Diet template deleted');
    }

    public function memberDiets(Request $request, int $memberId): JsonResponse
    {
        if (!$this->canUseDiet('diets.view')) return $this->error('Permission denied', 403);
        if (!$this->canManageMember($memberId)) return $this->error('You can only manage diets for members assigned to you.', 403);

        $diets = MemberDiet::where('member_id', $memberId)
            ->whereIn('parent_id', $this->getGymParentIds())
            ->with('meals', 'template')
            ->latest()
            ->get();
        return $this->success(['diets' => $diets]);
    }

    public function assign(Request $request, int $memberId): JsonResponse
    {
        if (!$this->canUseDiet('diets.assign')) return $this->error('Permission denied', 403);
        if (!$this->canManageMember($memberId)) return $this->error('You can only assign diets to members assigned to you.', 403);

        $data = $request->validate([
            'template_id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'goal' => 'nullable|string|max:120',
            'diet_type' => 'nullable|string|max:60',
            'daily_calories' => 'nullable|integer|min:0',
            'protein_target' => 'nullable|integer|min:0',
            'water_target' => 'nullable|integer|min:0',
            'general_instructions' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'meals' => 'nullable|array|min:1',
            'meals.*.meal_name' => 'required_with:meals|string|max:120',
            'meals.*.meal_time' => 'nullable|string|max:30',
            'meals.*.food_items' => 'nullable|string',
            'meals.*.quantity' => 'nullable|string|max:255',
            'meals.*.calories' => 'nullable|integer|min:0',
            'meals.*.protein' => 'nullable|integer|min:0',
            'meals.*.carbs' => 'nullable|integer|min:0',
            'meals.*.fats' => 'nullable|integer|min:0',
            'meals.*.notes' => 'nullable|string',
        ]);

        $template = !empty($data['template_id']) ? $this->findTemplate((int) $data['template_id']) : null;
        if (!empty($data['template_id']) && (!$template || !$this->canUseTemplate($template))) {
            return $this->error('Diet template not available', 404);
        }
        if (!$template && empty($data['title'])) return $this->error('Select a template or enter diet title', 422);

        $actor = $this->currentUser();
        $diet = DB::transaction(function () use ($data, $template, $memberId, $actor) {
            MemberDiet::where('member_id', $memberId)
                ->whereIn('parent_id', $this->getGymParentIds())
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            $customMeals = $data['meals'] ?? [];
            $sourceMeals = !empty($customMeals) ? $customMeals : ($template ? $template->meals->map(fn ($m) => $m->toArray())->all() : []);
            if (empty($sourceMeals)) throw new \InvalidArgumentException('Diet must contain at least one meal');

            $diet = MemberDiet::create([
                'parent_id' => $this->getParentId(),
                'member_id' => $memberId,
                'template_id' => $template?->id,
                'assigned_by_user_id' => $actor->id,
                'assigned_by_type' => $actor->type,
                'title' => $data['title'] ?? $template->title,
                'goal' => $data['goal'] ?? $template?->goal,
                'diet_type' => $data['diet_type'] ?? $template?->diet_type,
                'daily_calories' => $data['daily_calories'] ?? $template?->daily_calories,
                'protein_target' => $data['protein_target'] ?? $template?->protein_target,
                'water_target' => $data['water_target'] ?? $template?->water_target,
                'general_instructions' => $data['general_instructions'] ?? $template?->general_instructions,
                'start_date' => $data['start_date'] ?? now()->toDateString(),
                'end_date' => $data['end_date'] ?? null,
                'status' => 'active',
                'is_customized' => !empty($customMeals),
            ]);
            $this->createMemberMeals($diet, $sourceMeals);
            return $diet->load('meals', 'template');
        });

        $this->notifyMemberDiet($memberId, $diet, false);
        return $this->success(['diet' => $diet], 'Diet assigned successfully', 201);
    }

    public function updateMemberDiet(Request $request, int $id): JsonResponse
    {
        if (!$this->canUseDiet('diets.edit')) return $this->error('Permission denied', 403);
        $diet = MemberDiet::where('id', $id)->whereIn('parent_id', $this->getGymParentIds())->first();
        if (!$diet || !$this->canManageMember($diet->member_id)) return $this->error('Member diet not found', 404);

        $data = $request->validate([
            'title' => 'required|string|max:255', 'goal' => 'nullable|string|max:120',
            'diet_type' => 'nullable|string|max:60', 'daily_calories' => 'nullable|integer|min:0',
            'protein_target' => 'nullable|integer|min:0', 'water_target' => 'nullable|integer|min:0',
            'general_instructions' => 'nullable|string', 'start_date' => 'nullable|date',
            'end_date' => 'nullable|date', 'status' => 'nullable|in:active,archived,completed',
            'meals' => 'required|array|min:1', 'meals.*.meal_name' => 'required|string|max:120',
        ]);

        DB::transaction(function () use ($diet, $data) {
            $diet->update(array_merge($data, ['is_customized' => true]));
            $diet->meals()->delete();
            $this->createMemberMeals($diet, $data['meals']);
        });
        return $this->success(['diet' => $diet->fresh()->load('meals', 'template')], 'Member diet updated');
    }

    public function destroyMemberDiet(int $id): JsonResponse
    {
        if (!$this->canUseDiet('diets.delete')) return $this->error('Permission denied', 403);
        $diet = MemberDiet::where('id', $id)->whereIn('parent_id', $this->getGymParentIds())->first();
        if (!$diet || !$this->canManageMember($diet->member_id)) return $this->error('Member diet not found', 404);
        $diet->delete();
        return $this->success([], 'Member diet deleted');
    }

    public function myDiet(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user || $user->type !== 'trainee') return $this->error('Member access required', 403);
        $diet = MemberDiet::where('member_id', $user->id)->where('status', 'active')
            ->whereIn('parent_id', $this->getGymParentIds())->with('meals')->latest()->first();
        return $this->success(['diet' => $diet]);
    }

    private function canUseDiet(string $permission): bool
    {
        $actor = $this->currentUser();
        return $this->isGymOwner() || ($actor && $actor->type === 'trainer') || ($this->isStaff() && $this->hasStaffPermission($permission));
    }

    private function canManageMember(int $memberId): bool
    {
        $actor = $this->currentUser();
        $memberExists = User::where('id', $memberId)->where('type', 'trainee')->whereIn('parent_id', $this->getGymParentIds())->exists();
        if (!$memberExists) return false;
        if ($actor->type !== 'trainer') return true;
        return TraineeDetail::where('user_id', $memberId)->where('trainer_assign', $actor->id)->exists();
    }

    private function findTemplate(int $id): ?DietTemplate
    {
        return DietTemplate::where('id', $id)->whereIn('parent_id', $this->getGymParentIds())->with('meals')->first();
    }

    private function canUseTemplate(DietTemplate $template): bool
    {
        $actor = $this->currentUser();
        return $actor->type !== 'trainer' || $template->is_shared || (int) $template->created_by_user_id === (int) $actor->id;
    }

    private function canManageTemplate(DietTemplate $template): bool
    {
        $actor = $this->currentUser();
        return $actor->type !== 'trainer' || (int) $template->created_by_user_id === (int) $actor->id;
    }

    private function validateTemplate(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255', 'goal' => 'nullable|string|max:120',
            'diet_type' => 'nullable|string|max:60', 'daily_calories' => 'nullable|integer|min:0',
            'protein_target' => 'nullable|integer|min:0', 'water_target' => 'nullable|integer|min:0',
            'general_instructions' => 'nullable|string', 'is_shared' => 'nullable|boolean',
            'meals' => 'required|array|min:1', 'meals.*.meal_name' => 'required|string|max:120',
            'meals.*.meal_time' => 'nullable|string|max:30', 'meals.*.food_items' => 'nullable|string',
            'meals.*.quantity' => 'nullable|string|max:255', 'meals.*.calories' => 'nullable|integer|min:0',
            'meals.*.protein' => 'nullable|integer|min:0', 'meals.*.carbs' => 'nullable|integer|min:0',
            'meals.*.fats' => 'nullable|integer|min:0', 'meals.*.notes' => 'nullable|string',
        ]);
    }

    private function replaceTemplateMeals(DietTemplate $template, array $meals): void
    {
        $template->meals()->delete();
        foreach ($meals as $index => $meal) DietTemplateMeal::create(array_merge($this->mealPayload($meal), ['diet_template_id' => $template->id, 'sort_order' => $index]));
    }

    private function createMemberMeals(MemberDiet $diet, array $meals): void
    {
        foreach ($meals as $index => $meal) MemberDietMeal::create(array_merge($this->mealPayload($meal), ['member_diet_id' => $diet->id, 'sort_order' => $index]));
    }

    private function mealPayload(array $meal): array
    {
        return [
            'meal_time' => $meal['meal_time'] ?? null, 'meal_name' => $meal['meal_name'],
            'food_items' => $meal['food_items'] ?? null, 'quantity' => $meal['quantity'] ?? null,
            'calories' => $meal['calories'] ?? null, 'protein' => $meal['protein'] ?? null,
            'carbs' => $meal['carbs'] ?? null, 'fats' => $meal['fats'] ?? null, 'notes' => $meal['notes'] ?? null,
        ];
    }
}
