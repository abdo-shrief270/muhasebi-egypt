import type { ReceiptData, Sale } from '~/types/api'

/** The public link printed as a QR code and shared on WhatsApp. */
export function receiptUrl(token: string): string {
  return `${window.location.origin}/r/${token}`
}

export function receiptFromSale(sale: Sale, shop: { name: string, phone: string | null } | null, branch: string | null): ReceiptData {
  return {
    shop,
    branch,
    reference: sale.reference,
    completed_at: sale.completed_at,
    cashier_name: sale.cashier_name,
    customer_name: sale.customer_name,
    subtotal: sale.subtotal,
    discount: sale.discount,
    total: sale.total,
    paid: sale.paid,
    change: sale.change,
    refunded: sale.refunded,
    items: (sale.items ?? []).map(i => ({ name: i.name, qty: i.qty, unit_price: i.unit_price, discount: i.discount, line_total: i.line_total, returned_qty: i.returned_qty })),
    payments: (sale.payments ?? []).map(p => ({ method_label: p.method_label, amount: p.amount })),
  }
}

export function receiptWhatsappText(sale: Sale, shopName: string): string {
  return `شكراً لتعاملك مع ${shopName} 🌷\nفاتورتك ${sale.reference} بـ ${formatMoney(sale.total)}\n${receiptUrl(sale.public_token)}`
}
