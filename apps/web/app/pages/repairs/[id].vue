<template>
  <div v-if="ticket" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center gap-3">
      <UButton to="/repairs" color="neutral" variant="ghost" icon="i-lucide-arrow-right" square aria-label="رجوع" />
      <div class="min-w-0 flex-1">
        <h1 class="flex flex-wrap items-center gap-2 text-2xl font-extrabold">
          <span class="num">{{ ticket.reference }}</span>
          <UBadge :color="ticketStatusColor(ticket.status)" variant="subtle" size="lg">
            {{ ticket.status_label }}
          </UBadge>
          <UBadge v-if="ticket.is_overdue" color="error" variant="soft" icon="i-lucide-alarm-clock">
            متأخر
          </UBadge>
          <UBadge v-if="ticket.warranty_of_id" color="info" variant="soft" icon="i-lucide-shield-check">
            رجوع في الضمان
          </UBadge>
        </h1>
        <p class="text-(--ui-text-muted)">
          {{ ticket.device_name }} · استلمه {{ ticket.received_by_name ?? '—' }} {{ formatDate(ticket.received_at, true) }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton
          v-if="canMessage && ticket.status === 'ready' && !ticket.ready_notified_at"
          color="warning"
          icon="i-lucide-message-circle"
          label="بلّغ العميل إنه جاهز"
          @click="notify(ticket)"
        />
        <UButton v-else-if="canMessage" color="neutral" variant="outline" icon="i-lucide-message-circle" label="واتساب" @click="notify(ticket)" />
        <UButton color="neutral" variant="outline" icon="i-lucide-printer" label="الإيصال" @click="print" />
        <RepairsLabelPrint :ticket="ticket" color="neutral" variant="outline" />
        <UButton v-if="canWork && ticket.next_statuses.length" color="neutral" variant="outline" icon="i-lucide-arrow-left-right" label="تغيير الحالة" @click="openStatus(null)" />
        <UButton v-if="canWork && ticket.next_statuses.some(s => s.value === 'ready')" color="success" variant="soft" icon="i-lucide-check-circle" label="جاهز" @click="openStatus('ready')" />
        <UButton v-if="canOutsource && !['delivered', 'rejected'].includes(ticket.status) && !ticket.outsourced?.active" color="neutral" variant="outline" icon="i-lucide-send" label="ابعته لمحل شريك" @click="outsourceOpen = true" />
        <UButton v-if="canDeliver && ticket.can_deliver" icon="i-lucide-hand-helping" label="تسليم للعميل" @click="deliverOpen = true" />
        <UButton v-if="ticket.under_warranty && store.can('repairs.create') && store.hasFeature('repairs.warranty')" color="warning" variant="soft" icon="i-lucide-shield-alert" label="رجوع في الضمان" :loading="returning" @click="warrantyReturn" />
      </div>
    </div>

    <UAlert
      v-if="ticket.outsourced"
      :color="ticket.outsourced.active && ticket.outsourced.status !== 'delivered' ? 'info' : 'neutral'"
      variant="subtle"
      icon="i-lucide-send"
      :title="`${ticket.outsourced.active ? 'الجهاز عند' : 'اتبعت لـ'} «${ticket.outsourced.shop}» — ${partnerStatusLabel(ticket.outsourced.status)}`"
      :description="ticket.outsourced.cost ? `حساب المحل: ${formatMoney(ticket.outsourced.cost)} (بيتحسب تكلفة على التذكرة)` : undefined"
      :actions="store.can('shop_orders.view') ? [{ label: `طلب ${ticket.outsourced.reference}`, to: `/shop-orders/${ticket.outsourced.order_id}`, color: 'neutral', variant: 'outline' }] : []"
    />
    <UAlert
      v-if="ticket.partner"
      color="info"
      variant="subtle"
      icon="i-lucide-handshake"
      title="جاي من محل شريك"
      :description="`العميل هنا هو المحل. لما تغيّر الحالة أو تسلّمه، طلبه ${ticket.partner.reference} بيتحدّث لوحده.`"
      :actions="store.can('shop_orders.view') ? [{ label: `طلب ${ticket.partner.reference}`, to: `/shop-orders/${ticket.partner.order_id}`, color: 'neutral', variant: 'outline' }] : []"
    />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <div class="space-y-6">
        <!-- Faults and diagnosis -->
        <UCard>
          <p class="mb-2 font-bold">
            العطل
          </p>
          <p class="text-xs text-(--ui-text-muted)">
            حسب كلام العميل
          </p>
          <div class="mt-1 flex flex-wrap gap-1.5">
            <UBadge v-for="f in ticket.reported_faults" :key="f.id" color="neutral" variant="subtle">
              {{ f.category }} · {{ f.name }}
            </UBadge>
          </div>
          <p v-if="ticket.reported_note" class="mt-2 text-sm">
            {{ ticket.reported_note }}
          </p>

          <USeparator class="my-4" />
          <div class="flex items-center justify-between">
            <p class="text-xs text-(--ui-text-muted)">
              تشخيص الفني
            </p>
            <UButton v-if="canWork && ticket.status !== 'delivered' && !editingDiagnosis" size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" label="تعديل" @click="startDiagnosis" />
          </div>
          <template v-if="editingDiagnosis && options">
            <RepairsFaultPicker v-model="diagnosis.fault_ids" :categories="options.faults" class="mt-2" />
            <UTextarea v-model="diagnosis.note" :rows="2" placeholder="اللي لقيته" class="mt-3 w-full" />
            <div class="mt-2 flex gap-2">
              <UButton size="sm" label="حفظ التشخيص" :loading="saving" @click="saveDiagnosis" />
              <UButton size="sm" color="neutral" variant="ghost" label="إلغاء" @click="editingDiagnosis = false" />
            </div>
          </template>
          <template v-else>
            <div v-if="ticket.diagnosed_faults?.length" class="mt-1 flex flex-wrap gap-1.5">
              <UBadge v-for="f in ticket.diagnosed_faults" :key="f.id" color="primary" variant="subtle">
                {{ f.category }} · {{ f.name }}
              </UBadge>
            </div>
            <p v-if="ticket.diagnosis_note" class="mt-2 text-sm">
              {{ ticket.diagnosis_note }}
            </p>
            <p v-if="!ticket.diagnosed_faults?.length && !ticket.diagnosis_note" class="mt-1 text-sm text-(--ui-text-muted)">
              لسه متفحصش.
            </p>
          </template>
        </UCard>

        <!-- Parts -->
        <UCard>
          <div class="mb-3 flex items-center justify-between">
            <p class="font-bold">
              قطع الغيار
            </p>
            <span class="text-sm text-(--ui-text-muted)">بتتخصم من مخزون الفرع</span>
          </div>
          <div v-if="(canAddPart || canRequestPart) && ticket.status !== 'delivered'" class="relative mb-3">
            <UInput v-model="partTerm" icon="i-lucide-search" :placeholder="canAddPart ? 'امسح أو دوّر على القطعة…' : 'دوّر على القطعة اللي محتاجها من المخزن…'" class="w-full" @keydown.enter.prevent="addFirstPart" />
            <div v-if="partResults.length && partTerm" class="absolute inset-x-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded-(--ui-radius) border border-(--ui-border) bg-(--ui-bg) shadow-lg">
              <button v-for="p in partResults" :key="p.id" type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-start text-sm hover:bg-(--ui-bg-elevated)" @click="pickPart(p)">
                <span class="font-bold">{{ p.display_name }}</span>
                <span class="shrink-0 text-(--ui-text-muted)"><template v-if="p.technician_only">فني </template><span class="num">{{ formatMoney(p.technician_only ? p.prices.technician : p.prices.retail) }}</span><template v-if="p.stock"> · مخزون <span class="num">{{ p.stock.find(s => s.current)?.qty ?? 0 }}</span></template></span>
              </button>
            </div>
          </div>
          <div v-if="requesting" class="mb-3 space-y-2 rounded-(--ui-radius) border border-(--ui-border) p-3">
            <p class="text-sm font-bold">
              اطلب «{{ requesting.display_name }}» من المخزن
              <span v-if="requesting.technician_only" class="font-normal text-(--ui-text-muted)">· سعر الفني <span class="num">{{ formatMoney(requesting.prices.technician) }}</span></span>
            </p>
            <div class="flex flex-wrap items-end gap-2">
              <UFormField label="العدد" class="w-24">
                <UInput v-model.number="requestQty" type="number" min="1" max="100" class="num" />
              </UFormField>
              <UFormField label="ملاحظة للمخزن (اختياري)" class="min-w-0 flex-1">
                <UInput v-model="requestNote" maxlength="190" placeholder="مثلاً: الأصلي لو موجود" class="w-full" />
              </UFormField>
            </div>
            <div class="flex justify-end gap-2">
              <UButton size="sm" color="neutral" variant="ghost" label="إلغاء" @click="requesting = null" />
              <UButton size="sm" icon="i-lucide-send" label="ابعت الطلب" :loading="requestBusy" :disabled="!(requestQty >= 1)" @click="sendRequest" />
            </div>
          </div>
          <div v-if="serialPart" class="mb-3 space-y-2 rounded-(--ui-radius) border border-(--ui-border) p-3">
            <p class="text-sm font-bold">
              IMEI / سيريال «{{ serialPart.display_name }}»
            </p>
            <InventorySerialsInput v-model="partSerials" :required="1" size="sm" />
            <div class="flex justify-end gap-2">
              <UButton size="sm" color="neutral" variant="ghost" label="إلغاء" @click="serialPart = null" />
              <UButton size="sm" icon="i-lucide-plus" label="إضافة القطعة" :disabled="partSerials.length !== 1" @click="addPart(serialPart, partSerials)" />
            </div>
          </div>
          <ul class="divide-y divide-(--ui-border)">
            <li v-for="p in ticket.parts" :key="p.id" class="flex items-center justify-between gap-3 py-2 text-sm">
              <span>
                <span class="num">{{ p.qty }}</span> × {{ p.name }}
                <span v-if="p.serials?.length" class="block text-xs text-(--ui-text-muted) num" dir="ltr">{{ p.serials.join(' · ') }}</span>
                <span v-if="p.list_price !== null && p.list_price !== p.unit_price" class="block text-xs text-(--ui-text-muted)">
                  سعر المحل <s class="num">{{ formatMoney(p.list_price) }}</s><template v-if="p.price_note"> · {{ p.price_note }}</template>
                </span>
              </span>
              <span class="flex items-center gap-2">
                <span class="font-bold num">{{ formatMoney(p.line_total) }}</span>
                <UPopover v-if="canPrice && ticket.status !== 'delivered'" :content="{ align: 'end' }" @update:open="(open: boolean) => open && startPrice(p)">
                  <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-pencil" square :aria-label="`غيّر سعر ${p.name}`" />
                  <template #content>
                    <form class="w-64 space-y-3 p-3" @submit.prevent="savePrice(p.id)">
                      <p class="text-sm font-bold">
                        سعر {{ p.name }} للعميل ده
                      </p>
                      <UFormField label="سعر القطعة (ج)">
                        <UInput v-model="priceInput" type="number" min="0" step="any" dir="ltr" class="w-full" autofocus />
                      </UFormField>
                      <UFormField label="السبب (اختياري)">
                        <UInput v-model="priceNote" maxlength="120" placeholder="عميل قديم" class="w-full" />
                      </UFormField>
                      <UButton type="submit" block label="احفظ السعر" :loading="pricing" :disabled="priceInput === ''" />
                    </form>
                  </template>
                </UPopover>
                <UDropdownMenu
                  v-if="canWork && ticket.status !== 'delivered' && binOn"
                  :items="[[
                    { label: 'شيلها (ترجع المخزون)', icon: 'i-lucide-package-plus', onSelect: () => removePart(p.id) },
                    { label: 'القطعة طلعت بايظة (لسلة المرتجعات)', icon: 'i-lucide-undo-2', color: 'warning', onSelect: () => removePart(p.id, true) },
                  ]]"
                  :content="{ align: 'end' }"
                >
                  <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" square :aria-label="`شيل ${p.name}`" />
                </UDropdownMenu>
                <UButton v-else-if="canWork && ticket.status !== 'delivered'" size="xs" color="neutral" variant="ghost" icon="i-lucide-trash-2" square :aria-label="`شيل ${p.name}`" @click="removePart(p.id)" />
              </span>
            </li>
            <li v-if="!ticket.parts?.length" class="py-3 text-center text-sm text-(--ui-text-muted)">
              مفيش قطع.
            </li>
          </ul>
          <div v-if="ticket.part_requests?.length" class="mt-3 space-y-2 border-t border-(--ui-border) pt-3">
            <p class="text-sm font-bold">
              طلبات من المخزن
            </p>
            <ul class="space-y-1.5">
              <li v-for="r in ticket.part_requests" :key="r.id" class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <span class="min-w-0">
                  <span class="num">{{ r.qty }}</span> × {{ r.name }}
                  <span class="block text-xs text-(--ui-text-muted)">
                    {{ r.requested_by_name }}<template v-if="r.note"> · {{ r.note }}</template>
                    <template v-if="r.status === 'rejected' && r.reason"> · <span class="text-(--ui-error)">{{ r.reason }}</span></template>
                  </span>
                </span>
                <span class="flex items-center gap-1.5">
                  <UBadge :color="r.status === 'requested' ? 'warning' : r.status === 'rejected' ? 'error' : 'neutral'" variant="subtle" size="sm" :label="r.status_label" />
                  <UButton v-if="r.status === 'requested' && canHandPart" size="xs" color="primary" variant="soft" icon="i-lucide-package-check" label="اصرف" to="/repairs/parts" />
                  <UButton v-if="r.status === 'requested' && (canHandPart || r.requested_by === store.session?.user.id)" size="xs" color="neutral" variant="ghost" icon="i-lucide-x" :aria-label="`الغي طلب ${r.name}`" @click="cancelRequest(r.id)" />
                </span>
              </li>
            </ul>
          </div>
        </UCard>

        <!-- Device -->
        <UCard>
          <p class="mb-3 font-bold">
            الجهاز وهو داخل
          </p>
          <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div v-if="ticket.imei">
              <dt class="text-(--ui-text-muted)">
                IMEI
              </dt><dd class="num" dir="ltr">
                {{ ticket.imei }}
              </dd>
            </div>
            <div v-if="ticket.color">
              <dt class="text-(--ui-text-muted)">
                اللون
              </dt><dd>{{ ticket.color }}</dd>
            </div>
            <div v-if="ticket.accessories_labels.length">
              <dt class="text-(--ui-text-muted)">
                مع الجهاز
              </dt><dd>{{ ticket.accessories_labels.join('، ') }}</dd>
            </div>
            <div v-if="ticket.condition_labels.length">
              <dt class="text-(--ui-text-muted)">
                شكله
              </dt><dd class="text-warning">
                {{ ticket.condition_labels.join('، ') }}
              </dd>
            </div>
          </dl>
          <div v-if="Object.keys(ticket.checks).length && options" class="mt-3 flex flex-wrap gap-1.5">
            <UBadge
              v-for="c in options.checks.filter(c => ticket!.checks[c.value])"
              :key="c.value"
              :color="ticket.checks[c.value] === 'yes' ? 'success' : ticket.checks[c.value] === 'no' ? 'error' : 'neutral'"
              variant="subtle"
              :icon="ticket.checks[c.value] === 'yes' ? 'i-lucide-check' : ticket.checks[c.value] === 'no' ? 'i-lucide-x' : 'i-lucide-circle-help'"
            >
              {{ c.label }}
            </UBadge>
          </div>
          <div v-if="ticket.unlock_type !== 'none'" class="mt-4">
            <p class="text-sm text-(--ui-text-muted)">
              قفل الشاشة ({{ ({ pin: 'رقم سري', pattern: 'نمط', password: 'باسورد' } as Record<string, string>)[ticket.unlock_type] }})
            </p>
            <template v-if="ticket.unlock_code">
              <RepairsPatternInput v-if="ticket.unlock_type === 'pattern'" :model-value="ticket.unlock_code" readonly class="mt-2" />
              <p v-else class="mt-1 text-lg font-extrabold num" dir="ltr">
                {{ ticket.unlock_code }}
              </p>
            </template>
            <p v-else class="mt-1 text-sm text-(--ui-text-muted)">
              مخفي — بيظهر للفني بس.
            </p>
          </div>
        </UCard>

        <!-- Timeline -->
        <UCard>
          <p class="mb-3 font-bold">
            اللي حصل
          </p>
          <ol class="relative space-y-4 border-s border-(--ui-border) ps-4">
            <li v-for="(e, i) in [...(ticket.events ?? [])].reverse()" :key="i" class="relative">
              <span class="absolute -start-[1.4rem] top-1 size-2.5 rounded-full" :class="e.to_status ? 'bg-primary' : 'bg-(--ui-border-accented)'" />
              <p class="text-sm">
                <span class="font-bold">{{ e.to_status_label && e.type !== 'received' ? `${e.from_status_label ?? ''} ← ${e.to_status_label}` : e.type_label }}</span>
                <span v-if="e.note" class="text-(--ui-text-muted)"> — {{ e.note }}</span>
              </p>
              <p class="text-xs text-(--ui-text-muted)">
                {{ e.user_name }} · {{ formatDate(e.created_at, true) }}
              </p>
            </li>
          </ol>
        </UCard>
      </div>

      <!-- Side -->
      <div class="space-y-4">
        <UCard>
          <p class="text-sm text-(--ui-text-muted)">
            العميل
          </p>
          <NuxtLink v-if="store.can('customers.view')" :to="`/customers/${ticket.customer_id}`" class="block font-bold hover:text-primary">
            {{ ticket.customer_name }}
          </NuxtLink>
          <p v-else class="font-bold">
            {{ ticket.customer_name }}
          </p>
          <a v-if="ticket.customer_phone" :href="`tel:${ticket.customer_phone}`" class="block text-sm text-(--ui-text-muted) num hover:text-primary" dir="ltr">{{ localPhone(ticket.customer_phone) }}</a>

          <USeparator class="my-3" />
          <div class="space-y-3">
            <UFormField label="الفني">
              <USelect
                v-if="canWork && ticket.status !== 'delivered' && options"
                :model-value="ticket.technician_id ?? 'none'"
                :items="[{ label: 'مش متسند', value: 'none' }, ...options.technicians.map(t => ({ label: t.name, value: t.id }))]"
                class="w-full"
                @update:model-value="v => update({ technician_id: v === 'none' ? null : v })"
              />
              <p v-else class="text-sm">
                {{ ticket.technician_name ?? '—' }}
              </p>
            </UFormField>
            <UFormField label="ميعاد التسليم">
              <UInput
                v-if="canWork && ticket.status !== 'delivered'"
                :model-value="ticket.expected_at ? toLocalInput(ticket.expected_at) : ''"
                type="datetime-local"
                dir="ltr"
                class="w-full"
                @change="(e: Event) => update({ expected_at: (e.target as HTMLInputElement).value ? new Date((e.target as HTMLInputElement).value).toISOString() : null })"
              />
              <p v-else class="text-sm num">
                {{ ticket.expected_at ? formatDate(ticket.expected_at, true) : '—' }}
              </p>
            </UFormField>
          </div>
        </UCard>

        <UCard>
          <p class="mb-3 font-bold">
            الحساب
          </p>
          <div class="space-y-2 text-sm">
            <div v-if="ticket.estimate" class="flex justify-between text-(--ui-text-muted)">
              <span>التكلفة المبدئية</span><span class="num">{{ formatMoney(ticket.estimate) }}</span>
            </div>
            <div class="flex items-center justify-between gap-2">
              <span>المصنعية</span>
              <UInput
                v-if="canWork && ticket.status !== 'delivered'"
                v-model="labor"
                size="sm"
                type="number"
                min="0"
                step="any"
                dir="ltr"
                class="w-28"
                aria-label="المصنعية"
                @change="update({ labor: toPiasters(labor) ?? 0 })"
              />
              <span v-else class="num">{{ formatMoney(ticket.labor) }}</span>
            </div>
            <button
              v-if="canWork && ticket.status !== 'delivered' && ticket.suggested_labor && ticket.suggested_labor !== ticket.labor"
              type="button"
              class="text-xs text-primary"
              @click="update({ labor: ticket.suggested_labor })"
            >
              المقترح من الأعطال: <span class="num">{{ formatMoney(ticket.suggested_labor) }}</span>
            </button>
            <div class="flex justify-between">
              <span>قطع الغيار</span><span class="num">{{ formatMoney(ticket.parts_total) }}</span>
            </div>
            <div v-if="ticket.parts_cost !== null && ticket.parts_total" class="flex justify-between text-xs text-(--ui-text-muted)">
              <span>تكلفة القطع</span><span class="num">{{ formatMoney(ticket.parts_cost) }}</span>
            </div>
            <div class="flex items-center justify-between gap-2">
              <span>خصم</span>
              <UInput
                v-if="canWork && store.can('sales.discount') && ticket.status !== 'delivered'"
                v-model="discount"
                size="sm"
                type="number"
                min="0"
                step="any"
                dir="ltr"
                class="w-28"
                aria-label="خصم"
                @change="update({ discount: toPiasters(discount) ?? 0 })"
              />
              <span v-else class="num">{{ ticket.discount ? `−${formatMoney(ticket.discount)}` : '—' }}</span>
            </div>
            <div class="flex justify-between border-t border-(--ui-border) pt-2 text-base font-extrabold">
              <span>الإجمالي</span><span class="num">{{ formatMoney(ticket.total) }}</span>
            </div>
            <div v-if="ticket.paid" class="flex justify-between">
              <span>اتدفع</span><span class="num">{{ formatMoney(ticket.paid) }}</span>
            </div>
            <div v-if="ticket.credit" class="flex justify-between">
              <span>آجل</span><span class="num">{{ formatMoney(ticket.credit) }}</span>
            </div>
            <div class="flex justify-between text-lg font-extrabold" :class="ticket.due > 0 ? 'text-warning' : ''">
              <span>{{ ticket.due < 0 ? 'يرجع للعميل' : 'الباقي' }}</span><span class="num">{{ formatMoney(Math.abs(ticket.due)) }}</span>
            </div>
          </div>
          <p v-if="ticket.status === 'delivered' && ticket.commission !== null && ticket.commission_rule" class="mt-3 text-sm text-(--ui-text-muted)">
            عمولة {{ ticket.technician_name }}: <span class="font-bold text-(--ui-text) num">{{ formatMoney(ticket.commission) }}</span> ({{ ticket.commission_rule }})
          </p>
        </UCard>

        <UCard v-if="ticket.warranties?.length">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <p class="font-bold">
              الضمان
            </p>
            <UBadge :color="ticket.under_warranty ? 'success' : 'neutral'" variant="subtle">
              {{ ticket.under_warranty ? `شغال لحد ${formatDate(ticket.warranty_until)}` : 'خلص' }}
            </UBadge>
          </div>
          <ul class="divide-y divide-(--ui-border) text-sm">
            <li v-for="w in ticket.warranties" :key="w.id" class="flex flex-wrap items-center justify-between gap-2 py-2">
              <span>
                <span class="font-bold">{{ w.name }}</span> · {{ dayCount(w.days) }}
                <span v-if="w.state === 'voided'" class="block text-xs text-error">اتلغى: {{ w.void_reason }} ({{ w.voided_by_name }})</span>
              </span>
              <span class="flex items-center gap-2">
                <span class="num" :class="w.state === 'active' ? 'text-success' : 'text-(--ui-text-muted)'">لحد {{ formatDate(w.ends_at) }}</span>
                <UButton v-if="w.state === 'active' && store.can('repairs.settings')" size="xs" color="error" variant="ghost" label="إلغاء" @click="voidTarget = w" />
              </span>
            </li>
          </ul>
          <p v-if="ticket.warranty_defaults?.warranty_terms" class="mt-2 text-xs text-(--ui-text-muted)">
            {{ ticket.warranty_defaults.warranty_terms }}
          </p>
          <div class="mt-3 flex flex-wrap gap-2">
            <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-printer" label="اطبع كارت الضمان" @click="printWarranty" />
          </div>
        </UCard>

        <UModal :open="voidTarget !== null" title="إلغاء الضمان" @update:open="(v: boolean) => { if (!v) voidTarget = null }">
          <template #body>
            <form class="space-y-3" @submit.prevent="voidWarranty">
              <p class="text-sm">
                ضمان «{{ voidTarget?.name }}» هيتلغى من دلوقتي، والسبب هيتسجل في سجل العمليات.
              </p>
              <UFormField label="السبب" required>
                <UInput v-model="voidReason" maxlength="200" placeholder="الجهاز وقع / اتفتح برّه" class="w-full" autofocus />
              </UFormField>
              <UButton type="submit" color="error" block label="ألغِ الضمان" :loading="voiding" :disabled="!voidReason.trim()" />
            </form>
          </template>
        </UModal>

        <UCard v-if="canWork && ticket.status !== 'delivered'">
          <UFormField label="ملاحظة على التذكرة">
            <UTextarea v-model="note" :rows="2" class="w-full" />
          </UFormField>
          <UButton size="sm" class="mt-2" label="إضافة" :disabled="!note.trim()" @click="update({ note }).then(() => { note = '' })" />
        </UCard>
      </div>
    </div>

    <RepairsStatusModal v-model:open="statusOpen" :ticket="ticket" :preset="statusPreset" @changed="onChanged" />
    <RepairsDeliverModal v-model:open="deliverOpen" :ticket="ticket" @delivered="onChanged" />
    <RepairsOutsourceModal v-model:open="outsourceOpen" :ticket="ticket" @saved="onOutsourced" />
    <PrintSheet v-if="printing" :page-size="paper.page.value">
      <RepairsWarrantyCard v-if="printingWarranty" :ticket="ticket" :shop="shop" />
      <RepairsIntakeReceipt v-else :ticket="ticket" :shop="shop" />
    </PrintSheet>
  </div>
