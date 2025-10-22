<template>
    <UiCard :wide="true">
        <template #head>
            <div class="row spread">
                <h2>Події календаря (сьогодні, MS365)</h2>
                <button class="btn btn-ghost" @click="load">Оновити</button>
            </div>
        </template>
        <template #body>
            <ul v-if="events.length" class="list">
                <li v-for="e in events" :key="e.id || e.start?.dateTime" class="list__item">
                    <div>
                        <div class="ellipsis">{{ e.subject || '(без теми)' }}</div>
                        <div class="muted">
                            {{ timeRange(e.start?.dateTime, e.end?.dateTime) }}
                            <span v-if="e.location?.displayName" class="sep">•</span>
                            <span v-if="e.location?.displayName" class="badge badge--soft">{{ e.location.displayName }}</span>
                        </div>
                    </div>
                </li>
            </ul>
            <div v-else-if="loading" class="skeleton skeleton--line">Завантаження…</div>
            <div v-else class="empty">{{ emptyMessage }}</div>
        </template>
    </UiCard>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import UiCard from './UiCard.vue'

const events = ref([])
const loading = ref(false)
const emptyMessage = ref('Подій на сьогодні не знайдено або немає доступу.')

const load = async () => {
    loading.value = true
    try {
        const res = await fetch('/api/ms365/calendar/today', { headers:{Accept:'application/json'}, credentials:'same-origin' })
        if (!res.ok) {
            events.value = []
            emptyMessage.value = res.status === 403
                ? 'Немає дозволу Calendars.Read (або заблоковано user consent).'
                : `Помилка: ${await res.text()}`
        } else {
            const data = await res.json()
            events.value = data?.data || []
        }
    } catch (e) {
        events.value = []; emptyMessage.value = `Помилка завантаження: ${e}`
    } finally { loading.value = false }
}

const fmt = (iso) => iso ? new Date(iso) : null
const two = (n) => String(n).padStart(2,'0')
const hhmm = (d) => `${two(d.getHours())}:${two(d.getMinutes())}`
const timeRange = (s,e) => { const ds=fmt(s), de=fmt(e); return (ds&&de)?`${hhmm(ds)} → ${hhmm(de)}`:'' }

onMounted(load)
</script>
