import type { RouterConfig } from '@nuxt/schema'

/**
 * On a store's own subdomain (elnour.muhasebi.com) or the shop's own domain its pages sit at the
 * root: the /:slug parent route becomes "/" and the slug comes from the host. On the shared domain the routes stay
 * /:slug/… (and the server sends those to the subdomain when a store host is set).
 */
export default <RouterConfig>{
  routes: (routes) => {
    if (!useStoreAtRoot()) {
      return routes
    }
    const store = routes.find(r => r.path === '/:slug()')
    return store ? [{ ...store, path: '/' }] : routes
  },
}
