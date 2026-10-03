export interface Images { [width: string]: string }

export interface PublicStore {
  slug: string
  mode: 'off' | 'whatsapp' | 'orders'
  name: string
  tagline: string | null
  about: string | null
  color: string
  whatsapp: string | null
  phone: string | null
  address: string | null
  map_url: string | null
  hours: string | null
  policy: string | null
  facebook: string | null
  instagram: string | null
  show_quantity: boolean
  logo: Images | null
  cover: Images | null
  updated_at: string
}

export type Availability = 'in' | 'low' | 'out'

export interface ProductCard {
  id: string
  name: string
  brand: string | null
  category: { id: number, name: string }
  image: { id: string, width: number, height: number, urls: Images } | null
  price: number
  price_max: number
  qualities: string[]
  availability: Availability
  quantity?: number
}

export interface ProductVariant {
  id: string
  name: string | null
  quality: string | null
  quality_label: string | null
  price: number
  availability: Availability
  quantity?: number
}

export interface ProductDetail extends ProductCard {
  description: string | null
  images: { id: string, width: number, height: number, urls: Images }[]
  variants: ProductVariant[]
  device_models: { id: number, full_name: string }[]
  updated_at: string | null
}

export interface StoreHome {
  store: PublicStore
  categories: { id: number, name: string, products: number }[]
  device_brands: { id: number | null, name: string, models: { id: number, name: string, products: number }[] }[]
  latest: ProductCard[]
}

export interface ProductPage {
  data: ProductCard[]
  meta: { total: number, page: number, per_page: number }
}
