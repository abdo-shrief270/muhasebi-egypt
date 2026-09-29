<template>
  <div class="rounded-(--ui-radius) bg-(--ui-bg-elevated) px-3 py-2">
    <UCheckbox v-model="consent" :label="label">
      <template #description>
        <span class="text-xs">
          قانون حماية البيانات الشخصية.
          <NuxtLink to="/privacy" target="_blank" class="text-primary hover:underline">إزاي بنحافظ على البيانات</NuxtLink>
        </span>
      </template>
    </UCheckbox>
  </div>
</template>

<script setup lang="ts">
/** The customer agreed to having their data kept (Personal Data Protection Law 151/2020). Pre-ticked on new customers. */
const consent = defineModel<boolean>({ default: true })
const label = CONSENT_TEXT
</script>
