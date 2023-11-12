<?php

namespace App\Models;

use App\Enums\IsRecommended;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RemoteAddon extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'version',
        'description',
        'warning',
        'is_recommended',
        'page',
        'torrent',
        'published_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'published_at' => 'datetime',
        'is_recommended' => IsRecommended::class,
    ];

    public function getDetailsAttribute(): string
    {
        return "$this->author - $this->title";
    }

    public static function getRemoteAddons(): Collection
    {
        $response = Http::get("https://simplaza.org/torrent/rss.xml");
        $xmlContents = $response->body();
        $arrayContents = json_decode(json_encode(simplexml_load_string($xmlContents, options: LIBXML_NOCDATA)),true);

        $lastBuildDate = date('Y-m-d H:i:s', strtotime($arrayContents['channel']['lastBuildDate']));
        if (cache('lastBuildDate') == $lastBuildDate) {
            return collect();
        }
        cache(['lastBuildDate' => $lastBuildDate], 60*24);

        $addons = collect();

        foreach ($arrayContents['channel']['item'] as $addon) {
            $textContents = $addon['title'];

            $pattern = '/(.*) - (.*)/';
            preg_match($pattern, $textContents, $match);

            $author = $match[1];
            $title = $match[2];
            $version = "No version";

            $words = explode(' ', $title);

            foreach ($words as $word) {
                if (str_starts_with($word, 'v') && is_numeric(substr($word, 1, 1))) {
                    $version = substr($word, 1);
                    $title = implode(' ', array_slice($words, 0, array_search($word, $words)));
                    break;
                }

                if ($word == 'Cycle') {
                    $version = implode(' ', array_slice($words, array_search($word, $words)+1));
                    $title = implode(' ', array_slice($words, 0, array_search($word, $words)+1));
                    break;
                }
            }

            $description = $warning = null;
            $notePosition = strpos($addon['description'], 'Note: ');
            if ($notePosition !== false) {
                $description = substr($addon['description'], $notePosition + 6) . '<br><br>';
            }

            $warningPosition = strpos($addon['description'], 'Warning: ');
            if ($warningPosition !== false) {
                $warning = $notePosition === false
                    ? substr($addon['description'], $warningPosition + 9) . '<br><br>'
                    : substr($addon['description'], $warningPosition + 9, $notePosition - $warningPosition - 9);
            }

            if (!isset($warning)) {
                $isRecommended = IsRecommended::NoConflicts;
            } else {
                $pattern = '/\((.*) recommended\)/';
                preg_match($pattern, $warning, $match);

                if (!isset($match[1])) {
                    $isRecommended = IsRecommended::NoRecommendation;
                } else {
                    $isRecommended = $match[1] == $author
                        ? IsRecommended::FullyRecommended
                        : IsRecommended::NotRecommended;
                }
            }

            $addons->push([
                'author' => $author,
                'title' => $title,
                'version' => $version,
                'page' => $addon['link'],
                'torrent' => $addon['enclosure']['@attributes']['url'],
                'description' => $description ?? null,
                'warning' => $warning ?? null,
                'is_recommended' => $isRecommended,
                'published_at' => date('Y-m-d H:i:s', strtotime($addon['pubDate'])),
            ]);
        }

        return $addons;
    }

    public static function saveRemoteAddons(): int
    {
        $newRemoteAddons = self::getRemoteAddons();
        if ($newRemoteAddons->isEmpty()) {
            return 0;
        }

        if (self::all()->isEmpty()) {
            $timestamp = Carbon::now();
            $newRemoteAddons->transform(function ($addon) use ($timestamp) {
                $addon['created_at'] = $timestamp;
                $addon['updated_at'] = $timestamp;
                return $addon;
            });
            self::insert($newRemoteAddons->toArray());
            return $newRemoteAddons->count();
        }

        $recentlyCreated = 0;
        foreach ($newRemoteAddons as $newRemoteAddon) {
            $addon = self::updateOrCreate(
                [
                    'title' => $newRemoteAddon['title'],
                    'author' => $newRemoteAddon['author'],
                ],
                [
                    'page' => $newRemoteAddon['page'],
                    'version' => $newRemoteAddon['version'],
                    'description' => $newRemoteAddon['description'],
                    'warning' => $newRemoteAddon['warning'],
                    'is_recommended' => $newRemoteAddon['is_recommended'],
                    'torrent' => $newRemoteAddon['torrent'],
                    'published_at' => $newRemoteAddon['published_at'],
                ]
            );

            if ($addon->wasRecentlyCreated) {
                $recentlyCreated++;
            }
        }

        foreach (self::all() as $remoteAddon) {
            if (!$newRemoteAddons->contains('page', $remoteAddon->page)) {
                $remoteAddon->delete();
            }
        }

        return $recentlyCreated;
    }
}
