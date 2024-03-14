<?php

namespace App\Models;

use App\Enums\IsRecommended;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

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
        $client = new Client(['verify' => false]); // to avoid SSL error for missing certificate
        $response = Http::setClient($client)->get("https://simplaza.org/torrent/rss.xml");
        $xmlContents = $response->body();
        $arrayContents = json_decode(json_encode(simplexml_load_string($xmlContents, options: LIBXML_NOCDATA)),true);
        cache(['lastCheck' => now()], now()->addYears(10));

        $lastBuildDate = date('Y-m-d H:i:s', strtotime($arrayContents['channel']['lastBuildDate']));
        if (cache('lastBuildDate') == $lastBuildDate) {
            return collect();
        }
        cache(['lastBuildDate' => $lastBuildDate], now()->addYears(10));

        $addons = collect();

        foreach ($arrayContents['channel']['item'] as $addon) {
            $textContents = $addon['title'];

            $textContents = explode(' - ', $textContents);

            $author = $textContents[0];
            $title = implode(' - ', array_slice($textContents, 1));
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

            $warning = Str::of($addon['description'])
                ->whenContains('Warning:', function ($string) {
                    return $string
                        ->between('</a><br><br>', 'Note:')
                        ->finish('<br><br>');
                })
                ->whenExactly($addon['description'], fn () => false);

            $description = Str::of($addon['description'])
                ->whenContains('Note:', function ($string) use ($warning) {
                    return $string
                        ->after($warning ?: '</a><br><br>')
                        ->finish('<br><br>');
                })
                ->whenExactly($addon['description'], fn () => false);

            if (!$warning) {
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
                'description' => $description ?: null,
                'warning' => $warning ?: null,
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
