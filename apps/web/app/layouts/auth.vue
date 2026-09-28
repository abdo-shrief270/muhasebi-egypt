<template>
  <div class="min-h-dvh grid place-items-center bg-(--ui-bg-muted) p-4">
    <div class="w-full max-w-md">
      <div class="flex items-center justify-center gap-2 mb-6">
        <UIcon name="i-lucide-smartphone" class="size-8 text-primary" />
        <span class="text-2xl font-extrabold">محاسبي</span>
      </div>
      <slot />
    </div>
  </div>
</template>
