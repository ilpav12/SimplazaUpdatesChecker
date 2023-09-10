from typing import TypedDict


class LocalAddon(TypedDict):
    id: int | None
    title: str
    author: str
    version: str
    path: str
    is_excluded: bool | None
    is_updated: bool | None


class RemoteAddon(TypedDict):
    id: int | None
    title: str
    author: str
    version: str
    link: str


AddonsMatch = dict[int, int | None]


class MatchedAddon(TypedDict):
    local_title: str
    local_author: str
    local_version: str
    remote_title: str
    remote_author: str
    remote_version: str
    link: str