</template>

<script setup lang="ts">
import type { PriceCheckItem, RepairOptions, RepairTicket, RepairWarranty, TicketStatus } from '~/types/api'

definePageMeta({ module: 'repairs', permission: 'repairs.view' })

const paper = useThermalPaper()
const api = useApi()
const route = useRoute()
const store = useSessionStore()
const toast = useToast()
const id = computed(() => String(route.params.id))
const canWork = computed(() => store.can('repairs.update_status'))
const canDeliver = computed(() => store.can('repairs.deliver'))
const messages = useMessages()
const canOutsource = computed(() => store.can('repairs.update_status') && store.can('shop_orders.place') && store.hasFeature('repairs.outsourcing'))
// The owner's «رسايل واتساب للعميل» switch for repairs.
const canMessage = computed(() => store.hasFeature('repairs.status_whatsapp'))
const outsourceOpen = ref(false)

function notify(t: RepairTicket) {
  messages.sendTicket(t)
  if (t.status === 'ready' && data.value?.data) {
    data.value.data.ready_notified_at = new Date().toISOString()
  }
}
const shop = useReceiptShop()

const [{ data }, { data: optionsData }] = await Promise.all([
  useAsyncData(`repair-${id.value}`, () => api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}`)),
  useAsyncData('repair-options', () => api<{ data: RepairOptions }>('/repairs/options')),
])
const ticket = computed(() => data.value?.data)
const options = computed(() => optionsData.value?.data)

const labor = ref('')
const discount = ref('')
const note = ref('')
watch(ticket, (t) => {
  labor.value = t ? String(t.labor / 100) : ''
  discount.value = t?.discount ? String(t.discount / 100) : ''
}, { immediate: true })

function toLocalInput(iso: string) {
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const saving = ref(false)
async function update(body: Record<string, unknown>) {
  saving.value = true
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}`, { method: 'PATCH', body })
    data.value = res
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    saving.value = false
  }
}

