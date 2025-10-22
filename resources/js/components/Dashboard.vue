<template>
    <main class="container" :data-theme="theme">
        <!-- TOPBAR -->
        <header class="topbar">
            <div class="brand" @click="go('overview')" style="cursor:pointer">
                <div class="logo">V</div>
                <div class="titles">
                    <h1>Кабінет здобувача ВНУ</h1>
                    <p class="subtitle">розклад • курси • дедлайни</p>
                </div>
            </div>

            <div class="auth-buttons">
                <button class="btn btn-ghost" @click="toggleTheme">
                    {{ theme === 'light' ? '🌙 Тема' : '☀️ Тема' }}
                </button>

                <form v-if="isLoggedIn" method="POST" action="/logout">
                    <input type="hidden" name="_token" :value="csrf" />
                    <button class="btn btn-outline">Вийти</button>
                </form>

                <button v-else class="btn btn-primary" @click="msLogin">
                    Увійти через Microsoft
                </button>
            </div>
        </header>

        <!-- TABS -->
        <nav v-if="isLoggedIn"  class="tabs">
            <button
                v-for="t in tabs"
                :key="t.key"
                class="tab"
                :class="{ 'tab--active': view === t.key }"
                @click="go(t.key)"
            >
                {{ t.label }}
            </button>
        </nav>

        <!-- UNAUTH -->
        <section v-if="!isLoggedIn" class="unauth">
            <UiCard :wide="true">
                <template #body>
                    <p>
                        Натисніть <b>«Увійти через Microsoft»</b>, щоб авторизуватись.
                        <br />
                        <small class="muted">
                            Локально використовується Redirect URI:
                            <code>http://localhost:8000/login/microsoft/callback</code>
                        </small>
                    </p>
                </template>
            </UiCard>
        </section>

        <!-- AUTH VIEWS -->
        <section v-else class="grid">
            <!-- OVERVIEW -->
            <template v-if="view === 'overview'">
                <ProfileCard :me="me" :msPhotoUrl="msPhotoUrl" />

                <UiCard>
                    <template #head><h2>Курси</h2></template>
                    <template #body>
                        <ul v-if="courses?.length" class="list">
                            <li v-for="c in courses" :key="c.id" class="list__item">
                                <div class="ellipsis">{{ c.title }}</div>
                                <span class="badge">{{ c.code || '—' }}</span>
                            </li>
                        </ul>
                        <div v-else class="empty">Курси не завантажені.</div>
                    </template>
                </UiCard>

                <UiCard :wide="true">
                    <template #head>
                        <div class="row spread">
                            <h2>Сьогоднішній розклад</h2>
                            <button class="btn btn-ghost" @click="reloadSchedule">Оновити</button>
                        </div>
                    </template>
                    <template #body>
                        <ul v-if="schedule?.data?.length" class="timeline">
                            <li v-for="(e,i) in schedule.data" :key="i" class="timeline__item">
                                <div class="dot"></div>
                                <div class="row">
                                    <div class="when">
                                        <div class="time">{{ shortTime(e.start) }}</div>
                                        <div class="muted">→ {{ shortTime(e.end) }}</div>
                                    </div>
                                    <div class="what">
                                        <div class="title">{{ e.title }}</div>
                                        <div class="muted">
                                            <span v-if="e.location">ауд. {{ e.location }}</span>
                                            <span v-if="e.group" class="sep">•</span>
                                            <span v-if="e.group" class="badge badge--soft">{{ e.group }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div v-else class="empty">На сьогодні пар не знайдено.</div>
                    </template>
                </UiCard>

                <UiCard :wide="true">
                    <template #head><h2>Дедлайни</h2></template>
                    <template #body>
                        <ul v-if="assignments?.length" class="list">
                            <li v-for="a in assignments" :key="a.id" class="list__item">
                                <div>
                                    <div class="ellipsis">{{ a.title }}</div>
                                    <div class="muted">до {{ prettyDate(a.due_at) }}</div>
                                </div>
                                <span class="badge" :class="a.status === 'pending' ? 'badge--warn' : 'badge--ok'">
                  {{ a.status }}
                </span>
                            </li>
                        </ul>
                        <div v-else class="empty">Немає активних дедлайнів.</div>
                    </template>
                </UiCard>
            </template>

            <!-- CALENDAR (MS365) -->
            <template v-else-if="view === 'calendar'">
                <MsCalendarWidget />
            </template>

            <!-- MAIL (MS365) -->
            <template v-else-if="view === 'mail'">
                <MsMailWidget @open="onOpenMail" />
                <MsMailViewer v-if="openedMailId" :mailId="openedMailId" @close="closeMail" />
            </template>

            <!-- FILES (MS365 OneDrive) -->
            <template v-else-if="view === 'files'">
                <MsFilesWidget />
            </template>

            <!-- COURSES (Moodle stub) -->
            <template v-else-if="view === 'courses'">
                <UiCard :wide="true">
                    <template #head><h2>Курси (Moodle)</h2></template>
                    <template #body>
                        <ul v-if="courses?.length" class="list">
                            <li v-for="c in courses" :key="c.id" class="list__item">
                                <div class="ellipsis">{{ c.title }}</div>
                                <span class="badge">{{ c.code || '—' }}</span>
                            </li>
                        </ul>
                        <div v-else class="empty">Курси не завантажені.</div>
                    </template>
                </UiCard>
            </template>

            <!-- SCHEDULE (stub) -->
            <template v-else-if="view === 'schedule'">
                <UiCard :wide="true">
                    <template #head>
                        <div class="row spread">
                            <h2>Розклад (сьогодні)</h2>
                            <button class="btn btn-ghost" @click="reloadSchedule">Оновити</button>
                        </div>
                    </template>
                    <template #body>
                        <ul v-if="schedule?.data?.length" class="timeline">
                            <li v-for="(e,i) in schedule.data" :key="i" class="timeline__item">
                                <div class="dot"></div>
                                <div class="row">
                                    <div class="when">
                                        <div class="time">{{ shortTime(e.start) }}</div>
                                        <div class="muted">→ {{ shortTime(e.end) }}</div>
                                    </div>
                                    <div class="what">
                                        <div class="title">{{ e.title }}</div>
                                        <div class="muted">
                                            <span v-if="e.location">ауд. {{ e.location }}</span>
                                            <span v-if="e.group" class="sep">•</span>
                                            <span v-if="e.group" class="badge badge--soft">{{ e.group }}</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                        <div v-else class="empty">На сьогодні пар не знайдено.</div>
                    </template>
                </UiCard>
            </template>

            <!-- GRADES (stub) -->
            <template v-else-if="view === 'grades'">
                <UiCard :wide="true">
                    <template #head><h2>Журнал оцінок</h2></template>
                    <template #body><div class="empty">Заглушка: підключимо, коли з’явиться API.</div></template>
                </UiCard>
            </template>

            <!-- LIBRARY (stub) -->
            <template v-else-if="view === 'library'">
                <UiCard :wide="true">
                    <template #head><h2>Бібліотека</h2></template>
                    <template #body><div class="empty">Заглушка: підключимо, коли з’явиться API.</div></template>
                </UiCard>
            </template>
        </section>
    </main>
</template>

<script setup>
import './../../css/dashboard.css'
import { ref, onMounted, computed } from 'vue'

import UiCard from './widgets/UiCard.vue'
import ProfileCard from './widgets/ProfileCard.vue'
import MsCalendarWidget from './widgets/MsCalendarWidget.vue'
import MsMailWidget from './widgets/MsMailWidget.vue'
import MsFilesWidget from './widgets/MsFilesWidget.vue'
import MsMailViewer from './widgets/MsMailViewer.vue' // переглядач листа (окремий файл)

const view = ref('overview')
const tabs = [
    { key: 'overview', label: 'Огляд' },
    { key: 'calendar', label: 'Календар' },
    { key: 'mail',     label: 'Пошта' },
    { key: 'files',    label: 'Файли' },
    { key: 'courses',  label: 'Курси' },
    { key: 'schedule', label: 'Розклад' },
    { key: 'grades',   label: 'Оцінки' },
    { key: 'library',  label: 'Бібліотека' },
]

const me = ref(null)
const schedule = ref({ data: [] })
const courses = ref([])
const assignments = ref([])
const isLoggedIn = ref(false)
const theme = ref(localStorage.getItem('theme') || 'light')
const msPhotoUrl = computed(() => `/api/ms365/photo?ts=${Date.now()}`)

const openedMailId = ref(null)
const onOpenMail = (id) => { openedMailId.value = id }
const closeMail = () => { openedMailId.value = null }

const go = (key) => { view.value = key; history.replaceState(null, '', `#/${key}`) }

const toggleTheme = () => {
    theme.value = theme.value === 'light' ? 'dark' : 'light'
    document.documentElement.setAttribute('data-theme', theme.value)
    localStorage.setItem('theme', theme.value)
}
onMounted(() => {
    document.documentElement.setAttribute('data-theme', theme.value)
    const init = (location.hash || '').replace(/^#\//, '')
    if (tabs.some(t => t.key === init)) view.value = init
})

const csrf = computed(() => {
    const meta = document.querySelector('meta[name=csrf-token]')
    return meta ? meta.content : ''
})

const fetchJSON = async (url) => {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
    if (!res.ok) throw new Error(await res.text())
    return res.json()
}

const todayParams = () => {
    const from = encodeURIComponent('now')
    const to = encodeURIComponent('+1 day')
    return `?from=${from}&to=${to}&group=IPZ-11`
}

const fmt = (iso) => new Date(iso)
const two = (n) => String(n).padStart(2, '0')
const shortTime = (iso) => { const d = fmt(iso); return `${two(d.getHours())}:${two(d.getMinutes())}` }
const prettyDate = (iso) => fmt(iso).toLocaleString()

const reloadSchedule = async () => { schedule.value = await fetchJSON('/api/schedule' + todayParams()) }
const msLogin = () => { location.href = '/login' }

onMounted(async () => {
    try {
        me.value = await fetchJSON('/api/me')
        isLoggedIn.value = true

        await reloadSchedule()
        courses.value = await fetchJSON('/api/courses')
        try { assignments.value = await fetchJSON('/api/courses/101/assignments') } catch { assignments.value = [] }
    } catch {
        isLoggedIn.value = false
    }
})
</script>

<style scoped>
.container { padding: 16px; max-width: 1100px; margin: 0 auto; }
.topbar { display:flex; justify-content:space-between; align-items:center; gap:12px; }
.brand { display:flex; align-items:center; gap:12px; }
.logo { width:40px; height:40px; border-radius:10px; display:grid; place-items:center; font-weight:700; background: var(--logo-bg, #2a5bd7); color:white; }
.titles h1 { margin:0; font-size: 18px; line-height:1.2; }
.titles .subtitle { margin:2px 0 0; opacity:.7; font-size: 12px; }

/* Tabs */
.tabs { display:flex; gap:8px; flex-wrap:wrap; margin:12px 0 16px; }
.tab {
    padding:8px 12px; border:1px solid var(--border, #3a3a3a);
    background: var(--tab-bg, transparent); color: var(--tab-fg, inherit);
    border-radius: 999px; cursor: pointer; user-select: none;
    transition: background .15s ease, color .15s ease, border-color .15s ease, transform .02s ease;
    outline: none;
}
.tab--active { background: var(--tab-active-bg, rgba(100,150,250,.18)); border-color: rgba(100,150,250,.5); color: var(--tab-active-fg, #fff); }
.tab:hover { background: rgba(255,255,255,.06); border-color: var(--border, #4a4a4a); }
.tab:active { transform: translateY(1px); }
.tab:focus-visible { box-shadow: 0 0 0 2px rgba(100,150,250,.5); }

/* Cards */
.card { background: var(--card-bg, rgba(255,255,255,.03)); border:1px solid var(--border, #2f2f2f); border-radius:16px; padding:0; overflow:hidden; }
.card--wide { grid-column: 1 / -1; }
.card__head { padding:12px 16px; border-bottom:1px solid var(--border, #2f2f2f); }
.card__body { padding:16px; }
.row { display:flex; gap:12px; align-items:center; }
.spread { justify-content: space-between; align-items:center; }

/* Grid */
.grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:16px; }
@media (max-width: 900px){ .grid{ grid-template-columns: 1fr; } }

/* Lists / timeline */
.list { display:flex; flex-direction:column; gap:10px; }
.list__item { display:flex; align-items:center; justify-content:space-between; gap:12px; }
.ellipsis { max-width: 580px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.badge { display:inline-block; padding:4px 8px; border-radius:999px; border:1px solid var(--border, #2f2f2f); font-size:12px; }
.badge--soft { background: rgba(255,255,255,.06); }
.badge--ok { background: rgba(60,180,60,.15); border-color: rgba(60,180,60,.4); }
.badge--warn { background: rgba(255,180,60,.15); border-color: rgba(255,180,60,.4); }
.empty { opacity:.75; }

.timeline { display:flex; flex-direction:column; gap:16px; position:relative; }
.timeline__item { position:relative; padding-left:24px; }
.timeline__item .dot { position:absolute; left:4px; top:10px; width:8px; height:8px; border-radius:50%; background:#58a6ff; }
.when .time { font-weight:600; }
.what .title { font-weight:600; }

/* Profile */
.profile { display:flex; align-items:center; gap:12px; }
.avatar { width:48px; height:48px; border-radius:50%; background: #2d2d2d; color:#fff; display:grid; place-items:center; font-weight:700; }

/* Buttons */
.btn { padding:8px 12px; border-radius:10px; border:1px solid var(--border, #2f2f2f); background:transparent; color:inherit; cursor:pointer; }
.btn-ghost { background: transparent; }
.btn-outline { background: transparent; }
.btn-primary { background: #2a5bd7; border-color:#2a5bd7; color:white; }

/* Light / Dark */
:root[data-theme="light"]{
    --border: #d0d0d0;
    --tab-active-bg: rgba(30, 80, 180, .12);
    --tab-active-fg: #0b1641;
    --card-bg: #ffffff;
    --logo-bg: #2a5bd7;
}
:root[data-theme="dark"]{
    --border: #2f2f2f;
    --tab-active-bg: rgba(100,150,250,.18);
    --tab-active-fg: #ffffff;
    --card-bg: rgba(255,255,255,.03);
    --logo-bg: #2a5bd7;
}

/* Mobile tabs */
@media (max-width: 720px){
    .tabs{ position:sticky; top:0; z-index:5; background: var(--bg, #0b0b0b); padding-top:8px; margin-top: 0; }
}
</style>
