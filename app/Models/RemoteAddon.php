<?php

namespace App\Models;

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
        'page',
        'torrent',
        'published_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

            $description = $addon['description'];
            $description = !str_contains($description, 'Note: ') ? '' : substr($description, strpos($description, 'Note: ') + 6);

            $addons->push([
                'author' => $author,
                'title' => $title,
                'version' => $version,
                'page' => $addon['link'],
                'torrent' => $addon['enclosure']['@attributes']['url'],
                'description' => $description,
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