// Diagnosis
const editingDiagnosis = ref(false)
const diagnosis = reactive({ fault_ids: [] as number[], note: '' })
function startDiagnosis() {
  diagnosis.fault_ids = (ticket.value?.diagnosed_faults ?? ticket.value?.reported_faults ?? []).map(f => f.id)
  diagnosis.note = ticket.value?.diagnosis_note ?? ''
  editingDiagnosis.value = true
}
async function saveDiagnosis() {
  await update({ diagnosed_fault_ids: diagnosis.fault_ids, diagnosis_note: diagnosis.note || null })
  editingDiagnosis.value = false
}

// Parts
const partTerm = ref('')
const partResults = ref<PriceCheckItem[]>([])
let partTimer: ReturnType<typeof setTimeout> | undefined
watch(partTerm, (value) => {
  clearTimeout(partTimer)
  if (value.trim().length < 2) {
    partResults.value = []
    return
  }
  partTimer = setTimeout(async () => {
    partResults.value = (await api<{ data: PriceCheckItem[] }>('/inventory/price-check', { query: { q: value.trim() } })).data
  }, 250)
})
onBeforeUnmount(() => clearTimeout(partTimer))

// A part whose product tracks serials asks which unit first.
const serialPart = ref<PriceCheckItem | null>(null)
const partSerials = ref<string[]>([])

