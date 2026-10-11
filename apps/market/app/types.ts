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
  facets: Record<'category' | 'condition' | 'governorate' | 'brand', Facet[]>
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
