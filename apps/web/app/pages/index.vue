<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold">
        أهلاً {{ store.session?.user.name }} 👋
      </h1>
      <p class="text-(--ui-text-muted)">
        الأقسام المفعّلة في محلك:
      </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <NuxtLink v-for="item in store.menu" :key="item.to" :to="item.to">
        <UCard class="h-full hover:ring-primary transition">
          <div class="flex items-center gap-3">
            <div class="size-11 grid place-items-center rounded-lg bg-primary/10 text-primary">
              <UIcon :name="item.icon" class="size-6" />
            </div>
            <span class="font-semibold">{{ item.label }}</span>
          </div>
        </UCard>
      </NuxtLink>
    </div>

    <UAlert
      v-if="store.isOwner"
      icon="i-lucide-blocks"
      color="neutral"
      variant="subtle"
      title="محتاج قسم زيادة؟"
      description="جرّب الاستيراد أو الصيانة أو غيرهم مجاناً 14 يوم من صفحة الأقسام."
      :actions="[{ label: 'الأقسام', to: '/settings/modules' }]"
    />
  </div>
</template>

<script setup lang="ts">
const store = useSessionStore()
</script>
