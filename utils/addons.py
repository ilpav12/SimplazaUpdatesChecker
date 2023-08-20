import json
import os
import requests
from bs4 import BeautifulSoup
from rapidfuzz import fuzz
from tabulate import tabulate
from utils.types import *


def get_local_addons(folders: list[str] | str, addons: list[LocalAddon] = None) -> list[LocalAddon]:
    if addons is None:
        addons: list[LocalAddon] = []

    if isinstance(folders, list):
        for folder in folders:
            get_local_addons(folder, addons)
        return addons
    else:
        folder = folders

    for item in os.listdir(folder):
        if 'manifest.json' in os.listdir(folder):
            f = open(folder + "/manifest.json", 'r', encoding="cp866")
            manifest = json.load(f)

            addon: LocalAddon = {
                'id': None,
                'author': manifest.get('author', manifest.get('creator', 'No author')),
                'title': manifest.get('title', 'No title'),
                'version': manifest.get('version', manifest.get('package_version', 'No version')),
                'path': folder,
                'is_updated': None,
                'is_excluded': None,
            }
            if "AIRAC Cycle" in addon['title']:
                addon['version'] = addon['title'].replace("AIRAC Cycle ", "").replace(".", " ")
            addons.append(addon)

            break
        else:
            if os.path.isdir(folder + "\\" + item):
                get_local_addons(folder + "\\" + item, addons)

    return addons


def get_addons_from_simplaza() -> list[RemoteAddon]:
    url = "https://simplaza.org/torrent-master-list/"
    page = requests.get(url)

    soup = BeautifulSoup(page.content, "html.parser")

    scraped_content = soup.find(class_="nv-content-wrap entry-content") \
        .findChildren("p")[1] \
        .findChildren("a", string=lambda text: "Download" not in text)

    addons: list[RemoteAddon] = []

    for element in scraped_content:
        link = element['href']
        author = element.text.split(" – ")[0]
        title = element.text.split(" – ")[1:]
        version = "No version"
        words = ' '.join(title).split()
        for word in words:
            if word[0] == 'v' and word[1].isnumeric():
                version = word[1:]
                title = ' '.join(words[:words.index(word)])
                break

            if word == 'Cycle':
                version = ' '.join(words[words.index(word) + 1:])
                title = ' '.join(words[:words.index(word) + 1])
                break

        addon: RemoteAddon = {'id': None,
                              'author': author,
                              'title': title,
                              'version': version,
                              'link': link}
        addons.append(addon)

    return addons


def match_addons(local_addons: list[LocalAddon], remote_addons: list[RemoteAddon],
                 author_aliases: dict[str, str], title_aliases: dict[str, str]) -> dict[int, int | None]:
    matching_addons = {}

    for local_addon in local_addons:
        if local_addon['title'] == '':
            matching_addons[local_addon['id']] = None
            continue

        ratio = 0
        matching_addon = None

        local_author = local_addon['author']
        if local_author in author_aliases:
            local_author = author_aliases[local_author]

        local_title = local_addon['title']
        if local_title in title_aliases:
            local_title = title_aliases[local_title]

        working_remote_addons = [remote_addon
                                 for remote_addon in remote_addons
                                 if process_author(local_author) == process_author(remote_addon['author'])]
        if len(working_remote_addons) == 0:
            working_remote_addons = remote_addons

        for remote_addon in working_remote_addons:
            if remove_0s(remote_addon['version']) < remove_0s(local_addon['version']):
                continue

            if fuzz.ratio(local_title, remote_addon['title']) > ratio:
                ratio = fuzz.ratio(local_title, remote_addon['title'])
                matching_addon = remote_addon

        matching_addons[local_addon['id']] = matching_addon['id'] if matching_addon is not None else None

    return matching_addons


def set_is_updated(local_addons: list[LocalAddon], remote_addons: list[RemoteAddon],
                   addons_match: AddonsMatch) -> list[LocalAddon]:
    for local_addon in local_addons:
        if (local_addon['title'] == '' or local_addon['version'] == '' or
                local_addon['is_excluded'] == 1 or
                addons_match[local_addon['id']] is None):
            local_addon['is_updated'] = None
            continue

        matching_addon = [addon for addon in remote_addons if addon['id'] == addons_match[local_addon['id']]][0]

        if remove_0s(local_addon['version']) == remove_0s(matching_addon['version']):
            local_addon['is_updated'] = True
        else:
            local_addon['is_updated'] = False

    return local_addons


def print_matched_addons(matched_addons: list[MatchedAddon]) -> None:
    for addon in matched_addons:
        print(tabulate([
            ['Local', addon['local_title'], addon['local_author'], addon['local_version']],
            ['Remote', addon['remote_title'], addon['remote_author'], addon['remote_version']]
        ], headers=['Location', 'Title', 'Author', 'Version']))
        print("Link: " + addon['link'])
        print()


def remove_0s(version):
    """Remove all '.0' occurrences from the end of the version string"""
    if version[-2:] == ".0":
        version = version[:-2]
        return remove_0s(version)

    return version


def process_author(author):
    return author \
        .replace(" ", "") \
        .replace(".", "") \
        .replace("-", "") \
        .replace("_", "") \
        .replace("/", "") \
        .lower()
