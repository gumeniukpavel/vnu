<template>
    <div class="moodle-courses-grid vnu-moodle-grid">
        <div class="card card--wide">
            <div class="card__head">
                <h2>Курси Moodle</h2>
            </div>
            <div class="card__body">
                <div class="vnu-toolbar-row">
                    <div class="vnu-search">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="vnu-input"
                            placeholder="Пошук курсів..."
                            @input="handleSearch"
                        />
                    </div>

                    <div class="vnu-sort">
                        <select v-model="sortOrder" class="vnu-select" @change="handleSort">
                            <option value="asc">A → Z</option>
                            <option value="desc">Z → A</option>
                        </select>
                    </div>

                    <div class="vnu-per-page">
                        <select v-model="perPage" class="vnu-select" @change="handlePerPageChange">
                            <option value="12">12 на сторінку</option>
                            <option value="24">24 на сторінку</option>
                            <option value="48">48 на сторінку</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="vnu-loading">
            <div class="vnu-skeleton-grid">
                <div v-for="n in 8" :key="n" class="card vnu-skeleton-card">
                    <div class="vnu-skeleton-image"></div>
                    <div class="vnu-skeleton-content">
                        <div class="vnu-skeleton-line vnu-skeleton-line--title"></div>
                        <div class="vnu-skeleton-line"></div>
                        <div class="vnu-skeleton-line"></div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else-if="error" class="card card--wide vnu-error">
            <div class="card__body">
                <div class="vnu-alert vnu-alert--error">
                    <p>{{ error }}</p>
                    <button class="btn btn-primary" @click="loadCourses">Спробувати знову</button>
                </div>
            </div>
        </div>

        <div v-else class="vnu-courses-grid">
            <div v-if="paginatedCourses.length === 0" class="card card--wide">
                <div class="card__body">
                    <div class="empty">Курси не знайдено.</div>
                </div>
            </div>
            <div v-else class="vnu-cards">
                <div
                    v-for="course in paginatedCourses"
                    :key="course.id"
                    class="card vnu-card"
                >
                    <div class="vnu-card-image-wrapper">
                        <img
                            v-if="course.image"
                            :src="course.image"
                            :alt="course.fullname"
                            class="vnu-card-image"
                            @error="handleImageError"
                        />
                        <div v-else class="vnu-card-placeholder">
                            <span class="vnu-card-initials">{{ getCourseInitials(course.fullname) }}</span>
                        </div>
                    </div>

                    <div class="vnu-card-body">
                        <h5 class="vnu-card-title">{{ course.fullname }}</h5>
                        <p class="vnu-card-shortname">{{ course.shortname }}</p>
                        <p
                            v-if="course.summary"
                            class="vnu-card-summary line-clamp-3"
                        >{{ stripHtml(course.summary) }}</p>
                    </div>

                    <div class="vnu-card-footer">
                        <button
                            class="btn btn-primary w-100"
                            @click="emit('open-course', course.id)"
                        >
                            Відкрити курс
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!loading && !error && filteredCourses.length > 0" class="card card--wide vnu-pagination">
            <div class="card__body">
                <div class="vnu-pagination-inner">
                    <div class="vnu-pagination-info">
                        Показано {{ startIndex + 1 }}-{{ endIndex }} з {{ filteredCourses.length }}
                    </div>
                    <div class="vnu-pagination-controls">
                        <button
                            class="btn btn-ghost"
                            :disabled="currentPage === 1"
                            @click="currentPage--"
                        >
                            ← Попередня
                        </button>
                        <span class="vnu-page-info">
                            Сторінка {{ currentPage }} з {{ totalPages }}
                        </span>
                        <button
                            class="btn btn-ghost"
                            :disabled="currentPage === totalPages"
                            @click="currentPage++"
                        >
                            Наступна →
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const emit = defineEmits(['open-course'])

const courses = ref([])
const loading = ref(true)
const error = ref(null)
const searchQuery = ref('')
const sortOrder = ref('asc')
const perPage = ref(12)
const currentPage = ref(1)

