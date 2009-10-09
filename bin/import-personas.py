from datetime import datetime
import operator
import time

import lockfile
from sqlalchemy.ext.sqlsoup import SqlSoup

import utils


# The addontype ID for Personas.
PERSONAS_TYPE = 9

# Config key where we remember last time we ran.
LAST_UPDATE_KEY = 'personas_updated'

# Number of personas to import per-transaction.
CHUNK_SIZE = 50

DATETIME_FORMAT = '%Y-%m-%d %H:%M:%S'

STATUS_PUBLIC = 4
STATUS_DISABLED = 5

SUPPORTED_APPS = [1, 18]

# Database connectors that create fresh connections.
_personas_db = lambda: SqlSoup(utils.alchemy_config('personas'))
_amo_db = lambda: SqlSoup(utils.alchemy_config('write'))

# Our databases.
personas_db = _personas_db()
amo_db = _amo_db()

# Some useful tables.
Persona = personas_db.personas
AmoPersona = amo_db.personas
Addon = amo_db.addons

# Personas attributes we want to transfer (not including translated strings).
PERSONA_ATTRS = ['header', 'footer', 'accentcolor', 'textcolor', 'author',
                 'display_username', 'submit', 'approve']

# Oh lisp!
car = operator.itemgetter(0)


def add_personas_type():
    """Add personas to the addontypes table."""
    db = amo_db.engine.connect()

    exists = db.scalar('SELECT COUNT(*) FROM addontypes WHERE id=%s',
                       PERSONAS_TYPE)
    if not exists:
        name = add_translation(db, 'Persona')
        name_plural = add_translation(db, 'Personas')
        description = add_translation(db, 'Personas are lightweight, easy-to-install and easy-to-change "skins" for your Firefox web browser.')
        db.execute("""INSERT INTO addontypes
                        (id, name, name_plural, description, created, modified)
                      VALUE (%s, %s, %s, %s, NOW(), NOW())""",
                   PERSONAS_TYPE, name, name_plural, description)


def add_translation(db, string, locale='en-US'):
    """Add a string to the translations table."""
    if string is None:
        return None

    db.execute('UPDATE translations_seq SET id=LAST_INSERT_ID(id+1)')
    id = db.scalar('SELECT LAST_INSERT_ID() AS id FROM translations_seq')
    db.execute("""INSERT INTO translations
                    (id, locale, localized_string, created) VALUE
                  (%s, %s, %s, NOW())""", id, locale, string)
    return id


def update_translation(db, id, string, locale='en-US'):
    db.execute("""UPDATE translations SET localized_string=%s
                  WHERE id=%s AND locale=%s""", string, id, locale)


def add_persona(db, persona):
    """
    Create an Addon for persona and return a dict of persona data to be added
    (in batch) later.
    """
    name = add_translation(db, persona.name)
    description = add_translation(db, persona.description)
    db.execute(amo_db.addons._table.insert(),
               {'name': name, 'description': description,
                'status': STATUS_PUBLIC, 'created': persona.approve,
                'addontype_id': PERSONAS_TYPE})
    addon = db.scalar('SELECT LAST_INSERT_ID() FROM addons')

    p = {'addon_id': addon, 'name': name, 'description': description,
         'persona_id': persona.id, 'category': persona.category}
    p.update(dict((k, getattr(persona, k)) for k in PERSONA_ATTRS))
    return p


def update_personas(db, ids):
    """Update a batch of personas."""
    new_persona = lambda id: Persona.filter(Persona.id==id).one()
    for old_persona in AmoPersona.filter(AmoPersona.persona_id.in_(ids)):
        update_persona(db, old_persona, new_persona(old_persona.persona_id))


def update_persona(db, old_persona, new_persona):
    # Update the translated fields.
    for attr in 'name', 'description':
        update_translation(db, getattr(old_persona, attr),
                           getattr(new_persona, attr))

    # Update all other fields.
    p = dict((k, getattr(new_persona, k)) for k in PERSONA_ATTRS)
    # TODO: update categories
    # p['category'] = new_persona.category
    db.execute(AmoPersona._table.update()
               .where(AmoPersona.persona_id==new_persona.id)
               .values(**p))


def needs_import():
    """Find the personas that need to be imported."""
    all = vals(Persona.id, Persona.filter(Persona.status == 1))
    imported = vals(AmoPersona.persona_id, AmoPersona)
    return list(set(all).difference(imported))


def import_personas(db, ids):
    """Import the personas with the given ids."""
    to_insert = []
    for persona in Persona.filter(Persona.id.in_(ids)):
        to_insert.append(add_persona(db, persona))
    db.execute(AmoPersona._table.insert(), to_insert)

    db.execute(amo_db.addons_categories._table.insert(),
        [{'addon_id': d['addon_id'],
          'category_id': CATEGORIES[app][d['category']]}
         for d in to_insert for app in SUPPORTED_APPS
         if CATEGORIES[app][d['category']] is not None])


