<template>
    <UiCard :wide="true">
        <template #head>
            <div class="row spread">
                <h2>OneDrive файли (MS365)</h2>
                <button class="btn btn-ghost" @click="load">Оновити</button>
            </div>
        </template>

        <template #body>
            <ul v-if="files.length" class="list">
                <li v-for="f in files" :key="f.id" class="list__item">
                    <div class="ellipsis">
                        <span style="font-weight:600">{{ f.name }}</span>
                        <div class="muted">{{ pretty(f.lastModifiedDateTime) }}</div>
                    </div>

                    <div class="row" style="gap:8px">
                        <a class="btn" :href="`/api/ms365/drive/items/${f.id}/download`">⬇️</a>
                        <a v-if="f.webUrl" class="btn btn-ghost" :href="f.webUrl" target="_blank">🌐</a>
                        <span class="badge badge--soft">{{ formatSize(f.size) }}</span>
                    </div>
                </li>
            </ul>

            <div v-else-if="loading" class="skeleton skeleton--line">Завантаження...</div>
            <div v-else class="empty">{{ emptyMessage }}</div>
        </template>
    </UiCard>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import UiCard from './UiCard.vue'

const files = ref([])
const loading = ref(false)
const emptyMessage = ref('Немає файлів або відсутні дозволи.')

const pretty = (iso) => new Date(iso).toLocaleString()
const formatSize = (n) => {
    const u = ['B','KB','MB','GB','TB']
    let i=0, s = Number(n||0)
    while (s>=1024 && i<u.length-1) { s/=1024; i++ }
    return `${s.toFixed(1)} ${u[i]}`
}

const load = async () => {
    loading.value = true
    try {
        const res = await fetch('/api/ms365/drive/recent', { headers:{Accept:'application/json'}, credentials:'same-origin' })
        if (!res.ok) {
            files.value = []
            emptyMessage.value = res.status === 403
                ? 'Немає дозволу Files.Read або Files.ReadWrite.'
                : `Помилка: ${await res.text()}`
        } else {
            const data = await res.json()
            files.value = data?.data || []
            if (!files.value.length) emptyMessage.value = 'Немає недавніх файлів.'
        }
    } catch (e) {
        files.value = []
        emptyMessage.value = `Помилка завантаження: ${e}`
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
