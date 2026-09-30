/**
 * A tiny promise wrapper over IndexedDB for what the POS keeps on the device to sell offline:
 * `catalog` (one record per shop + branch: the whole sellable catalog) and `outbox` (sales made
 * offline, waiting to be sent). No dependency; everything fails soft when IndexedDB is missing.
 */
const DB_NAME = 'muhasebi-pos'
const DB_VERSION = 1

export type IdbStore = 'catalog' | 'outbox'

let opening: Promise<IDBDatabase> | null = null

function openDb(): Promise<IDBDatabase> {
  if (opening) {
    return opening
  }
  opening = new Promise<IDBDatabase>((resolve, reject) => {
    if (typeof indexedDB === 'undefined') {
      reject(new Error('IndexedDB is not available'))
      return
    }
    const req = indexedDB.open(DB_NAME, DB_VERSION)
    req.onupgradeneeded = () => {
      const db = req.result
      if (!db.objectStoreNames.contains('catalog')) {
        db.createObjectStore('catalog', { keyPath: 'scope' })
      }
      if (!db.objectStoreNames.contains('outbox')) {
        db.createObjectStore('outbox', { keyPath: 'id' })
      }
    }
    req.onsuccess = () => {
      const db = req.result
      // Another tab upgraded the schema: let it, and reopen next time.
      db.onversionchange = () => {
        db.close()
        opening = null
      }
      resolve(db)
    }
    req.onerror = () => reject(req.error)
    req.onblocked = () => reject(new Error('IndexedDB upgrade blocked'))
  })
  opening.catch(() => {
    opening = null
  })
  return opening
}

function done<T>(req: IDBRequest<T>): Promise<T> {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
}

async function store(name: IdbStore, mode: IDBTransactionMode): Promise<IDBObjectStore> {
  return (await openDb()).transaction(name, mode).objectStore(name)
}

export async function idbGet<T>(name: IdbStore, key: string): Promise<T | undefined> {
  return done((await store(name, 'readonly')).get(key)) as Promise<T | undefined>
}

export async function idbAll<T>(name: IdbStore): Promise<T[]> {
  return done((await store(name, 'readonly')).getAll()) as Promise<T[]>
}

export async function idbPut<T>(name: IdbStore, value: T): Promise<void> {
  await done((await store(name, 'readwrite')).put(value))
}

export async function idbDelete(name: IdbStore, key: string): Promise<void> {
  await done((await store(name, 'readwrite')).delete(key))
}