def needs_update(last_update):
    # This must be the first run, so there won't be any updates.
    if last_update is None:
        return []

    # Can't use log as a class because it doesn't have a pk.
    ids = map(car, personas_db.engine.execute("""
        SELECT DISTINCT id FROM log
        WHERE action='EditApproved' AND date > %s""", last_update).fetchall())

    return vals(Persona.id, Persona.filter(Persona.id.in_(ids))
                                   .filter(Persona.status == 1))


def pull_personas():
    """Disable all AMO personas that aren't public in the Personas db."""
    pulled = vals(Persona.id, Persona.filter(Persona.status != 1))
    addons = vals(AmoPersona.addon_id,
                  AmoPersona.filter(AmoPersona.persona_id.in_(pulled)))

    amo_db.engine.execute(Addon._table.update()
                          .where(Addon.id.in_(addons))
                          .values(status=STATUS_DISABLED))


def unpull_personas():
    """Re-enable all disabled AMO personas that are now public."""
    disabled = vals(AmoPersona.persona_id,
                    Addon.filter(Addon.status == STATUS_DISABLED)
                         .join(AmoPersona))
    unpull = vals(Persona.id, Persona.filter(Persona.status == 1)
                                     .filter(Persona.id.in_(disabled)))

    addons = vals(AmoPersona.addon_id,
                  AmoPersona.filter(AmoPersona.persona_id.in_(unpull)))

    amo_db.engine.execute(Addon._table.update()
                          .where(Addon.id.in_(addons))
                          .values(status=STATUS_PUBLIC))


def vals(value, query):
    """Return a list of the ``value`` column from ``query``."""
    return map(car, query.values(value))

# By Ned Batchelder.
def chunked(seq, n):
    """
    Yield successive n-sized chunks from seq.

    >>> for group in chunked(range(8), 3):
    ...     print group
    [0, 1, 2]
    [3, 4, 5]
    [6, 7]
    """
    for i in xrange(0, len(seq), n):
        yield seq[i:i+n]


def _last_update():
    # Update has to make fresh connections because import probably took a long
    # time and our mysql connection timed out.
    Config = _amo_db().config
    last_update = (Config.filter(Config.key==LAST_UPDATE_KEY)
                   .value(Config.value))
    if last_update is None:
        return None
    else:
        return datetime.strptime(last_update, DATETIME_FORMAT)


def _set_last_update(last_update):
    Config = _amo_db().config
    now = datetime.now().strftime(DATETIME_FORMAT)
    if last_update is None:
        sql = Config._table.insert()
    else:
        sql = Config._table.update().where(Config.key==LAST_UPDATE_KEY)
    amo_db.engine.execute(sql, {'key': LAST_UPDATE_KEY, 'value': now})


def _persona_categories():
    """
    Generate a mapping of persona category names to ids, adding categories if
    necessary.
    """

    def _amo_categories(db):
        result = db.execute(
            """SELECT T.localized_string, P.id, P.application_id
               FROM categories P INNER JOIN translations T
               ON P.name = T.id AND T.locale='en-US'
               AND P.addontype_id=%s""", PERSONAS_TYPE).fetchall()
        # Some personas have a NULL category, so include None.
        categories = dict((app, {None: None}) for app in SUPPORTED_APPS)
        for name, id, app in result:
            categories[app][name] = id
        return categories

    persona_categories = map(car, personas_db.engine.execute(
        """SELECT DISTINCT category FROM personas
           WHERE category IS NOT NULL""").fetchall())

    db = amo_db.engine.connect()
    amo_categories = _amo_categories(db)

    # Add any missing categories.
    for category in persona_categories:
        missing = [app for app in SUPPORTED_APPS
                   if category not in amo_categories[app]]
        if missing:
            name = add_translation(db, category)
            db.execute(amo_db.categories._table.insert(),
                       [{'name': name, 'addontype_id': PERSONAS_TYPE,
                         'application_id': app} for app in missing])

    return _amo_categories(db)

CATEGORIES = _persona_categories()


def main():
    start = time.time()

    add_personas_type()
    utils.debug('Importing')
    for index, ids in enumerate(chunked(needs_import(), CHUNK_SIZE)):
        utils.debug('%s: %s' % (index * CHUNK_SIZE, time.time() - start))
        amo_db.engine.transaction(import_personas, None, ids)

    # Update the global Persona reference since it's probably been disconnected
    # from the server during import..
    global Persona
    Persona = _personas_db().personas

    last_update = _last_update()
    utils.debug('Updating')
    for index, ids in enumerate(chunked(needs_update(last_update), CHUNK_SIZE)):
        utils.debug('%s: %s' % (index * CHUNK_SIZE, time.time() - start))
        amo_db.engine.transaction(update_personas, None, ids)

    utils.debug('Pulling disabled personas')
    utils.debug(time.time() - start)
    pull_personas()

    utils.debug('Re-enabling disabled personas')
    utils.debug(time.time() - start)
    unpull_personas()

    _set_last_update(last_update)


def _main():
    """Run main() with a lockfile."""
    lock = lockfile.FileLock(__name__)
    try:
        # Don't block.
        lock.acquire(0)
    except lockfile.LockError:
        utils.debug('Aborting personas import; could not acquire lock.')
        return

    try:
        main()
    finally:
        lock.release()


if __name__ == '__main__':
    _main()
