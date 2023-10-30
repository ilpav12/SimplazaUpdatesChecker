<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getRemoteAddons(): array
    {
        $response = Http::get("https://simplaza.org/torrent/rss.xml");
        $xmlContents = $response->body();
        $arrayContents = json_decode(json_encode(simplexml_load_string($xmlContents, options: LIBXML_NOCDATA)),true);

        if (cache('lastBuildDate') == $arrayContents['channel']['lastBuildDate']) {
            return [];
        }
        $lastBuildDate = $arrayContents['channel']['lastBuildDate'];
        $lastBuildDate = date('Y-m-d H:i:s', strtotime($lastBuildDate));
        cache(['lastBuildDate' => $lastBuildDate], 60*24);

        $addons = [];
        $updatedAddons = 0;

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

            $publishedAt = date('Y-m-d H:i:s', strtotime($addon['pubDate']));
            if ($publishedAt > $lastBuildDate) {
                $updatedAddons++;
            }

            $addons[] = [
                'author' => $author,
                'title' => $title,
                'version' => $version,
                'page' => $addon['link'],
                'torrent' => $addon['enclosure']['@attributes']['url'],
                'description' => $description,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ];
        }

        return [
            'addons' => $addons,
            'updatedAddons' => $updatedAddons,
        ];
    }

    public static function saveRemoteAddons(): int
    {
        $remoteAddons = self::getRemoteAddons();
        if (empty($remoteAddons)) {
            return 0;
        }

        RemoteAddon::truncate();
        RemoteAddon::insert($remoteAddons['addons']);
        return $remoteAddons['updatedAddons'];
    }
}
