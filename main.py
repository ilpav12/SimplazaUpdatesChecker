import requests
from bs4 import BeautifulSoup
import json
import os
from difflib import SequenceMatcher
from tabulate import tabulate

settings, author_alias, title_alias, author_exclude, title_exclude = {}, {}, {}, [], []

if os.path.isfile('options.json'):
    with open('options.json') as json_options:
        options = json.load(json_options)

        settings = options.get('settings')
        author_alias = options.get('author_alias')
        title_alias = options.get('title_alias')
        author_exclude = options.get('author_exclude')
        title_exclude = options.get('title_exclude')


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
        version = "No version"
        if "Navigraph – Navdata MSFS 2020 AIRAC Cycle" in addon.text:
            title = "Navdata MSFS 2020 AIRAC Cycle"
            version = addon.text.replace("Navigraph – Navdata MSFS 2020 AIRAC Cycle", "")
        else:
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
            if "AIRAC Cycle" in addon['title']:
                addon['version'] = addon['title'].replace("AIRAC Cycle", "").replace(".", " ")
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


def get_results(n, folder):
    for local_addon in get_local_addons(folder):
        if local_addon['author'] in author_exclude:
            continue
        if any(word in local_addon['title'].lower() for word in title_exclude) or local_addon['title'] in title_exclude:
            continue
        if (local_addon['title'] == '' or local_addon['title'] == 'No title') or (
                local_addon['version'] == '' or local_addon['version'] == 'No version'):
            continue

        for word in local_addon['title'].split():
            if word in title_alias:
                local_addon['title'] = local_addon['title'].replace(word, title_alias[word])
        if local_addon['title'] in title_alias:
            local_addon['title'] = title_alias[local_addon['title']]
        ratio = 0
        matching_addon = None

        if local_addon['author'] in author_alias:
            local_author = author_alias[local_addon['author']].replace(" ", "").lower()
        else:
            local_author = local_addon['author'] \
                .replace(" ", "") \
                .replace(".", "") \
                .replace("-", "") \
                .replace("_", "") \
                .replace("/", "") \
                .lower()

        for remote_addon in get_addons_from_simplaza():
            if remote_addon['version'] < local_addon['version'] or remote_addon['version'] == 'No version':
                continue

            remote_author = remote_addon['author'] \
                .replace(" ", "") \
                .replace(".", "") \
                .replace("-", "") \
                .replace("_", "") \
                .replace("/", "") \
                .lower()
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


def get_user_input():
    if settings.get('folder'):
        folder = settings['folder']
    else:
        print("Check addons in the current folder? (Y/N)")
        option = input("Option: ")

        if option.lower() == 'y':
            print()
            folder = os.getcwd()
        elif option.lower() == 'n':
            print()
            print("Enter the folder path:")
            folder = input("Path: ")
            print()
            if not os.path.isdir(folder):
                print("Invalid folder")
                get_user_input()
        else:
            print("Invalid option")
            get_user_input()

    if settings.get('result_option'):
        option = settings['result_option']
    else:
        print("Choose an option for the results:")
        print("1. Show all addons")
        print("2. Show only addons with updates")
        print("3. Show only addons with no match")
        option = input("Option: ")
        print()

    if option.isnumeric() and 1 <= int(option) <= 3:
        get_results(int(option), folder)
        print("Press any key to exit")
        input()
    else:
        print("Invalid option")
        get_user_input()


get_user_input()
