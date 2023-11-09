<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LocalAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'version',
        'path',
        'remote_addon_id',
        'is_updated',
        'is_excluded',
    ];

    protected $casts = [
        'is_updated' => 'boolean',
        'is_excluded' => 'boolean',
    ];

    public function remoteAddon(): \Illuminate\Database\Eloquent\Relations\belongsTo
    {
        return $this->belongsTo(RemoteAddon::class);
    }

    public function getDetailsAttribute(): string
    {
        return "$this->author - $this->title";
    }

    public static function getLocalAddons($folders, $addons = null): Collection
    {
        if ($addons == null) {
            $addons = collect();
        }

        if (is_array($folders)) {
            foreach($folders as $folder) {
                $addons = self::getLocalAddons($folder, $addons);
            }
            return $addons;
        }
        $folder = $folders;

        foreach (array_diff(scandir($folder), array('..', '.')) as $item) {
            if (file_exists($folder . "/manifest.json")) {
                $manifest = json_decode(file_get_contents($folder . "/manifest.json"));

                $addon = collect([
                    'author' => $manifest->author ?? $manifest->creator ?? 'No author',
                    'title' => $manifest->title ?? 'No title',
                    'version' => $manifest->version ?? $manifest->package_version ?? 'No version',
                    'path' => $folder,
                ]);
                if (str_contains($addon['title'], "AIRAC Cycle")) {
                    $addon['version'] = str_replace(["AIRAC Cycle ", "."], ["", " "], $addon['title']);
                }
                $addons->push($addon);

                break;
            }

            if (is_dir($folder . '/' . $item)) {
                $addons = self::getLocalAddons($folder . '/' . $item, $addons);
            }
        }

        return $addons;
    }

    public static function saveLocalAddons($folders): void
    {
        $newLocalAddons = self::getLocalAddons($folders);
        $oldLocalAddons = self::all();
        if ($oldLocalAddons->isEmpty()) {
            self::insert($newLocalAddons->toArray());
            return;
        }

        foreach ($newLocalAddons as $localAddon) {
            self::updateOrCreate(
                [
                    'path' => $localAddon['path'],
                ],
                [
                    'title' => $localAddon['title'],
                    'author' => $localAddon['author'],
                    'version' => $localAddon['version'],
                ]
            );
        }

        foreach ($oldLocalAddons as $oldLocalAddon) {
            if (!$newLocalAddons->contains('path', $oldLocalAddon->path)) {
                $oldLocalAddon->delete();
            }
        }
    }

    public static function matchLocalAddons($unmatchedOnly = true): void
    {
        $localAddons = self::query()
            ->where('is_excluded', false)
            ->when($unmatchedOnly, fn ($query) => $query->whereNull('remote_addon_id'))
            ->get();

        $allRemoteAddons = RemoteAddon::all();

        foreach ($localAddons as $localAddon) {
            $cleanLocalAuthor = strtolower(str_replace(' ', '', $localAddon->author));
            $remoteAddons = $allRemoteAddons->filter(function ($remoteAddon) use ($localAddon, $cleanLocalAuthor) {
                if (version_compare(rtrim($remoteAddon->version, ".0"), rtrim($localAddon->version, ".0"), '<')) {
                    return false;
                }

                $cleanRemoteAuthor = strtolower(str_replace(' ', '', $remoteAddon->author));
                if ($cleanRemoteAuthor != $cleanLocalAuthor && !str_contains($cleanRemoteAuthor, $cleanLocalAuthor) && !str_contains($cleanLocalAuthor, $cleanRemoteAuthor)) {
                    return false;
                }

                return true;
            });


            $cleanLocalTitle = strtolower(str_replace(' ', '', $localAddon->title));
            $remoteAddon = $remoteAddons->reduce(function ($carry, $remoteAddon) use ($localAddon, $cleanLocalTitle) {
                $cleanRemoteTitle = strtolower(str_replace(' ', '', $remoteAddon->title));
                if ($cleanRemoteTitle == $cleanLocalTitle || str_contains($cleanRemoteTitle, $cleanLocalTitle) || str_contains($cleanLocalTitle, $cleanRemoteTitle)) {
                    $carry['distance'] = 0;
                    $carry['remoteAddon'] = $remoteAddon;
                    return $carry;
                }

                $distance = levenshtein($cleanLocalTitle, $cleanRemoteTitle);
                if ($distance < $carry['distance']) {
                    $carry['distance'] = $distance;
                    $carry['remoteAddon'] = $remoteAddon;
                }
                return $carry;
            }, ['distance' => 1000, 'remoteAddon' => null])['remoteAddon'];

            if ($remoteAddon) {
                $localAddon->remote_addon_id = $remoteAddon->id;
                $localAddon->is_updated = version_compare(rtrim($localAddon->version, ".0"), rtrim($remoteAddon->version, ".0"), '>=');
                $localAddon->save();
            }
        }
    }
}