const fetchJSON = async (url) => {
    const res = await fetch(url, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
    
    const data = await res.json()
    
    if (!res.ok) {
        const error = new Error(data.message || data.error || `HTTP ${res.status}`)
        error.response = res
        error.data = data
        throw error
    }
    
    return data
}

const filteredCourses = computed(() => {
    let result = [...courses.value]
    
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase().trim()
        result = result.filter(course => 
            course.fullname.toLowerCase().includes(query) ||
            course.shortname.toLowerCase().includes(query)
        )
    }
    
    result.sort((a, b) => {
        const aName = a.fullname.toLowerCase()
        const bName = b.fullname.toLowerCase()
        if (sortOrder.value === 'asc') {
            return aName.localeCompare(bName)
        } else {
            return bName.localeCompare(aName)
        }
    })
    
    return result
})

const totalPages = computed(() => {
    return Math.ceil(filteredCourses.value.length / perPage.value)
})

const startIndex = computed(() => {
    return (currentPage.value - 1) * perPage.value
})

const endIndex = computed(() => {
    return Math.min(startIndex.value + perPage.value, filteredCourses.value.length)
})

const paginatedCourses = computed(() => {
    return filteredCourses.value.slice(startIndex.value, endIndex.value)
})

const handleSearch = () => {
    currentPage.value = 1
}

const handleSort = () => {
    currentPage.value = 1
}

const handlePerPageChange = () => {
    currentPage.value = 1
}

const handleImageError = (event) => {
    const wrapper = event.target.closest('.vnu-card-image-wrapper')
    if (wrapper) {
        const placeholder = wrapper.querySelector('.vnu-card-placeholder')
        if (placeholder) {
            event.target.style.display = 'none'
            placeholder.style.display = 'flex'
        }
    }
}

const getCourseInitials = (fullname) => {
    if (!fullname) return '?'
    const words = fullname.split(' ').filter(w => w.length > 0)
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase()
    }
    return fullname.substring(0, 2).toUpperCase()
}

const stripHtml = (html) => {
    if (!html) return ''
    const tmp = document.createElement('DIV')
    tmp.innerHTML = html
    let text = tmp.textContent || tmp.innerText || ''
    text = text.replace(/\s+/g, ' ').trim()
    return text
}

const loadCourses = async () => {
    loading.value = true
    error.value = null
    try {
        const response = await fetchJSON('/api/moodle/courses')
        
        if (response.error) {
            error.value = response.message || response.error || 'Помилка завантаження курсів'
            courses.value = response.courses || []
            return
        }
        
        if (Array.isArray(response)) {
            courses.value = response
        } else if (Array.isArray(response.courses)) {
            courses.value = response.courses
        } else {
            courses.value = []
        }
        
        courses.value = courses.value.filter(c => !c.hasOwnProperty('visible') || c.visible !== 0)
    } catch (e) {
        let errorMsg = 'Помилка завантаження курсів'
        try {
            const errorData = await e.response?.json()
            if (errorData?.message) {
                errorMsg = errorData.message
            } else if (errorData?.error) {
                errorMsg = errorData.error
            }
        } catch {
            errorMsg = e.message || 'Невідома помилка'
        }
        error.value = errorMsg
        courses.value = []
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    loadCourses()
})
</script>

