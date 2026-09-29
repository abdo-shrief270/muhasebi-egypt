<template>
  <div class="space-y-6 max-w-5xl">
    <PageHeader title="الموظفين" description="كل موظف بيدخل برقم موبايله، وبيشوف اللي دوره يسمح بيه بس، في الفروع اللي تختارها.">
      <UButton icon="i-lucide-user-plus" label="موظف جديد" @click="openForm()" />
    </PageHeader>

    <UCard :ui="{ body: 'p-0 sm:p-0' }">
      <table class="w-full text-sm">
        <thead class="bg-(--ui-bg-elevated) text-(--ui-text-muted)">
          <tr>
            <th class="p-3 text-start font-bold">
              الاسم
            </th>
            <th class="p-3 text-start font-bold">
              الموبايل
            </th>
            <th class="p-3 text-start font-bold">
              الدور
            </th>
            <th class="p-3 text-start font-bold">
              الفروع
            </th>
            <th class="p-3 text-start font-bold">
              الحالة
            </th>
            <th class="p-3" />
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" class="border-t border-(--ui-border)" :class="{ 'opacity-60': !u.is_active }">
            <td class="p-3">
              <div class="flex items-center gap-3">
                <UAvatar :alt="u.name" size="sm" />
                <span class="font-bold">{{ u.name }}</span>
              </div>
            </td>
            <td class="p-3">
              <span class="num">{{ u.phone }}</span>
            </td>
            <td class="p-3">
              <UBadge v-if="u.is_owner" color="primary" variant="subtle">
                صاحب المحل
              </UBadge>
              <span v-else>{{ u.role?.name ?? '—' }}</span>
            </td>
            <td class="p-3 text-(--ui-text-muted)">
              {{ u.is_owner ? 'كل الفروع' : branchNames(u.branch_ids) }}
            </td>
            <td class="p-3">
              <div class="flex flex-wrap gap-1">
                <UBadge :color="u.is_active ? 'success' : 'neutral'" variant="subtle">
                  {{ u.is_active ? 'شغال' : 'موقوف' }}
                </UBadge>
                <UBadge v-if="u.two_factor_enabled" color="primary" variant="subtle" icon="i-lucide-shield-check">
                  تحقق بخطوتين
                </UBadge>
              </div>
            </td>
            <td class="p-3 text-end whitespace-nowrap">
              <UButton v-if="store.isOwner" size="sm" color="neutral" variant="ghost" icon="i-lucide-monitor-smartphone" label="الأجهزة" @click="openSessions(u)" />
              <UButton v-if="!u.is_owner" size="sm" color="neutral" variant="ghost" icon="i-lucide-pencil" label="تعديل" @click="openForm(u)" />
            </td>
          </tr>
        </tbody>
      </table>
    </UCard>

    <UModal v-model:open="sessionsOpen" :title="sessionsFor ? `أجهزة «${sessionsFor.name}»` : 'الأجهزة'" :ui="{ content: 'sm:max-w-2xl' }">
      <template #body>
        <div v-if="sessionsFor" class="space-y-4">
          <SecuritySessionsList :endpoint="`/users/${sessionsFor.id}/sessions`" />
          <div v-if="sessionsFor.two_factor_enabled && !sessionsFor.is_owner" class="flex flex-wrap items-center justify-between gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 text-sm">
            <span>ضاع موبايله ومعهوش أكواد الاسترجاع؟ ألغِ التحقق بخطوتين عشان يدخل بكلمة السر، ويفعّله تاني.</span>
            <UButton size="sm" color="error" variant="soft" label="إلغاء التحقق بخطوتين" :loading="resetting" @click="resetTwoFactor(sessionsFor)" />
          </div>
        </div>
      </template>
    </UModal>

    <UModal v-model:open="formOpen" :title="editing ? `تعديل «${editing.name}»` : 'موظف جديد'">
      <template #body>
        <form id="user-form" class="space-y-4" @submit.prevent="save">
          <div class="grid gap-4 sm:grid-cols-2">
            <UFormField label="الاسم" required>
              <UInput v-model="form.name" class="w-full" />
            </UFormField>
            <UFormField label="رقم الموبايل" required>
              <UInput v-model="form.phone" dir="ltr" inputmode="tel" placeholder="01xxxxxxxxx" class="w-full" />
            </UFormField>
          </div>
          <UFormField :label="editing ? 'كلمة سر جديدة (سيبها فاضية لو مش هتغيّرها)' : 'كلمة السر'" :required="!editing">
            <UInput v-model="form.password" type="password" class="w-full" />
          </UFormField>
          <UFormField label="الدور" required>
            <USelect v-model="form.role_id" :items="roleItems" placeholder="اختار الدور" class="w-full" />
          </UFormField>
          <UFormField label="الفروع اللي يشتغل فيها" required>
            <div class="grid gap-2 sm:grid-cols-2">
              <UCheckbox
                v-for="b in branches"
                :key="b.id"
                :model-value="form.branch_ids.includes(b.id)"
                :label="b.name"
                @update:model-value="toggleBranch(b.id, $event === true)"
              />
            </div>
          </UFormField>
          <USwitch v-if="editing" v-model="form.is_active" label="الحساب شغال" description="لو وقفته هيخرج من كل الأجهزة ومش هيقدر يدخل." />
          <UAlert v-if="error" color="error" variant="subtle" :title="error" />
        </form>
      </template>
      <template #footer>
        <div class="flex w-full justify-end gap-2">
          <UButton color="neutral" variant="ghost" label="إلغاء" @click="formOpen = false" />
          <UButton type="submit" form="user-form" :loading="saving" label="حفظ" />
        </div>
      </template>
    </UModal>
  </div>
