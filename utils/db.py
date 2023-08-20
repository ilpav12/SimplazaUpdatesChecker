import sqlite3
from sqlite3 import Error
from utils.types import *


def create_connection(db_file: str) -> sqlite3.Connection:
    conn = None
    try:
        conn = sqlite3.connect(db_file)
    except Error as e:
        print(e)

    return conn


def get_folders(conn: sqlite3.Connection) -> list[str]:
    cur = conn.cursor()
    cur.execute("SELECT path FROM folders")

    rows = cur.fetchall()
    folders = [row[0] for row in rows]

    return folders


def get_author_aliases(conn: sqlite3.Connection) -> dict[str, str]:
    cur = conn.cursor()
    cur.execute("SELECT * FROM author_aliases")

    rows = cur.fetchall()
    author_aliases = {}
    for row in rows:
        author_aliases[row[0]] = row[1]

    return author_aliases


def get_title_aliases(conn: sqlite3.Connection) -> dict[str, str]:
    cur = conn.cursor()
    cur.execute("SELECT * FROM title_aliases")

    rows = cur.fetchall()
    title_aliases = {}
    for row in rows:
        title_aliases[row[0]] = row[1]

    return title_aliases


def insert_local_addons(conn: sqlite3.Connection, local_addons: list[LocalAddon]) -> list[LocalAddon]:
    sql = '''REPLACE INTO local_addons(id, title, author, version, path, is_excluded, is_updated)
             VALUES((SELECT id FROM local_addons WHERE title = :title AND author = :author AND path = :path),
                    :title,
                    :author,
                    :version,
                    :path,
                    COALESCE(:is_excluded, (SELECT is_excluded
                                            FROM   local_addons
                                            WHERE  title = :title AND author = :author)),
                    COALESCE(:is_updated, (SELECT is_updated
                                           FROM   local_addons
                                           WHERE  title = :title AND author = :author)))'''
    cur = conn.cursor()
    cur.executemany(sql, local_addons)
    conn.commit()

    sql = '''SELECT * FROM local_addons'''
    cur = conn.cursor()
    cur.execute(sql)
    rows = cur.fetchall()

    local_addons = []
    for row in rows:
        local_addon: LocalAddon = {'id': row[0],
                                   'title': row[1],
                                   'author': row[2],
                                   'version': row[3],
                                   'path': row[4],
                                   'is_excluded': row[5],
                                   'is_updated': row[6]}
        local_addons.append(local_addon)

    return local_addons


def insert_remote_addons(db_conn: sqlite3.Connection, remote_addons: list[RemoteAddon]) -> list[RemoteAddon]:
    sql = '''REPLACE INTO remote_addons(id, title, author, version, link)
             VALUES((SELECT id FROM remote_addons WHERE title = :title AND author = :author),
                    :title,
                    :author,
                    :version,
                    :link)'''
    cur = db_conn.cursor()
    cur.executemany(sql, remote_addons)
    db_conn.commit()

    sql = '''SELECT * FROM remote_addons'''
    cur = db_conn.cursor()
    cur.execute(sql)
    rows = cur.fetchall()

    remote_addons = []
    for row in rows:
        remote_addon: RemoteAddon = {'id': row[0],
                                     'title': row[1],
                                     'author': row[2],
                                     'version': row[3],
                                     'link': row[4]}
        remote_addons.append(remote_addon)

    return remote_addons


def insert_addons_match(db_conn: sqlite3.Connection, addons_match: AddonsMatch) -> None:
    sql = '''REPLACE INTO addons_match(local_addon_id, remote_addon_id)
             VALUES(?, ?)'''
    cur = db_conn.cursor()
    cur.executemany(sql, addons_match.items())
    db_conn.commit()


def get_matched_addons(db_conn: sqlite3.Connection, to_update_only: bool = False) -> list[MatchedAddon]:
    sql = '''SELECT local_addons.title, local_addons.author, local_addons.version,
                    remote_addons.title, remote_addons.author, remote_addons.version, remote_addons.link
                FROM local_addons
                INNER JOIN addons_match ON local_addons.id = addons_match.local_addon_id
                INNER JOIN remote_addons ON addons_match.remote_addon_id = remote_addons.id
                WHERE local_addons.is_excluded = 0 AND local_addons.is_updated = %s'''
    cur = db_conn.cursor()
    cur.execute(sql % ('0' if to_update_only else '1'))
    rows = cur.fetchall()

    matched_addons = []
    for row in rows:
        matched_addon: MatchedAddon = {'local_title': row[0],
                                       'local_author': row[1],
                                       'local_version': row[2],
                                       'remote_title': row[3],
                                       'remote_author': row[4],
                                       'remote_version': row[5],
                                       'link': row[6]}
        matched_addons.append(matched_addon)

    return matched_addons


def get_addons_match(db_conn: sqlite3.Connection) -> AddonsMatch:
    sql = '''SELECT * FROM addons_match'''
    cur = db_conn.cursor()
    cur.execute(sql)
    rows = cur.fetchall()

    addons_match = {}
    for row in rows:
        addons_match[row[0]] = row[1]

    return addons_match
