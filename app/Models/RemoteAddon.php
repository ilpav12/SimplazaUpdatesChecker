<?php

namespace App\Models;

use DOMDocument;
use DOMXPath;
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
        'link',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getRemoteAddons(): array
    {
        $url = "https://simplaza.org/torrent-master-list/";
        $response = Http::get($url);
        $htmlContents = $response->body();

        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContents);
        libxml_use_internal_errors(false);

        $finder = new DomXPath($dom);

        $nodes = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' nv-content-wrap entry-content ')]/p/a[not(contains(.,'Download'))]");

        $addons = [];

        foreach ($nodes as $node) {
            $textContents = $node->textContent;

            preg_match('/(.+)\s–\s(.+)/', $textContents, $match);

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

            $link = $node->attributes['href']->value;

            $addons[] = [
                'author' => $author,
                'title' => $title,
                'version' => $version,
                'link' => $link
            ];
        }

        return $addons;
    }

    public static function saveRemoteAddons(): void
    {
        $newAddons = self::getRemoteAddons();
        $oldAddons = self::all()->toArray();

        foreach ($newAddons as $newAddon) {
            $oldAddon = array_filter($oldAddons, function ($oldAddon) use ($newAddon) {
                return $oldAddon['link'] == $newAddon['link'];
            });

            if (empty($oldAddon)) {
                self::create($newAddon);
            } else {
                $oldAddon = array_values($oldAddon)[0];

                if ($oldAddon['version'] != $newAddon['version']) {
                    self::where('link', $oldAddon['link'])->update($newAddon);
                }
            }
        }
    }
}
