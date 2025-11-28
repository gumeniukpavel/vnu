<template>
    <main class="container" :data-theme="theme">
        <header class="topbar">
            <div class="brand" @click="go('overview')" style="cursor:pointer">
                <div class="logo"></div>
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

        <section v-else class="grid">
            <template v-if="view === 'overview'">
                <ProfileCard :me="me" :msPhotoUrl="msPhotoUrl" />

                <UiCard>
                    <template #head>
                        <div class="row spread">
                            <h2>Мої курси</h2>
                            <button class="btn btn-ghost btn-sm" @click="go('courses')">Всі курси →</button>
                        </div>
                    </template>
                    <template #body>
                        <ul v-if="moodleCourses?.length" class="list">
                            <li
                                v-for="c in moodleCourses.slice(0, 5)"
                                :key="c.id"
                                class="list__item list__item--clickable"
                                @click="openCourse(c.id)"
                            >
                                <div class="ellipsis">{{ c.fullname }}</div>
                                <span class="badge">{{ c.shortname || '—' }}</span>
                            </li>
                        </ul>
                        <div v-else-if="loadingMoodleCourses" class="empty">Завантаження курсів...</div>
                        <div v-else class="empty">Курси не завантажені.</div>
                    </template>
                </UiCard>

                <UiCard :wide="true">
                    <template #head>
                        <div class="row spread">
                            <h2>Розклад</h2>
                            <div class="row gap-2">
                                <button
                                    class="btn btn-sm"
                                    :class="schedulePeriod === 'today' ? 'btn-primary' : 'btn-ghost'"
                                    @click="setSchedulePeriod('today')"
                                >
                                    Сьогодні
                                </button>
                                <button
                                    class="btn btn-sm"
                                    :class="schedulePeriod === 'week' ? 'btn-primary' : 'btn-ghost'"
                                    @click="setSchedulePeriod('week')"
                                >
                                    Тиждень
                                </button>
                                <button class="btn btn-ghost btn-sm" @click="reloadSchedule">Оновити</button>
                            </div>
                        </div>
                    </template>
                    <template #body>
                        <template v-if="schedulePeriod === 'week' && schedule?.data?.length">
                            <div v-for="day in groupScheduleByDate(schedule.data)" :key="day.date" class="schedule-day schedule-accordion">
                                <button 
                                    class="schedule-day-header" 
                                    @click="toggleScheduleDay(day.date)"
                                    :aria-expanded="isScheduleDayOpen(day.date)"
                                >
                                    <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-primary); flex: 1; text-align: left;">
                                        {{ day.dateFormatted }}
                                        <span class="schedule-day-count">({{ day.events.length }})</span>
                                    </h3>
                                    <span class="schedule-accordion-icon" :class="{ 'schedule-accordion-icon--open': isScheduleDayOpen(day.date) }">
                                        ▼
                                    </span>
                                </button>
                                <div 
                                    class="schedule-day-content" 
                                    :class="{ 'schedule-day-content--collapsed': !isScheduleDayOpen(day.date) }"
                                >
                                    <ul class="timeline">
                                        <li v-for="(e,i) in day.events" :key="`${day.date}-${i}`" class="timeline__item">
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
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <ul v-if="getFilteredTodaySchedule()?.length" class="timeline">
                                <li v-for="(e,i) in getFilteredTodaySchedule()" :key="i" class="timeline__item">
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
                            <div v-else class="empty">Пар не знайдено.</div>
                        </template>
                    </template>
                </UiCard>

                <UiCard :wide="true">
                    <template #head>
                        <div class="row spread">
                            <h2>Оголошення та новини</h2>
                            <button class="btn btn-ghost btn-sm" @click="loadNews">Оновити</button>
                        </div>
                    </template>
                    <template #body>
                        <div v-if="loadingNews" class="empty">Завантаження...</div>
                        <div v-else-if="news?.length" class="news-feed">
                            <article v-for="item in news.slice(0, 3)" :key="item.id" class="news-item news-item--compact">
                                <div class="news-item__header">
                                    <span class="badge" :class="{
                                        'badge--danger': item.priority === 'high',
                                        'badge--warn': item.priority === 'normal',
                                        'badge--soft': item.type === 'news'
                                    }">
                                        {{ item.type === 'announcement' ? 'Оголошення' : 'Новина' }}
                                    </span>
                                    <span class="muted" style="font-size: 0.875rem;">{{ formatNewsDate(item.published_at) }}</span>
                                </div>
                                <h3 class="news-item__title" style="margin: 0.5rem 0 0.25rem 0; font-size: 1rem;">{{ item.title }}</h3>
                                <p class="muted" style="font-size: 0.875rem; line-height: 1.4; margin: 0;">{{ item.content.substring(0, 100) }}{{ item.content.length > 100 ? '...' : '' }}</p>
                            </article>
                            <div style="margin-top: 1rem; text-align: center;">
                                <button class="btn btn-ghost btn-sm" @click="go('news')">Всі новини →</button>
                            </div>
                        </div>
                        <div v-else class="empty">Новин немає.</div>
                    </template>
                </UiCard>

                <UiCard :wide="true">
                    <template #head><h2>Дедлайни</h2></template>
                    <template #body>
                        <ul v-if="assignments?.length" class="list">
                            <li 
                                v-for="a in assignments.slice(0, 10)" 
                                :key="`${a.course_id}-${a.id}`" 
                                class="list__item"
                                :class="{ 'assignment--overdue': a.status === 'overdue' }"
                            >
                                <div>
                                    <div class="ellipsis" :class="{ 'assignment-title--overdue': a.status === 'overdue' }">
                                        <strong v-if="a.course_name">{{ a.course_name }}</strong>
                                        <span v-if="a.course_name">: </span>
                                        {{ a.title }}
                                    </div>
                                    <div class="muted" :class="{ 'assignment-due--overdue': a.status === 'overdue' }">
                                        <span v-if="a.due_at">до {{ prettyDate(a.due_at) }}</span>
                                        <span v-else>Без дедлайну</span>
                                    </div>
                                </div>
                                <span class="badge" :class="{
                                    'badge--ok': a.status === 'submitted' || a.status === 'graded',
                                    'badge--warn': a.status === 'pending',
                                    'badge--danger': a.status === 'overdue'
                                }">
                                    {{ a.status === 'pending' ? 'Очікує' : a.status === 'submitted' ? 'Подано' : a.status === 'graded' ? 'Оцінено' : a.status === 'overdue' ? 'Прострочено' : a.status }}
                                </span>
                            </li>
                        </ul>
                        <div v-else class="empty">Немає активних дедлайнів.</div>
                    </template>
                </UiCard>
            </template>

            <template v-else-if="view === 'calendar'">
                <MsCalendarWidget />
            </template>

            <template v-else-if="view === 'mail'">
                <MsMailWidget @open="onOpenMail" />
            </template>

            <template v-else-if="view === 'mail-view' && selectedMailId">
                <div class="mail-view-container">
                    <MsMailViewPage :mailId="selectedMailId" @back="closeMailView" />
                </div>
            </template>

            <template v-else-if="view === 'files'">
                <MsFilesWidget />
            </template>

            <template v-else-if="view === 'courses'">
                <MoodleCoursesGrid @open-course="openCourse" />
            </template>

            <template v-else-if="view === 'course-view' && selectedCourseId">
                <MoodleCourseView :course-id="selectedCourseId" @close="closeCourse" />
            </template>

            <template v-else-if="view === 'news'">
                <UiCard :wide="true">
                    <template #head>
                        <div class="row spread">
                            <h2>Стрічка оголошень і новин</h2>
                            <button class="btn btn-ghost btn-sm" @click="loadNews">Оновити</button>
                        </div>
                    </template>
                    <template #body>
                        <div v-if="loadingNews" class="empty">Завантаження новин...</div>
                        <div v-else-if="news?.length" class="news-feed">
                            <article v-for="item in news" :key="item.id" class="news-item">
                                <div class="news-item__header">
                                    <div class="news-item__meta">
                                        <span class="badge" :class="{
                                            'badge--danger': item.priority === 'high',
                                            'badge--warn': item.priority === 'normal',
                                            'badge--soft': item.type === 'news'
                                        }">
                                            {{ item.type === 'announcement' ? 'Оголошення' : 'Новина' }}
                                        </span>
                                        <span class="muted">{{ formatNewsDate(item.published_at) }}</span>
                                    </div>
                                    <h3 class="news-item__title">{{ item.title }}</h3>
                                </div>
                                <div class="news-item__content">
                                    <p>{{ item.content }}</p>
                                </div>
                                <div class="news-item__footer">
                                    <span class="muted">{{ item.author }}</span>
                                </div>
                            </article>
                        </div>
                        <div v-else class="empty">Новин немає.</div>
                    </template>
                </UiCard>
            </template>

            <template v-else-if="view === 'schedule'">
                <UiCard :wide="true">
                    <template #head>
                        <div class="row spread">
                            <h2>Розклад</h2>
                            <div class="row gap-2">
                                <button
                                    class="btn btn-sm"
                                    :class="schedulePeriod === 'today' ? 'btn-primary' : 'btn-ghost'"
                                    @click="setSchedulePeriod('today')"
                                >
                                    Сьогодні
                                </button>
                                <button
                                    class="btn btn-sm"
                                    :class="schedulePeriod === 'week' ? 'btn-primary' : 'btn-ghost'"
                                    @click="setSchedulePeriod('week')"
                                >
                                    Тиждень
                                </button>
                                <button class="btn btn-ghost btn-sm" @click="reloadSchedule">Оновити</button>
                            </div>
                        </div>
                    </template>
                    <template #body>
                        <template v-if="schedulePeriod === 'week' && schedule?.data?.length">
                            <div v-for="day in groupScheduleByDate(schedule.data)" :key="day.date" class="schedule-day schedule-accordion">
                                <button 
                                    class="schedule-day-header" 
                                    @click="toggleScheduleDay(day.date)"
                                    :aria-expanded="isScheduleDayOpen(day.date)"
                                >
                                    <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-primary); flex: 1; text-align: left;">
                                        {{ day.dateFormatted }}
                                        <span class="schedule-day-count">({{ day.events.length }})</span>
                                    </h3>
                                    <span class="schedule-accordion-icon" :class="{ 'schedule-accordion-icon--open': isScheduleDayOpen(day.date) }">
                                        ▼
                                    </span>
                                </button>
                                <div 
                                    class="schedule-day-content" 
                                    :class="{ 'schedule-day-content--collapsed': !isScheduleDayOpen(day.date) }"
                                >
                                    <ul class="timeline">
                                        <li v-for="(e,i) in day.events" :key="`${day.date}-${i}`" class="timeline__item">
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
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <ul v-if="getFilteredTodaySchedule()?.length" class="timeline">
                                <li v-for="(e,i) in getFilteredTodaySchedule()" :key="i" class="timeline__item">
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
                            <div v-else class="empty">Пар не знайдено.</div>
                        </template>
                    </template>
                </UiCard>
            </template>

            <template v-else-if="view === 'grades'">
                <UiCard :wide="true">
                    <template #head><h2>Журнал оцінок</h2></template>
                    <template #body>
                        <div class="placeholder-state">
                            <div class="placeholder-icon">📊</div>
                            <h3 class="placeholder-title">Журнал оцінок</h3>
                            <p class="placeholder-description">
                                Функціонал журналу оцінок знаходиться в розробці.<br>
                                Тут буде відображатися інформація про ваші оцінки з усіх курсів.
                            </p>
                        </div>
                    </template>
                </UiCard>
            </template>

            <template v-else-if="view === 'library'">
                <UiCard :wide="true">
                    <template #head><h2>Бібліотека</h2></template>
                    <template #body>
                        <div class="placeholder-state">
                            <div class="placeholder-icon">📚</div>
                            <h3 class="placeholder-title">Бібліотека</h3>
                            <p class="placeholder-description">
                                Функціонал бібліотеки знаходиться в розробці.<br>
                                Тут буде доступний пошук та перегляд навчальних матеріалів.
                            </p>
                        </div>
                    </template>
                </UiCard>
            </template>
        </section>
    </main>
