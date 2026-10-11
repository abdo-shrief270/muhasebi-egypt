<template>
  <header class="sticky top-0 z-20 border-b border-line bg-paper/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-3 sm:gap-4">
      <NuxtLink to="/" class="flex items-center gap-2.5 text-ink hover:text-ink">
        <span class="grid size-9 place-items-center rounded-full bg-teal font-bold text-paper">م</span>
        <span class="text-lg font-bold sm:text-xl">سوق محاسبي</span>
      </NuxtLink>
      <PlaceButton />
      <nav class="ms-auto flex gap-1 text-sm sm:text-[15px]" aria-label="الأقسام">
        <NuxtLink to="/shops" class="rounded-lg px-3 py-2 text-ink hover:bg-sand hover:text-ink">المحلات</NuxtLink>
        <NuxtLink to="/search?category=used" class="rounded-lg px-3 py-2 text-ink hover:bg-sand hover:text-ink">المستعمل</NuxtLink>
        <NuxtLink to="/search?category=part" class="hidden rounded-lg px-3 py-2 text-ink hover:bg-sand hover:text-ink sm:block">قطع غيار</NuxtLink>
      </nav>
    </div>
  </header>
</template>