<style scoped>
.vnu-moodle-grid {
    width: 100%;
    max-width: 100%;
    grid-column: 1 / -1;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.vnu-courses-grid {
    width: 100%;
}

.vnu-toolbar-row {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@media (min-width: 768px) {
    .vnu-toolbar-row {
        flex-direction: row;
        align-items: center;
        gap: 12px;
    }
}

.vnu-search {
    flex: 1;
    min-width: 0;
}

.vnu-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 8px;
    font-size: 14px;
    background: var(--card, #ffffff);
    color: var(--ink, #0f172a);
}

.vnu-input:focus {
    outline: none;
    border-color: var(--brand, #0ea5e9);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.vnu-sort,
.vnu-per-page {
    min-width: 150px;
}

.vnu-select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 8px;
    font-size: 14px;
    background: var(--card, #ffffff);
    color: var(--ink, #0f172a);
    cursor: pointer;
}

.vnu-select:focus {
    outline: none;
    border-color: var(--brand, #0ea5e9);
}

.vnu-loading {
    width: 100%;
}

.vnu-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 24px;
}

@media (min-width: 576px) {
    .vnu-skeleton-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 768px) {
    .vnu-skeleton-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1200px) {
    .vnu-skeleton-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

.vnu-skeleton-card {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.vnu-skeleton-image {
    width: 100%;
    height: 200px;
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 37%, #f3f4f6 63%);
    background-size: 400% 100%;
    animation: shimmer 1.4s ease infinite;
}

.vnu-skeleton-content {
    padding: 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.vnu-skeleton-line {
    height: 16px;
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 37%, #f3f4f6 63%);
    background-size: 400% 100%;
    animation: shimmer 1.4s ease infinite;
    border-radius: 4px;
}

.vnu-skeleton-line--title {
    height: 20px;
    width: 80%;
}

@keyframes shimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: 0 0;
    }
}

.vnu-error {
    width: 100%;
}

.vnu-alert {
    padding: 16px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.vnu-alert--error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

.vnu-courses-grid {
    width: 100%;
}

.vnu-cards {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 16px;
}

@media (min-width: 576px) {
    .vnu-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 768px) {
    .vnu-cards {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (min-width: 1200px) {
    .vnu-cards {
        grid-template-columns: repeat(4, 1fr);
    }
}

.vnu-card {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.vnu-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(2,6,23,.1);
}

.vnu-card-image-wrapper {
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: var(--bg, #f8fafc);
    position: relative;
}

.vnu-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.vnu-card-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--brand, #0ea5e9), #60a5fa);
    color: white;
    font-size: 48px;
    font-weight: 700;
}

.vnu-card-body {
    padding: 14px 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.vnu-card-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 8px 0;
    color: var(--ink, #0f172a);
    line-height: 1.4;
}

.vnu-card-shortname {
    font-size: 14px;
    color: var(--muted, #6b7280);
    margin: 0 0 12px 0;
}

.vnu-card-summary {
    font-size: 14px;
    color: var(--ink, #0f172a);
    line-height: 1.6;
    flex: 1;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vnu-card-footer {
    padding: 14px 16px;
    border-top: 1px solid var(--ring, #e2e8f0);
}

.vnu-card-footer .btn {
    width: 100%;
    text-align: center;
}

.vnu-pagination {
    margin-top: 0;
}

.vnu-pagination-inner {
    display: flex;
    flex-direction: column;
    gap: 16px;
    align-items: center;
}

@media (min-width: 768px) {
    .vnu-pagination-inner {
        flex-direction: row;
        justify-content: space-between;
    }
}

.vnu-pagination-info {
    font-size: 14px;
    color: var(--muted, #6b7280);
}

.vnu-pagination-controls {
    display: flex;
    align-items: center;
    gap: 16px;
}

.vnu-page-info {
    font-size: 14px;
    color: var(--ink, #0f172a);
}


.btn {
    padding: 10px 16px;
    border-radius: 8px;
    border: 1px solid var(--ring, #e2e8f0);
    background: var(--card, #ffffff);
    color: var(--ink, #0f172a);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-block;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.btn-primary {
    background: var(--brand, #0ea5e9);
    color: white;
    border: none;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--muted, #6b7280);
    color: var(--muted, #6b7280);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.btn-ghost {
    background: transparent;
    border: 1px solid var(--ring, #e2e8f0);
}

.btn-ghost:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}


[data-theme="dark"] .vnu-input,
[data-theme="dark"] .vnu-select {
    background: var(--card, #1e293b);
    border-color: var(--ring, #334155);
    color: var(--ink, #f8fafc);
}

[data-theme="dark"] .vnu-card-title {
    color: var(--ink, #f8fafc);
}

[data-theme="dark"] .vnu-card-summary {
    color: var(--ink, #f8fafc);
}
</style>


