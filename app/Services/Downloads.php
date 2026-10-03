<?php

namespace App\Services;

use App\Models\DownloadAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Downloads
{
    public function create(User $actor, array $data, UploadedFile $file): DownloadAsset
    {
        abort_unless($actor->hasPermission('downloads.manage'), 403);
        $v = Validator::make($data + ['file' => $file], ['title' => 'required|string|max:180', 'description' => 'required|string|max:10000', 'product_id' => 'nullable|integer|exists:products,id', 'file' => 'required|file|max:10240', 'active' => 'sometimes|boolean'])->validate();
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'zip', 'txt', 'gz', 'tar'], true)) {
            throw ValidationException::withMessages(['file' => 'Use PDF, ZIP, TXT, GZ ou TAR; conteúdo não é executado nem extraído.']);
        }
        $raw = $file->getContent();
        if (strlen($raw) > 10485760 || strlen($raw) === 0) {
            throw ValidationException::withMessages(['file' => 'Arquivo vazio ou acima de 10 MiB.']);
        }
        $path = 'downloads/'.Str::uuid().'.encrypted';
        $filename = 'arquivo-'.Str::random(12).'.'.$extension;
        try {
            if (! Storage::disk('local')->put($path, Crypt::encryptString($raw))) {
                throw new \RuntimeException('Private storage unavailable');
            }

            return DB::transaction(function () use ($v, $actor, $path, $filename, $raw) {
                $asset = DownloadAsset::create(['author_id' => $actor->id, 'product_id' => $v['product_id'] ?? null, 'title' => $v['title'], 'description' => $v['description'], 'active' => (bool) ($v['active'] ?? false), 'path' => $path, 'filename' => $filename, 'size' => strlen($raw), 'sha256' => hash('sha256', $raw)]);
                Audit::record('download.created', 'download:'.$asset->id, [], $actor->id);

                return $asset;
            }, 5);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function bytes(DownloadAsset $asset): string
    {
        if (! preg_match('~^downloads/[a-f0-9-]{36}\.encrypted$~D', $asset->path)) {
            abort(404);
        }
        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->path) && $disk->size($asset->path) <= 22000000, 404);
        try {
            $raw = Crypt::decryptString($disk->get($asset->path));
        } catch (\Throwable) {
            abort(409, 'Arquivo indisponível ou com integridade inválida.');
        }
        abort_unless(strlen($raw) === $asset->size && hash_equals($asset->sha256, hash('sha256', $raw)), 409);

        return $raw;
    }
}
