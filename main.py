import requests
from bs4 import BeautifulSoup

URL = "https://simplaza.org/torrent-master-list/"
page = requests.get(URL)

soup = BeautifulSoup(page.content, "html.parser")

content = soup.find(class_="nv-content-wrap entry-content")
addons = content.findChildren("p")[1]
links = addons.findChildren("a", string=lambda text: "Download" not in text)

for link in links:
    print(link.text)
    author = link.text.split(" – ")[0]
    title = ""
    for word in link.text.split(" – ")[1].split():
        if word[0] == 'v' and word[1].isnumeric():
            version = word
            title = title[:-1]
            break
        else:
            title += word + " "

    print(author, title, version, sep=', ')

