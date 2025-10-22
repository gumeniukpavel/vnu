<template>
    <article class="card" :class="{ 'card--wide': wide }">
        <div v-if="$slots.head" class="card__head"><slot name="head" /></div>
        <div class="card__body"><slot name="body" /></div>
    </article>
</template>

<script setup>
defineProps({ wide: { type: Boolean, default: false } })
</script>
