import type { OnlineOrder, OnlineOrderDetail, OnlineOrderStatus } from '~/types/api'

/** Badge colours for the online orders' statuses. */
export const ONLINE_ORDER_COLORS: Record<OnlineOrderStatus, 'info' | 'warning' | 'primary' | 'success' | 'neutral' | 'error'> = {
  new: 'warning',
  confirmed: 'info',
  preparing: 'primary',
  out_for_delivery: 'primary',
  ready: 'primary',
  delivered: 'success',
  cancelled: 'neutral',
}

export function onlineOrderFulfilment(order: Pick<OnlineOrder, 'fulfilment' | 'zone_name'>): string {
  return order.fulfilment === 'delivery' ? `توصيل${order.zone_name ? ` — ${order.zone_name}` : ''}` : 'استلام من المحل'
}

export function onlineOrderPayment(order: Pick<OnlineOrder, 'payment' | 'fulfilment'>): string {
  return order.payment === 'transfer' ? 'تحويل (InstaPay / محفظة)' : order.fulfilment === 'delivery' ? 'كاش عند الاستلام' : 'كاش في المحل'
}

/** The built-in WhatsApp wording, while the shop's templates haven't loaded. */
export function onlineOrderText(order: OnlineOrderDetail, shop: string): string {
  const hello = `أهلاً أستاذ/ة ${order.customer_name} 👋`
  const link = order.track_url ? `تابع طلبك من هنا: ${order.track_url}` : ''
  const lines: Record<OnlineOrderStatus, string[]> = {
    new: [hello, `استلمنا طلبك ${order.reference} من ${shop}.`, link],
    confirmed: [hello, `طلبك ${order.reference} من ${shop} اتأكد ✅`, `الإجمالي: ${formatMoney(order.total)}`, link],
    preparing: [hello, `بنجهّز طلبك ${order.reference} دلوقتي.`, link],
    out_for_delivery: [hello, `طلبك ${order.reference} خرج للتوصيل 🛵`, `جهّز ${formatMoney(order.total)} للمندوب.`, link],
    ready: [hello, `طلبك ${order.reference} جاهز تستلمه من ${shop} ✅`, `المطلوب: ${formatMoney(order.total)}`, link],
    delivered: [hello, `شكراً لطلبك من ${shop} 🌷`],
    cancelled: [hello, `آسفين، طلبك ${order.reference} من ${shop} اتلغى.`, order.cancel_reason ? `السبب: ${order.cancel_reason}` : ''],
  }
  return lines[order.status].filter(Boolean).join('\n')
}
