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
        'is_updated',
        'is_excluded',
    ];

    protected $casts = [
        'is_updated' => 'boolean',
        'is_excluded' => 'boolean',
    ];

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
        $localAddons = self::getLocalAddons($folders);
        self::truncate();
        self::insert($localAddons->toArray());
    }

}
