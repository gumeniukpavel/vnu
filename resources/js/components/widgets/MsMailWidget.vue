<template>
    <UiCard :wide="true">
        <template #head>
            <div class="row spread">
                <h2>Пошта (MS365)</h2>
                <div>
                    <span class="badge" v-if="unread !== null">Непрочитані: {{ unread }}</span>
                    <button class="btn btn-ghost" @click="load">Оновити</button>
                </div>
            </div>
        </template>
        <template #body>
            <ul v-if="messages.length" class="list">
                <li
                    v-for="m in messages"
                    :key="m.id"
                    class="list__item"
                    style="cursor:pointer"
                    @click="$emit('open', m.id)"
                >
                    <div class="ellipsis">{{ m.subject || '(без теми)' }}</div>
                    <div class="muted">
                        від {{ m.from?.emailAddress?.name || m.from?.emailAddress?.address || 'невідомо' }}
                        • {{ pretty(m.receivedDateTime) }}
                    </div>
                    <span class="badge" :class="m.isRead ? 'badge--ok' : 'badge--warn'">
            {{ m.isRead ? 'прочитано' : 'нове' }}
          </span>
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

defineEmits(['open'])

const messages = ref([])
const unread = ref(null)
const loading = ref(false)
const emptyMessage = ref('Листів не знайдено або немає доступу.')

const pretty = (iso) => new Date(iso).toLocaleString()

const load = async () => {
    loading.value = true
    try {
        // лічильник непрочитаних
        const u = await fetch('/api/ms365/mail/unread', { headers:{Accept:'application/json'}, credentials:'same-origin' })
        if (u.ok) {
            const ju = await u.json()
            unread.value = ju.unread ?? 0
        } else if (u.status === 403) {
            unread.value = null
            emptyMessage.value = 'Немає дозволу Mail.Read (або заблоковано user consent).'
        }

        // топ листів
        const res = await fetch('/api/ms365/mail/top?take=10', { headers:{Accept:'application/json'}, credentials:'same-origin' })
        if (!res.ok) {
            messages.value = []
            emptyMessage.value = res.status === 403
                ? 'Немає дозволу Mail.Read.'
                : `Помилка: ${await res.text()}`
        } else {
            const data = await res.json()
            messages.value = data?.data || []
            if (!messages.value.length && unread.value === 0) emptyMessage.value = 'Немає нових листів.'
        }
    } catch (e) {
        messages.value = []
        emptyMessage.value = `Помилка завантаження: ${e}`
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