</template>

<script setup lang="ts">
import type { Branch, Role, SessionUser } from '~/types/api'

definePageMeta({ permission: 'users.manage' })

const api = useApi()
const toast = useToast()
const store = useSessionStore()

const [{ data: usersData, refresh }, { data: rolesData }, { data: branchesData }] = await Promise.all([
  useAsyncData('users', () => api<{ data: SessionUser[] }>('/users')),
  useAsyncData('roles', () => api<{ data: Role[] }>('/roles')),
  useAsyncData('branches', () => api<{ data: Branch[] }>('/branches')),
])
const users = computed(() => usersData.value?.data ?? [])
const branches = computed(() => (branchesData.value?.data ?? []).filter(b => b.is_active))
const roleItems = computed(() => (rolesData.value?.data ?? []).map(r => ({ label: r.name, value: r.id })))

function branchNames(ids: string[] | undefined): string {
  const names = (branchesData.value?.data ?? []).filter(b => ids?.includes(b.id)).map(b => b.name)
  return names.length ? names.join('، ') : '—'
}

const formOpen = ref(false)
const editing = ref<SessionUser | null>(null)
const saving = ref(false)
const error = ref<string | null>(null)
const form = reactive({ name: '', phone: '', password: '', role_id: undefined as number | undefined, branch_ids: [] as string[], is_active: true })

function openForm(user?: SessionUser) {
  editing.value = user ?? null
  error.value = null
  Object.assign(form, {
    name: user?.name ?? '',
    phone: user?.phone ?? '',
    password: '',
    role_id: user?.role?.id,
    branch_ids: user?.branch_ids ?? (branches.value[0] ? [branches.value[0].id] : []),
    is_active: user?.is_active ?? true,
  })
  formOpen.value = true
}

const sessionsOpen = ref(false)
const sessionsFor = ref<SessionUser | null>(null)
const resetting = ref(false)

function openSessions(user: SessionUser) {
  sessionsFor.value = user
  sessionsOpen.value = true
}

async function resetTwoFactor(user: SessionUser) {
  resetting.value = true
  try {
    await api(`/users/${user.id}/two-factor`, { method: 'DELETE' })
    toast.add({ color: 'success', title: `اتلغى التحقق بخطوتين لـ «${user.name}» وخرج من كل الأجهزة` })
    sessionsOpen.value = false
    await refresh()
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
  finally {
    resetting.value = false
  }
}

function toggleBranch(id: string, on: boolean) {
  form.branch_ids = on ? [...new Set([...form.branch_ids, id])] : form.branch_ids.filter(b => b !== id)
}

async function save() {
  saving.value = true
  error.value = null
  try {
    const body = { ...form, password: form.password || undefined }
    if (editing.value) {
      await api(`/users/${editing.value.id}`, { method: 'PATCH', body })
    }
    else {
      await api('/users', { method: 'POST', body })
    }
    formOpen.value = false
    toast.add({ color: 'success', title: 'اتحفظ' })
    await refresh()
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}
</script>
