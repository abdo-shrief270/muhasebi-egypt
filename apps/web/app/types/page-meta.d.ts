declare module '#app' {
  interface PageMeta {
    /** Page is reachable without logging in. */
    guest?: boolean
    /** Page is for anyone, logged in or not (e.g. a customer's receipt). */
    public?: boolean
    /** Key of the module this page belongs to; hidden when the module is not usable. */
    module?: string
    /** Permission key the user needs to open the page. */
    permission?: string
    /** Only the shop owner may open the page. */
    ownerOnly?: boolean
  }
}

export {}
