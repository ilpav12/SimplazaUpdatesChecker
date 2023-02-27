import requests
from bs4 import BeautifulSoup
import json
import os
from difflib import SequenceMatcher
from tabulate import tabulate


def get_addons_from_simplaza():
    url = "https://simplaza.org/torrent-master-list/"
    page = requests.get(url)

    soup = BeautifulSoup(page.content, "html.parser")

    content = soup.find(class_="nv-content-wrap entry-content") \
        .findChildren("p")[1] \
        .findChildren("a", string=lambda text: "Download" not in text)

    addons = []

    for addon in content:
        link = addon['href']
        author = addon.text.split(" – ")[0]
        title = ""
        for word in addon.text.split(" – ")[1].split():
            if word[0] == 'v' and word[1].isnumeric():
                version = word[1:]
                title = title[:-1]
                break
            else:
                title += word + " "

        addons.append({'author': author, 'title': title, 'version': version, 'link': link})

    return addons


# print(get_addons_from_simplaza())

def get_local_addons(folder, addons=[]):
    for item in os.listdir(folder):
        if 'manifest.json' in os.listdir(folder):
            f = open(folder + "/manifest.json", 'r', encoding="cp866")
            manifest = json.load(f)
            addon = {
                'author': manifest.get('author', manifest.get('creator', 'No author')),
                'title': manifest.get('title', 'No title'),
                'version': manifest.get('version', manifest.get('package_version', 'No version'))
            }
            addons.append(addon)
            break
        else:
            if os.path.isdir(folder + "/" + item):
                get_local_addons(folder + "/" + item, addons)

    return addons


# print(get_local_addons("E:/MSFS Addons"))

def remove_0s(version):
    if version[-2:] == ".0":
        version = version[:-2]
        return remove_0s(version)

    return version


def get_results(n):
    for local_addon in get_local_addons("E:/MSFS Addons"):
        if (local_addon['title'] == '' or local_addon['title'] == 'No title') or (
                local_addon['version'] == '' or local_addon['version'] == 'No version'):
            continue
        ratio = 0
        matching_addon = None
        local_author = local_addon['author'] \
            .replace(" ", "") \
            .replace(".", "") \
            .replace("-", "") \
            .replace("_", "") \
            .replace("/", "") \
            .lower()
        for remote_addon in get_addons_from_simplaza():
            remote_author = remote_addon['author'] \
                .replace(" ", "") \
                .replace(".", "") \
                .replace("-", "") \
                .replace("_", "") \
                .replace("/", "") \
                .lower()
            # if author is not the same or author name contains each other, skip
            if local_author != remote_author and local_author not in remote_author and remote_author not in local_author:
                continue
            else:
                if SequenceMatcher(None, local_addon['title'], remote_addon['title']).ratio() > ratio:
                    ratio = SequenceMatcher(None, local_addon['title'], remote_addon['title']).ratio()
                    matching_addon = remote_addon

        if matching_addon is None:
            matching_addon = {'title': 'No match', 'author': 'No match', 'version': 'No match', 'link': 'No match'}

        local_version = remove_0s(local_addon['version'])
        remote_version = remove_0s(matching_addon['version'])

        if n == 1 \
                or (n == 2 and local_version != remote_version) \
                or (n == 3 and matching_addon['title'] == 'No match'):
            print(tabulate([
                ['Local', local_addon['title'], local_addon['author'], local_addon['version']],
                ['Remote', matching_addon['title'], matching_addon['author'], matching_addon['version']]
            ], headers=['Location', 'Title', 'Author', 'Version']))
            print("Link: " + matching_addon['link'])
            print()


print("Choose an option for the results:")
print("1. Show all addons")
print("2. Show only addons with updates")
print("3. Show only addons with no match")
option = input("Option: ")
print()

get_results(int(option))
