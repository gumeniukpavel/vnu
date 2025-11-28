<template>
    <UiCard :wide="true">
        <template #head>
            <div class="row spread">
                <h2>Пошта (MS365)</h2>
                <div class="row gap-2" style="align-items: center">
                    <div class="mail-folder-tabs">
                        <button 
                            class="btn btn-sm" 
                            :class="folder === 'Inbox' ? 'btn-primary' : 'btn-ghost'"
                            @click="setFolder('Inbox')"
                        >
                            Вхідні
                        </button>
                        <button 
                            class="btn btn-sm" 
                            :class="folder === 'SentItems' ? 'btn-primary' : 'btn-ghost'"
                            @click="setFolder('SentItems')"
                        >
                            Вихідні
                        </button>
                    </div>
                    <span class="badge" v-if="unread !== null && folder === 'Inbox'">Непрочитані: {{ unread }}</span>
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
                        <template v-if="folder === 'SentItems'">
                            кому {{ getToRecipients(m) }}
                            • {{ pretty(m.sentDateTime) }}
                        </template>
                        <template v-else>
                            від {{ m.from?.emailAddress?.name || m.from?.emailAddress?.address || 'невідомо' }}
                            • {{ pretty(m.receivedDateTime) }}
                        </template>
                    </div>
                    <span v-if="folder === 'Inbox'" class="badge" :class="m.isRead ? 'badge--ok' : 'badge--warn'">
                        {{ m.isRead ? 'прочитано' : 'нове' }}
                    </span>
                </li>
            </ul>
            <div v-else-if="loading" class="skeleton skeleton--line">Завантаження…</div>
            <div v-else class="empty">{{ emptyMessage }}</div>
            
            <div v-if="messages.length > 0" class="pagination">
                <button 
                    class="btn btn-ghost btn-sm" 
                    @click="prevPage" 
                    :disabled="currentPage === 1 || loading"
                >
                    ← Попередня
                </button>
                <span class="pagination-info">
                    Сторінка {{ currentPage }} 
                    <span v-if="totalPages > 0">з {{ totalPages }}</span>
                </span>
                <button 
                    class="btn btn-ghost btn-sm" 
                    @click="nextPage" 
                    :disabled="currentPage >= totalPages || loading || messages.length < pageSize"
                >
                    Наступна →
                </button>
            </div>
        </template>
    </UiCard>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import UiCard from './UiCard.vue'

defineEmits(['open'])

const folder = ref(localStorage.getItem('mailFolder') || 'Inbox')
const messages = ref([])
const unread = ref(null)
const loading = ref(false)
const emptyMessage = ref('Листів не знайдено або немає доступу.')
const pageSize = 10
const currentPage = ref(1)
const totalPages = ref(0)

const pretty = (iso) => new Date(iso).toLocaleString()

const getToRecipients = (m) => {
    if (!m.toRecipients || !Array.isArray(m.toRecipients) || m.toRecipients.length === 0) {
        return 'невідомо'
    }
    const first = m.toRecipients[0]
    const name = first?.emailAddress?.name || first?.emailAddress?.address
    const count = m.toRecipients.length
    return count > 1 ? `${name} (+${count - 1})` : name
}

const setFolder = (newFolder) => {
    folder.value = newFolder
    currentPage.value = 1
    localStorage.setItem('mailFolder', newFolder)
    load()
}

const prevPage = () => {
    if (currentPage.value > 1) {
        currentPage.value--
        load()
    }
}

const nextPage = () => {
    if (messages.value.length === pageSize) {
        currentPage.value++
        load()
    }
}

const load = async () => {
    loading.value = true
    try {
        if (folder.value === 'Inbox') {
            const u = await fetch('/api/ms365/mail/unread', { headers:{Accept:'application/json'}, credentials:'same-origin' })
            if (u.ok) {
                const ju = await u.json()
                unread.value = ju.unread ?? 0
            } else if (u.status === 403) {
                unread.value = null
                emptyMessage.value = 'Немає дозволу Mail.Read (або заблоковано user consent).'
            }
        } else {
            unread.value = null
        }

        const skip = (currentPage.value - 1) * pageSize
        const res = await fetch(`/api/ms365/mail/top?take=${pageSize}&skip=${skip}&folder=${folder.value}`, { headers:{Accept:'application/json'}, credentials:'same-origin' })
        if (!res.ok) {
            messages.value = []
            emptyMessage.value = res.status === 403
                ? 'Немає дозволу Mail.Read.'
                : `Помилка: ${await res.text()}`
        } else {
            const data = await res.json()
            messages.value = data?.data || []
            
            if (data?.total !== null && data?.total !== undefined) {
                totalPages.value = Math.ceil(data.total / pageSize)
            } else {
                if (messages.value.length < pageSize) {
                    totalPages.value = currentPage.value
                } else {
                    totalPages.value = currentPage.value + 1
                }
            }
            
            if (!messages.value.length) {
                emptyMessage.value = folder.value === 'SentItems' 
                    ? 'Вихідних листів не знайдено.'
                    : (unread.value === 0 ? 'Немає нових листів.' : 'Листів не знайдено.')
            }
        }
    } catch (e) {
        messages.value = []
        emptyMessage.value = `Помилка завантаження: ${e}`
    } finally {
        loading.value = false
    }
}

watch(folder, () => {
    currentPage.value = 1
    load()
})
onMounted(load)
</script>

<style scoped>
.mail-folder-tabs {
    display: flex;
    gap: 4px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--border, #e0e0e0);
}

.pagination-info {
    color: var(--muted);
    font-size: 14px;
}

.pagination button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 575px) {
    .pagination {
        flex-direction: column;
        gap: 8px;
    }
}
</style>
