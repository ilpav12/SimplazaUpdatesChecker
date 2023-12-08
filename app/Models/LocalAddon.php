<?php

namespace App\Models;

use App\Enums\IsInCommunityFolder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use function Sodium\add;

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
        'is_in_community_folder',
    ];

    protected $casts = [
        'is_excluded' => 'boolean',
        'is_in_community_folder' => IsInCommunityFolder::class,
    ];

    protected $appends = [
        'is_updated',
    ];

    public function remoteAddon(): \Illuminate\Database\Eloquent\Relations\belongsTo
    {
        return $this->belongsTo(RemoteAddon::class);
    }

    public function getDetailsAttribute(): string
    {
        return "$this->author - $this->title";
    }

    public function getIsUpdatedAttribute(): ?bool
    {
        if (is_null($this->remoteAddon)) {
            return null;
        }

        return version_compare(rtrim($this->version, ".0"), rtrim($this->remoteAddon->version, ".0"), '>=');
    }

    private static function createLocalAddon($folder, $isSimlinked = false): array
    {
        $manifest = json_decode(file_get_contents($folder . "/manifest.json"));

        if ($isSimlinked) {
            $isInCommunityFolder = IsInCommunityFolder::Symlinked;
        } elseif (Str::startsWith($folder, config('settings.community_folder'))) {
            $isInCommunityFolder = IsInCommunityFolder::Inside;
        } else {
            $isInCommunityFolder = IsInCommunityFolder::Outside;
        }

        $addon = [
            'author' => $manifest->author ?? $manifest->creator ?? 'No author',
            'title' => $manifest->title ?? 'No title',
            'version' => $manifest->version ?? $manifest->package_version ?? 'No version',
            'path' => $folder,
            'is_in_community_folder' => $isInCommunityFolder,
        ];
        if (str_contains($addon['title'], "AIRAC Cycle")) {
            $addon['version'] = str_replace(["AIRAC Cycle ", "."], ["", " "], $addon['title']);
        }
        return $addon;
    }

    private static function getLocalAddons($folders, $addons = null): array
    {
        $addons ??= [];

        if (is_array($folders)) {
            foreach ($folders as $folder) {
                return self::getLocalAddons($folder, $addons);
            }
        }
        $folder = $folders;

        foreach (array_diff(scandir($folder), array('..', '.')) as $item) {
            if (is_link($folder)) {
                $pointingAddon = LocalAddon::where('path', readlink($folder))->first();
                if ($pointingAddon) {
                    $pointingAddon->update(['is_in_community_folder' => IsInCommunityFolder::Symlinked]);
                } else {
                    $addons[] = self::createLocalAddon($folder, true);
                }
                break;
            }

            if (file_exists($folder . "/manifest.json")) {
                $addons[] = self::createLocalAddon($folder);
                break;
            }

            if (is_dir($folder . '/' . $item)) {
                $addons = self::getLocalAddons($folder . '/' . $item, $addons);
            }
        }

        return $addons;
    }

    public static function saveLocalAddons($communityFolder, $addonsFolders): void
    {
        $newLocalAddons = empty($addonsFolders)
            ? []
            : self::getLocalAddons($addonsFolders);
        $newLocalAddons += self::getLocalAddons($communityFolder);
        $oldLocalAddons = self::all();
        if ($oldLocalAddons->isEmpty()) {
            self::insert($newLocalAddons);
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

        $newLocalAddons = collect($newLocalAddons);
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
        if ($allRemoteAddons->isEmpty()) {
            RemoteAddon::saveRemoteAddons();
        }

        foreach ($localAddons as $localAddon) {
            $cleanLocalAuthor = Str::of($localAddon->author)
                ->replaceMatches('/[^A-Za-z0-9]++/', '')
                ->lower();
            $remoteAddons = $allRemoteAddons->filter(function ($remoteAddon) use ($localAddon, $cleanLocalAuthor) {
                if (version_compare(rtrim($remoteAddon->version, ".0"), rtrim($localAddon->version, ".0"), '<')) {
                    return false;
                }

                $cleanRemoteAuthor = Str::of($remoteAddon->author)
                    ->replaceMatches('/[^A-Za-z0-9]++/', '')
                    ->lower();
                if (!$cleanRemoteAuthor->contains($cleanLocalAuthor) && !$cleanLocalAuthor->contains($cleanRemoteAuthor)) {
                    return false;
                }

                return true;
            });


            $cleanLocalTitle = Str::of($localAddon->title)
                ->replaceMatches('/[^A-Za-z0-9]++/', '')
                ->lower();
            $remoteAddon = $remoteAddons->reduce(function ($carry, $remoteAddon) use ($localAddon, $cleanLocalTitle) {
                $cleanRemoteTitle = Str::of($remoteAddon->title)
                    ->replaceMatches('/[^A-Za-z0-9]++/', '')
                    ->lower();
                if ($cleanLocalTitle->contains($cleanRemoteTitle) || $cleanRemoteTitle->contains($cleanLocalTitle)) {
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
                $localAddon->save();
            }
        }
    }
}
