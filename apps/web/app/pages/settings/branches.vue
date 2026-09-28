<template>
  <div class="space-y-6 max-w-4xl">
    <PageHeader title="الفروع" description="كل فرع ليه مخزون وخزنة وترقيم فواتير خاص بيه.">
      <UButton icon="i-lucide-plus" label="فرع جديد" @click="openForm()" />
    </PageHeader>

    <UAlert
      v-if="needsModule"
      color="warning"
      variant="subtle"
      icon="i-lucide-lock"
      title="الفرع التاني محتاج قسم «الفروع المتعددة»"
      description="جرّبه مجاناً 14 يوم من صفحة الأقسام."
      :actions="store.isOwner ? [{ label: 'الأقسام', to: '/settings/modules?need=multi_branch' }] : []"
    />

    <div class="grid gap-4 sm:grid-cols-2">
      <UCard v-for="b in branches" :key="b.id" :class="{ 'opacity-60': !b.is_active }">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <div class="size-10 grid place-items-center rounded-(--ui-radius) app-soft">
              <UIcon name="i-lucide-store" class="size-5" />
            </div>
            <div>
              <p class="font-bold">
                {{ b.name }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                {{ b.address || 'من غير عنوان' }}
              </p>
              <p class="text-sm text-(--ui-text-muted)">
                ترقيم الفواتير <span class="num">{{ b.invoice_prefix }}</span>
              </p>
            </div>
          </div>
          <div class="flex flex-col items-end gap-2">
            <UBadge v-if="b.is_main" color="primary" variant="subtle">
              الرئيسي
            </UBadge>
            <UBadge v-else-if="!b.is_active" color="neutral" variant="subtle">
              موقوف
            </UBadge>
            <UButton size="sm" color="neutral" variant="ghost" icon="i-lucide-pencil" label="تعديل" @click="openForm(b)" />
          </div>
        </div>
      </UCard>
    </div>

    <UModal v-model:open="formOpen" :title="editing ? `تعديل «${editing.name}»` : 'فرع جديد'">
      <template #body>
        <form id="branch-form" class="space-y-4" @submit.prevent="save">
          <UFormField label="اسم الفرع" required>
            <UInput v-model="form.name" class="w-full" />
          </UFormField>
          <UFormField label="العنوان">
            <UInput v-model="form.address" class="w-full" />
          </UFormField>
          <UFormField label="تليفون الفرع">
            <UInput v-model="form.phone" dir="ltr" inputmode="tel" class="w-full" />
          </UFormField>
          <USwitch v-if="editing && !editing.is_main" v-model="form.is_active" label="الفرع شغال" />
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="formOpen = false" />
          <UButton type="submit" form="branch-form" :loading="saving" label="حفظ" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { ApiError, Branch } from '~/types/api'

definePageMeta({ permission: 'branches.manage' })

const api = useApi()
const store = useSessionStore()
const toast = useToast()

const { data, refresh } = await useAsyncData('branches', () => api<{ data: Branch[] }>('/branches'))
const branches = computed(() => data.value?.data ?? [])

const formOpen = ref(false)
const editing = ref<Branch | null>(null)
const saving = ref(false)
const error = ref<string | null>(null)
const needsModule = ref(false)
const form = reactive({ name: '', address: '', phone: '', is_active: true })

function openForm(branch?: Branch) {
  editing.value = branch ?? null
  error.value = null
  Object.assign(form, { name: branch?.name ?? '', address: branch?.address ?? '', phone: branch?.phone ?? '', is_active: branch?.is_active ?? true })
  formOpen.value = true
}

async function save() {
  saving.value = true
  error.value = null
  try {
    const body = { ...form, address: form.address || null, phone: form.phone || null }
    if (editing.value) {
      await api(`/branches/${editing.value.id}`, { method: 'PATCH', body })
    }
    else {
      await api('/branches', { method: 'POST', body })
    }
    formOpen.value = false
    toast.add({ color: 'success', title: 'اتحفظ' })
    await Promise.all([refresh(), store.load()])
  }
  catch (e) {
    if ((e as { data?: ApiError }).data?.code === 'multi_branch_required') {
      needsModule.value = true
      formOpen.value = false
    }
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
