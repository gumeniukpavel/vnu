<template>
    <UiCard>
        <template #head><h2>Мій профіль</h2></template>
        <template #body>
            <div v-if="me" class="profile">
                <img
                    v-if="msPhotoUrl && photoVisible"
                    :src="msPhotoUrl"
                    alt="avatar"
                    class="avatar"
                    style="object-fit:cover;border-radius:50%;width:48px;height:48px;margin-right:12px"
                    @error="onPhotoError"
                />
                <div v-else class="avatar" style="width:48px;height:48px;margin-right:12px;border-radius:50%;background:linear-gradient(135deg, #0ea5e9, #60a5fa);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:18px;flex-shrink:0;">
                    {{ initials(me.name) }}
                </div>
                <div>
                    <div class="name">{{ me.name }}</div>
                    <div class="muted">{{ me.email }}</div>
                </div>
            </div>
            <div v-else class="skeleton skeleton--line"></div>
        </template>
    </UiCard>
</template>

<script setup>
import { ref } from 'vue'
import UiCard from './UiCard.vue'

const props = defineProps({
    me: { type: Object, default: null },
    msPhotoUrl: { type: String, default: '' },
})

const photoVisible = ref(true)

const initials = (name) => (name || '?').split(' ').map(s => s[0]).slice(0,2).join('').toUpperCase()
const onPhotoError = (e) => { 
    photoVisible.value = false
    e.target.style.display = 'none'
}
</script>
