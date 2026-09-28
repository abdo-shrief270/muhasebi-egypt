<template>
  <div class="space-y-6 max-w-6xl">
    <PageHeader title="الأدوار والصلاحيات" description="كل دور مجموعة صلاحيات. صاحب المحل ليه كل الصلاحيات دايماً.">
      <UButton icon="i-lucide-plus" label="دور جديد" @click="select(null)" />
    </PageHeader>

    <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
      <UCard :ui="{ body: 'p-2 sm:p-2' }">
        <button
          v-for="role in roles"
          :key="role.id"
          type="button"
          class="flex w-full items-center justify-between rounded-[calc(var(--ui-radius)*1.5)] px-3 py-2.5 text-start font-semibold transition hover:bg-(--ui-bg-elevated)"
          :class="{ 'app-soft': selected?.id === role.id }"
          @click="select(role)"
        >
          <span>{{ role.name }}</span>
          <span class="text-xs text-(--ui-text-muted)"><span class="num">{{ role.users_count ?? 0 }}</span> موظف</span>
        </button>
      </UCard>

      <UCard>
        <form class="space-y-5" @submit.prevent="save">
          <UFormField label="اسم الدور" required>
            <UInput v-model="form.name" class="w-full max-w-sm" />
          </UFormField>

          <div v-for="group in groups" :key="group.module" class="space-y-2">
            <div class="flex items-center justify-between border-b border-(--ui-border) pb-1">
              <p class="font-bold">
                {{ group.name }}
              </p>
              <UButton size="xs" color="neutral" variant="link" :label="allOn(group.module) ? 'شيل الكل' : 'اختار الكل'" @click="toggleGroup(group.module)" />
            </div>
            <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
              <UCheckbox
                v-for="p in group.permissions"
                :key="p.key"
                :model-value="form.permissions.includes(p.key)"
                :label="p.label"
                @update:model-value="toggle(p.key, $event === true)"
              />
            </div>
          </div>

          <UAlert v-if="error" color="error" variant="subtle" :title="error" />

          <div class="flex justify-between gap-2">
            <UButton v-if="selected" color="error" variant="ghost" icon="i-lucide-trash-2" label="مسح الدور" @click="remove" />
            <span v-else />
            <UButton type="submit" :loading="saving" :label="selected ? 'حفظ التعديلات' : 'إضافة الدور'" />
          </div>
        </form>
      </UCard>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PermissionGroup, Role } from '~/types/api'

definePageMeta({ permission: 'roles.manage' })

const api = useApi()
const toast = useToast()

const [{ data: rolesData, refresh }, { data: groupsData }] = await Promise.all([
  useAsyncData('roles', () => api<{ data: Role[] }>('/roles')),
  useAsyncData('permissions', () => api<{ data: PermissionGroup[] }>('/permissions')),
])
const roles = computed(() => rolesData.value?.data ?? [])
const groups = computed(() => groupsData.value?.data ?? [])

const selected = ref<Role | null>(null)
const form = reactive({ name: '', permissions: [] as string[] })
const saving = ref(false)
const error = ref<string | null>(null)

function select(role: Role | null) {
  selected.value = role
  error.value = null
  form.name = role?.name ?? ''
  form.permissions = [...(role?.permissions ?? [])]
}
select(roles.value[0] ?? null)

function toggle(key: string, on: boolean) {
  form.permissions = on ? [...new Set([...form.permissions, key])] : form.permissions.filter(p => p !== key)
}

function keysOf(module: string): string[] {
  return groups.value.find(g => g.module === module)?.permissions.map(p => p.key) ?? []
}

function allOn(module: string): boolean {
  return keysOf(module).every(k => form.permissions.includes(k))
}

function toggleGroup(module: string) {
  const on = !allOn(module)
  keysOf(module).forEach(k => toggle(k, on))
}

async function save() {
  saving.value = true
  error.value = null
  try {
    const res = selected.value
      ? await api<{ data: Role }>(`/roles/${selected.value.id}`, { method: 'PUT', body: form })
      : await api<{ data: Role }>('/roles', { method: 'POST', body: form })
    await refresh()
    select(roles.value.find(r => r.id === res.data.id) ?? null)
    toast.add({ color: 'success', title: 'اتحفظ' })
  }
  catch (e) {
    error.value = apiErrorMessage(e)
  }
  finally {
    saving.value = false
  }
}

async function remove() {
  if (!selected.value) {
    return
  }
  try {
    await api(`/roles/${selected.value.id}`, { method: 'DELETE' })
    await refresh()
    select(roles.value[0] ?? null)
  }
  catch (e) {
    toast.add({ color: 'error', title: apiErrorMessage(e) })
  }
}
</script>
