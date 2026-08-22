<?php

namespace App\Http\Controllers;

use App\Models\MemberDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Member KYC-style documents (Aadhaar front/back) uploaded by gym staff.
 * Additive feature: the old app versions never call these endpoints, so
 * they keep working unchanged.
 */
class MemberDocumentController extends BaseController
{
    private const DOC_TYPES = ['aadhaar_front', 'aadhaar_back'];

    private function publicFilePath(string $relativePath): string
    {
        // FTP-compatible public storage: public/uploads/member-documents/...
        return public_path('uploads/' . ltrim($relativePath, '/'));
    }

    /** Resolve the member with the same strict scoping as profile photos. */
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

    public function index(Request $request, int $memberId): JsonResponse
    {
        $member = $this->memberForGym($request, $memberId);
        if (!$member) return $this->error('Member not found or access denied', 404);

        $documents = MemberDocument::where('user_id', $memberId)->get();

        return $this->success(['documents' => $documents]);
    }

    public function store(Request $request, int $memberId): JsonResponse
    {
        $member = $this->memberForGym($request, $memberId);
        if (!$member) return $this->error('Member not found or access denied', 404);

        $data = $request->validate([
            'doc_type' => ['required', 'string', 'in:' . implode(',', self::DOC_TYPES)],
            'document' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $docType = $data['doc_type'];
        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = 'member-documents/' . $memberId . '/' . $docType . '_' . Str::uuid() . '.' . $extension;
        $destination = dirname($this->publicFilePath($path));
        File::ensureDirectoryExists($destination, 0755, true);
        $file->move($destination, basename($path));

        // One document per type: replace the previous file.
        $existing = MemberDocument::where('user_id', $memberId)->where('doc_type', $docType)->first();
        if ($existing) {
            try { File::delete($this->publicFilePath($existing->file_path)); } catch (\Throwable $e) {}
            $existing->update(['file_path' => $path]);
            $document = $existing->fresh();
        } else {
            $document = MemberDocument::create(['user_id' => $memberId, 'doc_type' => $docType, 'file_path' => $path]);
        }

        return $this->success(['document' => $document], 'Document uploaded');
    }

    public function destroy(Request $request, int $memberId, string $docType): JsonResponse
    {
        $member = $this->memberForGym($request, $memberId);
        if (!$member) return $this->error('Member not found or access denied', 404);

        if (!in_array($docType, self::DOC_TYPES, true)) {
            return $this->error('Invalid document type', 400);
        }

        $document = MemberDocument::where('user_id', $memberId)->where('doc_type', $docType)->first();
        if (!$document) return $this->success([], 'No document to remove');

        try { File::delete($this->publicFilePath($document->file_path)); } catch (\Throwable $e) {}
        $document->delete();

        return $this->success([], 'Document removed');
    }
}