</template>

<script setup>
import './../../css/dashboard.css'
import { ref, onMounted, computed, nextTick } from 'vue'

import UiCard from './widgets/UiCard.vue'
import ProfileCard from './widgets/ProfileCard.vue'
import MsCalendarWidget from './widgets/MsCalendarWidget.vue'
import MsMailWidget from './widgets/MsMailWidget.vue'
import MsFilesWidget from './widgets/MsFilesWidget.vue'
import MsMailViewer from './widgets/MsMailViewer.vue'
import MsMailViewPage from './MsMailViewPage.vue'
import MoodleCoursesGrid from './MoodleCoursesGrid.vue'
import MoodleCourseView from './MoodleCourseView.vue'

const view = ref('overview')
const tabs = [
    { key: 'overview', label: 'Огляд' },
    { key: 'calendar', label: 'Календар' },
    { key: 'mail',     label: 'Пошта' },
    { key: 'files',    label: 'Файли' },
    { key: 'courses',  label: 'Курси' },
    { key: 'schedule', label: 'Розклад' },
    { key: 'news',     label: 'Новини' },
    { key: 'grades',   label: 'Оцінки' },
    { key: 'library',  label: 'Бібліотека' },
]

const me = ref(null)
const schedule = ref({ data: [] })
const schedulePeriod = ref(localStorage.getItem('schedulePeriod') || 'today')
const openScheduleDays = ref(new Set())
const moodleCourses = ref([])
const loadingMoodleCourses = ref(false)
const assignments = ref([])
const news = ref([])
const loadingNews = ref(false)
const isLoggedIn = ref(false)
const theme = ref(localStorage.getItem('theme') || 'light')
const msPhotoUrl = computed(() => `/api/ms365/photo?ts=${Date.now()}`)

