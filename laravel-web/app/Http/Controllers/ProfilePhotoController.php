<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ProfilePhotoController extends BaseController
{
    private function publicFilePath(string $relativePath): string
    {
        // FTP-compatible public storage: public/uploads/profile-photos/...
        // No `storage:link` or server terminal access is required.
        return public_path('uploads/' . ltrim($relativePath, '/'));
    }

    private function upload(Request $request, User $target): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $file = $request->file('photo');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = 'profile-photos/' . $target->id . '/' . Str::uuid() . '.' . $extension;
        $destination = dirname($this->publicFilePath($path));
        File::ensureDirectoryExists($destination, 0755, true);
        $file->move($destination, basename($path));

        if ($target->profile) {
            try { File::delete($this->publicFilePath($target->profile)); } catch (\Throwable $e) {}
        }
        $target->update(['profile' => $path]);

        return $this->success([
            'profile' => $target->profile,
            'profile_photo_url' => $target->fresh()->profile_photo_url,
        ], 'Profile photo updated');
    }

    private function remove(User $target): JsonResponse
    {
        if ($target->profile) {
            try { File::delete($this->publicFilePath($target->profile)); } catch (\Throwable $e) {}
        }
        $target->update(['profile' => null]);
        return $this->success([
            'profile' => null,
            'profile_photo_url' => $target->fresh()->profile_photo_url,
        ], 'Profile photo removed');
    }

    public function uploadMine(Request $request): JsonResponse
    {
        return $this->upload($request, $request->user());
    }

    public function removeMine(Request $request): JsonResponse
    {
        return $this->remove($request->user());
    }

    private function memberForGym(Request $request, int $id): ?User
    {
        $actor = $request->user();
        if (!in_array($actor->type, ['admin', 'owner', 'staff'], true)) return null;
        if ($actor->type === 'staff' && !$actor->hasStaffPermission('members.edit')) return null;
        return User::where('id', $id)
            ->where('type', 'trainee')
            ->whereIn('parent_id', $this->getGymParentIds())
            ->first();
    }

    public function uploadMember(Request $request, int $id): JsonResponse
    {
        $member = $this->memberForGym($request, $id);
        if (!$member) return $this->error('Member not found or access denied', 404);
        return $this->upload($request, $member);
    }

    public function removeMember(Request $request, int $id): JsonResponse
    {
        $member = $this->memberForGym($request, $id);
        if (!$member) return $this->error('Member not found or access denied', 404);
        return $this->remove($member);
    }
}