// «الفني يطلب القطع من المخزن»: with it on, only whoever hands parts out adds them; a technician asks.
const partRequestsOn = computed(() => store.hasFeature('repairs.part_requests'))
const canHandPart = computed(() => partRequestsOn.value && store.can('repairs.hand_parts'))
const canAddPart = computed(() => canWork.value && (!partRequestsOn.value || store.can('repairs.hand_parts')))
const canRequestPart = computed(() => partRequestsOn.value && !canAddPart.value && store.can('repairs.request_parts'))
const requesting = ref<PriceCheckItem | null>(null)
const requestQty = ref(1)
const requestNote = ref('')
const requestBusy = ref(false)

function pickPart(item: PriceCheckItem) {
  if (canAddPart.value) {
    addPart(item)
    return
  }
  partTerm.value = ''
  partResults.value = []
  requesting.value = item
  requestQty.value = 1
  requestNote.value = ''
}

async function sendRequest() {
  if (!requesting.value) return
  requestBusy.value = true
  try {
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/part-requests`, { method: 'POST', body: { variant_id: requesting.value.id, qty: requestQty.value, note: requestNote.value.trim() || null } })
    requesting.value = null
    toast.add({ color: 'success', title: 'الطلب وصل المخزن', description: 'أول ما يتصرف هيظهر في القطع.' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    requestBusy.value = false
  }
}

async function cancelRequest(requestId: string) {
  try {
    await api(`/repairs/part-requests/${requestId}`, { method: 'DELETE' })
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}`)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

async function addPart(item: PriceCheckItem, serials?: string[]) {
  partTerm.value = ''
  partResults.value = []
  if (item.track_serial && !serials?.length) {
    serialPart.value = item
    partSerials.value = []
    return
  }
  try {
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/parts`, { method: 'POST', body: { variant_id: item.id, qty: 1, serials } })
    serialPart.value = null
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
function addFirstPart() {
  const hit = partResults.value.find(p => p.exact_barcode) ?? partResults.value[0]
  if (hit) {
    pickPart(hit)
  }
}
// With supplier returns on, a part that turned out bad goes to the returns bin instead of stock.
const binOn = computed(() => store.hasModule('supplier_returns'))
// «تغيير سعر القطعة»: this customer's price; under the shop's floor the API asks for a manager.
const canPrice = computed(() => store.can('repairs.part_price'))
const priceInput = ref('')
const priceNote = ref('')
const pricing = ref(false)
function startPrice(p: { unit_price: number, price_note: string | null }) {
  priceInput.value = String(p.unit_price / 100)
  priceNote.value = p.price_note ?? ''
}
async function savePrice(partId: number) {
  pricing.value = true
  try {
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/parts/${partId}`, {
      method: 'PATCH',
      body: { unit_price: toPiasters(priceInput.value) ?? 0, note: priceNote.value.trim() || null },
    })
    toast.add({ color: 'success', title: 'اتغيّر السعر' })
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    pricing.value = false
  }
}

