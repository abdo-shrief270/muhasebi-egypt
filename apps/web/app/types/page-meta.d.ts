declare module '#app' {
  interface PageMeta {
    /** Page is reachable without logging in. */
    guest?: boolean
    /** Key of the module this page belongs to; hidden when the module is not usable. */
    module?: string
  }
}

export {}
