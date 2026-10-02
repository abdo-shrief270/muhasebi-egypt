<template>
  <div class="max-w-3xl space-y-6">
    <PageHeader title="الأمان وتسجيل الدخول" description="احمِ حسابك بالتحقق بخطوتين، وشوف الأجهزة اللي داخلة عليه." />

    <UAlert
      v-if="store.isOwner && !store.session?.user.two_factor_enabled"
      color="warning"
      variant="subtle"
      icon="i-lucide-shield-alert"
      title="إنت صاحب المحل: فعّل التحقق بخطوتين"
      description="حسابك بيفتح كل حاجة في المحل: الفلوس والأرباح والموظفين. كود من موبايلك مع كلمة السر بيمنع أي حد يدخل لو عرف كلمة السر."
    />

    <SecurityTwoFactorCard />

    <SecurityPasswordCard />

    <SecurityPinCard v-if="store.hasModule('owner_app') && store.can('owner_app.approve')" />

    <UCard>
      <template #header>
        <div>
          <h2 class="text-lg font-bold">
            الأجهزة اللي داخلة على حسابك
          </h2>
          <p class="text-sm text-(--ui-text-muted)">
            لو فيه جهاز مش عارفه أو موبايل ضاع، اخرج منه من هنا على طول.
          </p>
        </div>
      </template>
      <SecuritySessionsList endpoint="/account/sessions" own />
    </UCard>
  </div>
</template>

<script setup lang="ts">
const store = useSessionStore()
</script>
