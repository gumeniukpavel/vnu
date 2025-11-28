<template>
    <UiCard :wide="true">
        <template #head>
            <div class="row spread">
                <h2>Лист</h2>
                <button class="btn btn-ghost" @click="$emit('close')">Закрити</button>
            </div>
        </template>
        <template #body>
            <div v-if="loading" class="skeleton skeleton--line">Завантаження…</div>

            <div v-else-if="error" class="empty">{{ error }}</div>

            <div v-else-if="mail">
                <div class="row" style="justify-content:space-between; align-items:flex-start; gap:16px">
                    <div>
                        <div class="name" style="font-weight:700">{{ mail.subject || '(без теми)' }}</div>
                        <div class="muted" style="margin-top:4px">
                            від {{ fromName(mail) }} • {{ pretty(mail.receivedDateTime) }}
                        </div>
                    </div>
                    <a v-if="mail.webLink" class="btn" :href="mail.webLink" target="_blank" rel="noopener">Відкрити в Outlook</a>
                </div>

                <hr style="border:none; border-bottom:1px solid var(--border, #2f2f2f); margin:12px 0" />

                <div class="mail-body">
                    <p v-if="mail.bodyPreview" style="white-space:pre-wrap">{{ mail.bodyPreview }}</p>
                    <p v-else class="muted">Без попереднього перегляду.</p>
                </div>

                <div v-if="attachments.length" style="margin-top:16px">
                    <h3 style="margin:0 0 8px">Вкладення</h3>
                    <ul class="list">
                        <li v-for="a in attachments" :key="a.id" class="list__item">
                            <div class="ellipsis">
                                <span class="badge badge--soft" style="margin-right:8px">{{ humanType(a['@odata.type']) }}</span>
                                {{ a.name }} <span class="muted">({{ formatSize(a.size) }})</span>
                            </div>
                            <a
                                v-if="isFileAttachment(a)"
                                class="btn"
                                :href="`/api/ms365/mail/${mail.id}/attachments/${a.id}/download`"
                            >
                                ⬇️ Завантажити
                            </a>
                            <span v-else class="muted">— не файл (посилання / вкладений об’єкт)</span>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </UiCard>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import UiCard from './UiCard.vue'

const props = defineProps({
    mailId: { type: String, required: true }
})
const emit = defineEmits(['close'])

const mail = ref(null)
const attachments = ref([])
const loading = ref(false)
const error = ref('')

const pretty = (iso) => new Date(iso).toLocaleString()
const fromName = (m) => m?.from?.emailAddress?.name || m?.from?.emailAddress?.address || 'невідомо'
const isFileAttachment = (a) => (a?.['@odata.type'] || '').endsWith('fileAttachment')
const humanType = (t) => t?.split('.').pop() || 'attachment'
const formatSize = (n) => {
    const u = ['B','KB','MB','GB','TB']; let i=0; let s=Number(n||0);
    while (s>=1024 && i<u.length-1) { s/=1024; i++ }
    return `${s.toFixed(1)} ${u[i]}`
}

const load = async () => {
    loading.value = true; error.value=''; mail.value=null; attachments.value=[]
    try {
        const m = await fetch(`/api/ms365/mail/${props.mailId}`, { headers:{Accept:'application/json'}, credentials:'same-origin' })
        if (!m.ok) {
            error.value = `Помилка листа: ${await m.text()}`; return
        }
        mail.value = await m.json()

        if (mail.value?.hasAttachments) {
            const a = await fetch(`/api/ms365/mail/${props.mailId}/attachments`, { headers:{Accept:'application/json'}, credentials:'same-origin' })
            if (a.ok) {
                const ja = await a.json()
                attachments.value = ja?.data || []
            } else if (a.status === 403) {
                error.value = 'Немає дозволу Mail.Read для вкладень.'
            }
        }
    } catch (e) {
        error.value = String(e)
    } finally {
        loading.value = false
    }
}

watch(() => props.mailId, load, { immediate: true })
onMounted(load)
</script>
