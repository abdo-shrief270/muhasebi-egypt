import type { LatLng } from '~/types'

/**
 * Where the visitor is: their location (asked once, on a button) or a governorate they picked,
 * kept on this device. Pages render first without it (cached, the same for everyone) and re-sort
 * in the browser once it's known.
 */
export interface VisitorPlace {
  point: LatLng | null
  governorate: string | null
  label: string
}

const KEY = 'souq:place'

export function usePlace() {
  const place = useState<VisitorPlace | null>('place', () => null)
  const ready = useState('place-ready', () => false)
  const locating = ref(false)
  const error = ref<string | null>(null)

  function save(value: VisitorPlace | null) {
    place.value = value
    try {
      if (value) {
        localStorage.setItem(KEY, JSON.stringify(value))
      }
      else {
        localStorage.removeItem(KEY)
      }
    }
    catch {
      // Private mode: it lasts this visit.
    }
  }

  function load() {
    if (ready.value || import.meta.server) {
      return
    }
    try {
      const raw = localStorage.getItem(KEY)
      place.value = raw ? JSON.parse(raw) as VisitorPlace : null
    }
    catch {
      place.value = null
    }
    ready.value = true
  }

  /** Ask the browser where the visitor is. */
  function locate(): Promise<void> {
    error.value = null
    if (!navigator.geolocation) {
      error.value = 'المتصفح ده مش بيدي الموقع. اختار محافظتك.'
      return Promise.resolve()
    }
    locating.value = true
    return new Promise((resolve) => {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          save({ point: { lat: pos.coords.latitude, lng: pos.coords.longitude }, governorate: null, label: 'حواليك' })
          locating.value = false
          resolve()
        },
        () => {
          error.value = 'مقدرناش نعرف مكانك. اسمح بالموقع أو اختار محافظتك.'
          locating.value = false
          resolve()
        },
        { enableHighAccuracy: false, timeout: 10000, maximumAge: 10 * 60 * 1000 },
      )
    })
  }

  function chooseGovernorate(key: string, label: string) {
    save({ point: null, governorate: key, label })
  }

  /** What the API takes: the point, else the governorate. */
  const query = computed((): Record<string, string | number> => {
    const p = place.value
    if (p?.point) {
      return { lat: Number(p.point.lat.toFixed(4)), lng: Number(p.point.lng.toFixed(4)) }
    }
    return p?.governorate ? { governorate: p.governorate } : {}
  })

  return { place, ready, load, locate, chooseGovernorate, clear: () => save(null), locating, error, query }
}
