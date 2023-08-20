from sqlite3 import Connection
from utils import db, addons
from utils.types import *

if __name__ == "__main__":
    db_conn: Connection = db.create_connection("database.db")
    folders: list[str] = db.get_folders(db_conn)
    author_aliases: dict[str, str] = db.get_author_aliases(db_conn)
    title_aliases: dict[str, str] = db.get_title_aliases(db_conn)

    local_addons: list[LocalAddon] = addons.get_local_addons(folders)
    local_addons: list[LocalAddon] = db.insert_local_addons(db_conn, local_addons)
    remote_addons: list[RemoteAddon] = addons.get_addons_from_simplaza()
    remote_addons: list[RemoteAddon] = db.insert_remote_addons(db_conn, remote_addons)

    addons_match: AddonsMatch = addons.match_addons(local_addons, remote_addons, author_aliases, title_aliases)
    db.insert_addons_match(db_conn, addons_match)

    local_addons: list[LocalAddon] = addons.set_is_updated(local_addons, remote_addons, addons_match)
    local_addons: list[LocalAddon] = db.insert_local_addons(db_conn, local_addons)

    matched_addons: list[MatchedAddon] = db.get_matched_addons(db_conn, True)
    addons.print_matched_addons(matched_addons)
    db_conn.close()
