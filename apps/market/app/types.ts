/** Shapes of the API's public marketplace endpoints (prices in piasters). */

export interface Place {
  governorate: string | null
  governorate_label: string | null
  area: string | null
}

export interface LatLng { lat: number, lng: number }

export interface Offer {
  id: string
  variant_id: string | null
  product_id: string | null
  title: string
  brand: string | null
  category: string | null
  condition: 'new' | 'used'
  grade: string | null
  item_id?: string | null
  storage_gb?: number | null
  battery_health?: number | null
  color?: string | null
  price: number
  availability: 'in' | 'low'
  image: string | null
  shop: { slug: string | null, name: string | null }
  place: Place
  location: LatLng | null
  distance_km: number | null
}

export interface OfferDetail extends Offer {
  description: string | null
  images: Record<string, string>[] | number
  models: string[] | string | null
  category_name: string | null
}

export interface Facet { key: string, label: string, count: number }

export interface SearchResult {
  items: Offer[]
  total: number
  page: number
  per_page: number
  sort: string
  facets: Record<'category' | 'condition' | 'governorate' | 'brand', Facet[]> & Partial<Record<'grade' | 'storage', Facet[]>>
  price: { min: number | null, max: number | null }
}

export interface ShopRow {
  slug: string
  name: string
  branch_id: string
  governorate: string | null
  governorate_label: string | null
  area: string | null
  location: LatLng | null
  offers: number
  distance_km: number | null
}

export interface ShopPage {
  slug: string
  name: string
  phone: string
  store: ShopStore | null
  store_url: string | null
  branches: ShopRow[]
  offers: number
}

export interface Options {
  categories: { key: string, label: string }[]
  conditions: { key: string, label: string }[]
  governorates: { key: string, label: string }[]
  sorts: string[]
}

export interface MapPin extends LatLng { label: string, to?: string }

/** A shop's own store, when it has one open: where the cart orders (takes_orders) or a link. */
export interface ShopStore { slug: string, url: string, takes_orders: boolean, whatsapp: string | null }

export interface Ordering {
  pickup: boolean
  delivery: boolean
  min_order: number
  free_delivery_over: number | null
  pay_cod: boolean
  pay_transfer: boolean
  transfer_instapay: string | null
  transfer_wallet: string | null
  hours: { from: string, until: string } | null
  open_now: boolean
  coupons?: boolean
}

export interface Zone { id: string, name: string, fee: number }

export interface StoreCheckout {
  store: { slug: string, name: string, mode: string, whatsapp: string | null, ordering: Ordering | null }
  zones: Zone[]
}

export interface OrderView {
  reference: string
  status: string
  status_label: string
  customer_name: string
  fulfilment: 'pickup' | 'delivery'
  zone_name: string | null
  payment: 'cod' | 'transfer'
  subtotal: number
  coupon_code: string | null
  discount: number
  delivery_fee: number
  total: number
  cancel_reason: string | null
  items: { product_id: string, name: string, qty: number, unit_price: number, line_total: number }[]
  timeline: { status: string, label: string, at: string }[]
  created_at: string
  token?: string
}

/** A phone of the reference catalog. */
export interface Phone {
  slug: string
  brand: string
  series: string | null
  model: string
  name: string
  year: number | null
  storage_gb: number[]
  network: string | null
}

export interface PriceRange { min: number, max: number, offers: number, shops: number }

export interface PhonePrices { new: PriceRange | null, used: PriceRange | null, fits: number }

export interface PhoneRow extends Phone { prices: PhonePrices }

export interface PhonePage {
  phone: Phone & { aliases: string[] }
  prices: PhonePrices
  new: Offer[]
  used: Offer[]
}

/** A used device's page: what the shop's inspection found (never the seller or the IMEI). */
export interface UsedListing {
  grade: string
  grade_label: string
  battery_health: number | null
  storage: string | null
  storage_gb: number | null
  color: string | null
  photos: string[]
  checks: { label: string, ok: boolean }[]
  imei_registered: boolean
  listed_at: string
}