const openedMailId = ref(null)
const selectedMailId = ref(null)
const onOpenMail = (id) => { 
    selectedMailId.value = id
    view.value = 'mail-view'
    history.replaceState(null, '', `#/mail/${id}`)
}
const closeMail = () => { openedMailId.value = null }
const closeMailView = () => {
    selectedMailId.value = null
    view.value = 'mail'
    history.replaceState(null, '', `#/mail`)
}

const selectedCourseId = ref(null)

const go = (key) => {
    view.value = key
    selectedCourseId.value = null
    history.replaceState(null, '', `#/${key}`)
}

const openCourse = (courseId) => {
    selectedCourseId.value = courseId
    view.value = 'course-view'
    history.replaceState(null, '', `#/course/${courseId}`)
}

const closeCourse = () => {
    selectedCourseId.value = null
    view.value = 'courses'
    history.replaceState(null, '', `#/courses`)
}

const toggleTheme = () => {
    theme.value = theme.value === 'light' ? 'dark' : 'light'
    document.documentElement.setAttribute('data-theme', theme.value)
    localStorage.setItem('theme', theme.value)
}
onMounted(() => {
    document.documentElement.setAttribute('data-theme', theme.value)
    const hash = (location.hash || '').replace(/^#\//, '')

    const courseMatch = hash.match(/^course\/(\d+)$/)
    if (courseMatch) {
        selectedCourseId.value = parseInt(courseMatch[1])
        view.value = 'course-view'
    } else {
        const mailMatch = hash.match(/^mail\/(.+)$/)
        if (mailMatch) {
            selectedMailId.value = mailMatch[1]
            view.value = 'mail-view'
        } else if (tabs.some(t => t.key === hash)) {
            view.value = hash
        }
    }
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

const getScheduleParams = () => {
    const now = new Date()
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())

    let from, to
    if (schedulePeriod.value === 'week') {
        from = today
        to = new Date(today)
        to.setDate(to.getDate() + 7)
    } else {
        from = today
        to = new Date(today)
        to.setDate(to.getDate() + 1)
    }

    return `?from=${encodeURIComponent(from.toISOString())}&to=${encodeURIComponent(to.toISOString())}&group=IPZ-11`
}

const fmt = (iso) => new Date(iso)
const two = (n) => String(n).padStart(2, '0')
const shortTime = (iso) => { const d = fmt(iso); return `${two(d.getHours())}:${two(d.getMinutes())}` }
const prettyDate = (iso) => fmt(iso).toLocaleString()
const formatDate = (iso) => {
    const d = fmt(iso)
    const weekdays = ['Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб']
    const months = ['січ', 'лют', 'бер', 'кві', 'тра', 'чер', 'лип', 'сер', 'вер', 'жов', 'лис', 'гру']
    const weekday = weekdays[d.getDay()]
    const day = d.getDate()
    const month = months[d.getMonth()]
    return `${weekday}, ${day} ${month}`
}

const formatNewsDate = (iso) => {
    const d = fmt(iso)
    const now = new Date()
    const diff = now - d
    const hours = Math.floor(diff / (1000 * 60 * 60))
    const days = Math.floor(diff / (1000 * 60 * 60 * 24))

    if (hours < 1) return 'щойно'
    if (hours < 24) return `${hours} ${hours === 1 ? 'година' : hours < 5 ? 'години' : 'годин'} тому`
    if (days === 1) return 'вчора'
    if (days < 7) return `${days} ${days === 1 ? 'день' : days < 5 ? 'дні' : 'днів'} тому`

    const months = ['січня', 'лютого', 'березня', 'квітня', 'травня', 'червня',
                     'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня']
    return `${d.getDate()} ${months[d.getMonth()]}`
}

const getDateKey = (iso) => {
    const d = fmt(iso)
    return `${d.getFullYear()}-${two(d.getMonth() + 1)}-${two(d.getDate())}`
}

const getFilteredTodaySchedule = () => {
    if (!schedule.value?.data?.length) return []
    
    const now = new Date()
    return schedule.value.data.filter(event => {
        const eventEnd = new Date(event.end)
        return eventEnd >= now
    })
}

const groupScheduleByDate = (scheduleData) => {
    if (!scheduleData || !Array.isArray(scheduleData)) return []

    const today = new Date()
    today.setHours(0, 0, 0, 0) // Встановлюємо на початок дня для порівняння

    const grouped = {}
    scheduleData.forEach(event => {
        const eventDate = new Date(event.start)
        eventDate.setHours(0, 0, 0, 0)
        
        if (eventDate < today) {
            return
        }
        
        const dateKey = getDateKey(event.start)
        if (!grouped[dateKey]) {
            grouped[dateKey] = []
        }
        grouped[dateKey].push(event)
    })

    return Object.entries(grouped)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([date, events]) => ({
            date,
            dateFormatted: formatDate(events[0].start),
            events: events.sort((a, b) => new Date(a.start) - new Date(b.start))
        }))
}

const reloadSchedule = async () => {
    schedule.value = await fetchJSON('/api/schedule' + getScheduleParams())
    await nextTick()
    if (schedulePeriod.value === 'week') {
        initializeScheduleAccordion()
    }
}

const setSchedulePeriod = (period) => {
    schedulePeriod.value = period
    localStorage.setItem('schedulePeriod', period)
    reloadSchedule()
    if (period === 'week') {
        initializeScheduleAccordion()
    }
}

const initializeScheduleAccordion = () => {
    const today = new Date()
    const todayKey = `${today.getFullYear()}-${two(today.getMonth() + 1)}-${two(today.getDate())}`
    
    if (schedule.value?.data?.length) {
        const groupedDays = groupScheduleByDate(schedule.value.data)
        openScheduleDays.value = new Set()
        
        groupedDays.forEach(day => {
            if (day.date !== todayKey) {
                openScheduleDays.value.add(day.date)
            }
        })
    } else {
        openScheduleDays.value = new Set()
    }
}

const toggleScheduleDay = (dateKey) => {
    if (openScheduleDays.value.has(dateKey)) {
        openScheduleDays.value.delete(dateKey)
    } else {
        openScheduleDays.value.add(dateKey)
    }
}

const isScheduleDayOpen = (dateKey) => {
    return !openScheduleDays.value.has(dateKey)
}

const loadNews = async () => {
    loadingNews.value = true
    try {
        news.value = await fetchJSON('/api/news').catch(() => [])
    } catch {
        news.value = []
    } finally {
        loadingNews.value = false
    }
}

const msLogin = () => { location.href = '/login' }

const loadMoodleCourses = async () => {
    loadingMoodleCourses.value = true
    try {
        const response = await fetchJSON('/api/moodle/courses').catch(() => ({ courses: [] }))
        let courses = []
        if (Array.isArray(response)) {
            courses = response.filter(c => !c.hasOwnProperty('visible') || c.visible !== 0)
        } else if (Array.isArray(response.courses)) {
            courses = response.courses.filter(c => !c.hasOwnProperty('visible') || c.visible !== 0)
        }

        moodleCourses.value = courses

        const allAssignments = []
        courses.forEach(course => {
            if (Array.isArray(course.assignments) && course.assignments.length > 0) {
                course.assignments.forEach(assignment => {
                    allAssignments.push({
                        ...assignment,
                        course_name: course.fullname || course.shortname || ''
                    })
                })
            }
        })

        const now = new Date()
        allAssignments.forEach(assignment => {
            if (assignment.due_at) {
                const dueDate = new Date(assignment.due_at)
                if (dueDate < now && assignment.status !== 'submitted' && assignment.status !== 'graded') {
                    assignment.status = 'overdue'
                } else if (!assignment.status) {
                    assignment.status = 'pending'
                }
            } else if (!assignment.status) {
                assignment.status = 'pending'
            }
        })

        allAssignments.sort((a, b) => {
            if (!a.due_at && !b.due_at) return 0
            if (!a.due_at) return 1
            if (!b.due_at) return -1
            return new Date(a.due_at) - new Date(b.due_at)
        })

        assignments.value = allAssignments
    } catch {
        moodleCourses.value = []
    } finally {
        loadingMoodleCourses.value = false
    }
}

onMounted(async () => {
    try {
        me.value = await fetchJSON('/api/me')
        isLoggedIn.value = true

        await reloadSchedule()
        if (schedulePeriod.value === 'week') {
            await nextTick()
            initializeScheduleAccordion()
        }
        await loadMoodleCourses()
        await loadNews()
    } catch {
        isLoggedIn.value = false
    }
})
</script>

<style scoped>
.schedule-day {
    margin-bottom: 2rem;
}

.schedule-day:last-child {
    margin-bottom: 0;
}

.schedule-day h3 {
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--ring);
    margin-bottom: 0.75rem;
}

.news-feed {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.news-item {
    padding: 1rem;
    border: 1px solid var(--ring);
    border-radius: 8px;
    background: var(--card);
    transition: box-shadow 0.2s;
}

.news-item:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.news-item--compact {
    padding: 0.75rem;
    border: none;
    border-bottom: 1px solid var(--ring);
    border-radius: 0;
}

.news-item--compact:last-child {
    border-bottom: none;
}

.news-item__header {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.news-item__meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.news-item__title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--ink);
    margin: 0;
    line-height: 1.4;
}

.news-item__content {
    margin-bottom: 0.75rem;
    line-height: 1.6;
    color: var(--ink);
}

.news-item__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    border-top: 1px solid var(--ring);
    font-size: 0.875rem;
}
</style>
