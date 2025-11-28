<template>
    <div class="mail-view-page">
        <UiCard :wide="true">
            <template #head>
                <div class="row spread">
                    <div class="row gap-2" style="align-items: center">
                        <button class="btn btn-ghost" @click="goBack">← Назад</button>
                        <h2 style="margin: 0">Лист</h2>
                    </div>
                    <a v-if="mail?.webLink" class="btn" :href="mail.webLink" target="_blank" rel="noopener">
                        Відкрити в Outlook
                    </a>
                </div>
            </template>
            <template #body>
                <div v-if="loading" class="skeleton skeleton--line">Завантаження…</div>

                <div v-else-if="error" class="empty">{{ error }}</div>

                <div v-else-if="mail">
                    <div class="row" style="justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom: 16px">
                        <div>
                            <div class="name" style="font-weight:700; font-size: 1.2em">{{ mail.subject || '(без теми)' }}</div>
                            <div class="muted" style="margin-top:4px">
                                <template v-if="mail.sentDateTime && !mail.receivedDateTime">
                                    кому {{ getToRecipients(mail) }} • {{ pretty(mail.sentDateTime) }}
                                </template>
                                <template v-else>
                                    від {{ fromName(mail) }} • {{ pretty(mail.receivedDateTime) }}
                                </template>
                            </div>
                        </div>
                    </div>

                    <hr style="border:none; border-bottom:1px solid var(--border, #2f2f2f); margin:12px 0" />

                    <div class="mail-body" style="margin-bottom: 24px; padding: 16px; background: var(--bg-secondary, #f5f5f5); border-radius: 8px">
                        <p v-if="mail.bodyPreview" style="white-space:pre-wrap; margin: 0">{{ mail.bodyPreview }}</p>
                        <p v-else class="muted" style="margin: 0">Без попереднього перегляду.</p>
                    </div>

                    <div v-if="attachments.length" style="margin-bottom: 24px">
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
                                <span v-else class="muted">— не файл (посилання / вкладений об'єкт)</span>
                            </li>
                        </ul>
                    </div>

                    <hr style="border:none; border-bottom:1px solid var(--border, #2f2f2f); margin:24px 0" />

                    <div v-if="!mail.sentDateTime || mail.receivedDateTime" class="reply-section">
                        <h3 style="margin:0 0 12px">Відправити відповідь</h3>
                        <form @submit.prevent="sendReply">
                            <div style="margin-bottom: 12px">
                                <label style="display: block; margin-bottom: 4px; font-weight: 600">Кому:</label>
                                <input 
                                    type="text" 
                                    :value="fromName(mail)" 
                                    disabled 
                                    style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg-secondary)"
                                />
                            </div>
                            <div style="margin-bottom: 12px">
                                <label style="display: block; margin-bottom: 4px; font-weight: 600">Тема:</label>
                                <input 
                                    type="text" 
                                    v-model="replySubject" 
                                    placeholder="Re: {{ mail.subject }}"
                                    style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px"
                                />
                            </div>
                            <div style="margin-bottom: 12px">
                                <label style="display: block; margin-bottom: 4px; font-weight: 600">Повідомлення:</label>
                                <textarea 
                                    v-model="replyBody" 
                                    rows="8"
                                    placeholder="Введіть вашу відповідь..."
                                    required
                                    style="width: 100%; padding: 8px; border: 1px solid var(--border); border-radius: 4px; font-family: inherit; resize: vertical"
                                ></textarea>
                            </div>
                            <div class="row gap-2">
                                <button 
                                    type="submit" 
                                    class="btn btn-primary" 
                                    :disabled="sending || !replyBody.trim()"
                                >
                                    {{ sending ? 'Відправка...' : 'Відправити відповідь' }}
                                </button>
                                <button 
                                    type="button" 
                                    class="btn btn-ghost" 
                                    @click="resetReply"
                                    :disabled="sending"
                                >
                                    Очистити
                                </button>
                            </div>
                            <div v-if="replyError" class="error-message" style="margin-top: 12px; color: var(--error, #dc3545)">
                                {{ replyError }}
                            </div>
                            <div v-if="replySuccess" class="success-message" style="margin-top: 12px; color: var(--success, #28a745)">
                                {{ replySuccess }}
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </UiCard>
    </div>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import UiCard from './widgets/UiCard.vue'

const props = defineProps({
    mailId: { type: String, required: true }
})

const emit = defineEmits(['back'])

const mail = ref(null)
const attachments = ref([])
const loading = ref(false)
const error = ref('')
const replyBody = ref('')
const replySubject = ref('')
const sending = ref(false)
const replyError = ref('')
const replySuccess = ref('')

const pretty = (iso) => new Date(iso).toLocaleString()
const fromName = (m) => m?.from?.emailAddress?.name || m?.from?.emailAddress?.address || 'невідомо'
const getToRecipients = (m) => {
    if (!m.toRecipients || !Array.isArray(m.toRecipients) || m.toRecipients.length === 0) {
        return 'невідомо'
    }
    const first = m.toRecipients[0]
    const name = first?.emailAddress?.name || first?.emailAddress?.address
    const count = m.toRecipients.length
    return count > 1 ? `${name} (+${count - 1})` : name
}
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
        replySubject.value = `Re: ${mail.value.subject || ''}`

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

const sendReply = async () => {
    if (!replyBody.value.trim()) return
    
    sending.value = true
    replyError.value = ''
    replySuccess.value = ''
    
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''
        
        const response = await fetch(`/api/ms365/mail/${props.mailId}/reply`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                body: replyBody.value,
                subject: replySubject.value || undefined
            })
        })
        
        if (!response.ok) {
            const errorData = await response.json()
            replyError.value = errorData.details || errorData.message || 'Помилка відправки'
            return
        }
        
        replySuccess.value = 'Відповідь успішно відправлено!'
        replyBody.value = ''
        replySubject.value = `Re: ${mail.value.subject || ''}`
        
        setTimeout(() => {
            replySuccess.value = ''
        }, 5000)
    } catch (e) {
        replyError.value = `Помилка: ${e.message}`
    } finally {
        sending.value = false
    }
}

const resetReply = () => {
    replyBody.value = ''
    replySubject.value = `Re: ${mail.value?.subject || ''}`
    replyError.value = ''
    replySuccess.value = ''
}

const goBack = () => {
    emit('back')
}

watch(() => props.mailId, load, { immediate: true })
onMounted(load)
</script>

<style scoped>
.reply-section {
    margin-top: 24px;
    padding: 16px;
    background: var(--bg, #fff);
    border: 1px solid var(--border, #e0e0e0);
    border-radius: 8px;
}

.error-message {
    padding: 8px;
    background: rgba(220, 53, 69, 0.1);
    border-radius: 4px;
}

.success-message {
    padding: 8px;
    background: rgba(40, 167, 69, 0.1);
    border-radius: 4px;
}
</style>