async function removePart(partId: number, defective = false) {
  try {
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/parts/${partId}`, { method: 'DELETE', query: defective ? { defective: 1 } : undefined })
    if (defective) {
      toast.add({ color: 'success', title: 'القطعة راحت سلة مرتجعات الموردين' })
    }
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}

// Status and delivery
const statusOpen = ref(false)
const statusPreset = ref<TicketStatus | null>(null)
const deliverOpen = ref(false)
function openStatus(preset: TicketStatus | null) {
  statusPreset.value = preset
  statusOpen.value = true
}
function onOutsourced(updated: RepairTicket) {
  data.value = { data: updated }
}

function onChanged(updated: RepairTicket) {
  data.value = { data: updated }
  // Offer the matching WhatsApp message right away.
  toast.add({
    color: 'success',
    title: `بقت «${updated.status_label}»`,
    actions: canMessage.value ? [{ label: 'ابعت للعميل واتساب', icon: 'i-lucide-message-circle', onClick: () => notify(updated) }] : [],
  })
}

const returning = ref(false)
async function warrantyReturn() {
  returning.value = true
  try {
    const res = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}/warranty`, { method: 'POST', body: {} })
    await navigateTo(`/repairs/${res.data.id}`)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    returning.value = false
  }
}

const { printing, print } = usePrint()

// «الضمان»: the warranty card, and voiding an item (repairs.settings).
const printingWarranty = ref(false)
async function printWarranty() {
  printingWarranty.value = true
  await print()
  printingWarranty.value = false
}
const voidTarget = ref<RepairWarranty | null>(null)
const voidReason = ref('')
const voiding = ref(false)
async function voidWarranty() {
  if (!voidTarget.value) return
  voiding.value = true
  try {
    await api(`/repairs/warranties/${voidTarget.value.id}/void`, { method: 'POST', body: { reason: voidReason.value.trim() } })
    voidTarget.value = null
    voidReason.value = ''
    data.value = await api<{ data: RepairTicket }>(`/repairs/tickets/${id.value}`)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    voiding.value = false
  }
}
</script>
