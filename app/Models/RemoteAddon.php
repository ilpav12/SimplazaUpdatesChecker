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
        'page',
        'torrent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function getRemoteAddons(): array
    {
        $response = Http::get("https://simplaza.org/torrent-master-list/");
        $htmlContents = $response->body();

        $dom = new DOMDocument;

        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContents);
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($dom);
        $pages = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' nv-content-wrap entry-content ')]/p/a");
        $torrents = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' nv-content-wrap entry-content ')]/p/strong/a[contains(., 'Download')]");

        $addons = [];

        foreach ($pages as $key => $page) {
            $textContents = $page->textContent;

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

            $addons[] = [
                'author' => $author,
                'title' => $title,
                'version' => $version,
                'page' => $page->attributes['href']->value,
                'torrent' => $torrents[$key]->attributes['href']->value,
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
                return $oldAddon['title'] == $newAddon['title'] && $oldAddon['author'] == $newAddon['author'];
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

    public function download()
    {
        return redirect()->away($this->torrent);
    }
}
