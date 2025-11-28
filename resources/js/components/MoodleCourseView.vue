<template>
    <div class="moodle-course-view vnu-course-view vnu-course-view--fullwidth">
        <div class="vnu-course-header mb-4">
            <button class="btn btn-ghost" @click="$emit('close')">
                ← Назад до курсів
            </button>
        </div>

        <div v-if="loading" class="vnu-loading">
            <div class="vnu-skeleton-course">
                <div class="vnu-skeleton-image"></div>
                <div class="vnu-skeleton-content">
                    <div class="vnu-skeleton-line vnu-skeleton-line--title"></div>
                    <div class="vnu-skeleton-line"></div>
                    <div class="vnu-skeleton-line"></div>
                </div>
            </div>
        </div>

        <div v-else-if="error" class="vnu-error">
            <div class="vnu-alert vnu-alert--error">
                <p>{{ error }}</p>
                <button class="btn btn-primary" @click="loadCourse">Спробувати знову</button>
            </div>
        </div>

        <div v-else-if="course" class="vnu-course-content">
            <div class="card card--wide vnu-course-hero">
                <div class="card__head">
                    <div>
                        <h2 class="vnu-course-title">{{ course.fullname }}</h2>
                        <p class="vnu-course-shortname">{{ course.shortname }}</p>
                    </div>
                </div>
                <div class="card__body">
                    <div class="vnu-course-hero-inner">
                        <div v-if="course.image" class="vnu-course-hero-image">
                            <img :src="course.image" :alt="course.fullname" class="vnu-course-image" />
                        </div>
                        <div v-else class="vnu-course-hero-placeholder">
                            <span class="vnu-course-initials">{{ getCourseInitials(course.fullname) }}</span>
                        </div>
                        <div v-if="course.summary" class="vnu-course-summary" v-html="course.summary"></div>
                    </div>
                </div>
            </div>

            <div v-if="sections.length > 1" class="vnu-course-nav mb-4">
                <div class="vnu-course-nav-wrapper">
                    <button
                        v-for="(section, index) in sections"
                        :key="section.id || index"
                        class="btn"
                        :class="activeSection === index ? 'btn-primary' : 'btn-ghost'"
                        @click="activeSection = index"
                    >
                        {{ section.name || `Розділ ${index + 1}` }}
                    </button>
                </div>
            </div>

            <div class="vnu-course-sections">
                <div
                    v-for="(section, sectionIndex) in sections"
                    :key="section.id || sectionIndex"
                    class="card card--wide vnu-course-section"
                    :class="{ 'vnu-course-section--active': sections.length <= 1 || activeSection === sectionIndex }"
                    :style="{ display: sections.length <= 1 || activeSection === sectionIndex ? 'block' : 'none' }"
                >
                    <div class="card__head">
                        <h2 class="vnu-section-title">{{ section.name || `Розділ ${sectionIndex + 1}` }}</h2>
                    </div>
                    <div class="card__body">
                    
                    <div v-if="section.summary" class="vnu-section-summary" v-html="section.summary"></div>

                    <div v-if="section.modules && section.modules.length > 0" class="vnu-section-modules">
                        <div
                            v-for="(module, moduleIndex) in section.modules"
                            :key="module.id || moduleIndex"
                            class="vnu-module"
                            :data-modname="module.modname"
                        >
                            <div class="vnu-module-header">
                                <h3 class="vnu-module-title">
                                    <span class="vnu-module-icon">{{ getModuleIcon(module.modname) }}</span>
                                    {{ module.name || 'Модуль' }}
                                </h3>
                                <div class="vnu-module-meta">
                                    <span v-if="module.modplural" class="vnu-module-type">{{ module.modplural }}</span>
                                    <span v-if="module.visible === 0" class="vnu-module-hidden">Приховано</span>
                                </div>
                            </div>

                            <div v-if="module.description" class="vnu-module-description" v-html="module.description"></div>

                            <div v-if="module.modname === 'url' && module.url" class="vnu-module-url">
                                <a
                                    :href="module.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn btn-primary w-100"
                                >
                                    Відкрити посилання →
                                </a>
                            </div>

                            <div v-if="module.modname === 'assign' || module.modname === 'assignment'" class="vnu-module-actions">
                                <button 
                                    class="btn btn-primary btn-sm"
                                    @click="openAssignmentUploadDialog(module.id, module.name)"
                                >
                                    📤 Завантажити файл до завдання
                                </button>
                                <a
                                    v-if="module.url"
                                    :href="module.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="btn btn-outline btn-sm"
                                    style="margin-left: 8px;"
                                >
                                    Відкрити в Moodle →
                                </a>
                            </div>

                            <div v-if="module.contents && module.contents.length > 0" class="vnu-module-contents">
                                <h4 class="vnu-contents-title">Файли та матеріали:</h4>
                                <div class="vnu-contents-list">
                                    <div
                                        v-for="(content, contentIndex) in module.contents"
                                        :key="contentIndex"
                                        class="vnu-content-item"
                                    >
                                        <div class="vnu-content-info">
                                            <span class="vnu-content-icon">{{ getContentIcon(content.type || content.mimetype) }}</span>
                                            <div class="vnu-content-details">
                                                <span class="vnu-content-name">{{ content.filename || content.name || 'Файл' }}</span>
                                                <span v-if="content.filesize" class="vnu-content-size">
                                                    {{ formatFileSize(content.filesize) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="vnu-content-actions">
                                            <a
                                                v-if="content.fileurl && canPreview(content.type || content.mimetype)"
                                                :href="content.fileurl"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-primary btn-sm"
                                            >
                                                Переглянути
                                            </a>
                                            <a
                                                v-if="content.fileurl"
                                                :href="content.fileurl"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-outline btn-sm"
                                                :download="content.filename || 'file'"
                                                @click="downloadFile(content)"
                                            >
                                                Завантажити
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="module.descriptionfiles && module.descriptionfiles.length > 0" class="vnu-module-files">
                                <h4 class="vnu-files-title">Додаткові файли:</h4>
                                <div class="vnu-file-list">
                                    <div
                                        v-for="(file, fileIndex) in module.descriptionfiles"
                                        :key="fileIndex"
                                        class="vnu-file-item"
                                    >
                                        <a
                                            :href="file.fileurl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="vnu-file-link"
                                            @click="downloadFile(file)"
                                        >
                                            <span class="vnu-file-icon">{{ getContentIcon(file.mimetype) }}</span>
                                            {{ file.filename }}
                                            <span v-if="file.filesize" class="vnu-file-size">
                                                ({{ formatFileSize(file.filesize) }})
                                            </span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="vnu-empty-section">
                        <p>У цьому розділі поки немає матеріалів.</p>
                    </div>
                    </div>
                </div>
            </div>

            <div v-if="course.courseUrl" class="card card--wide vnu-course-footer">
                <div class="card__body">
                    <div class="vnu-course-footer-actions">
                        <a
                            :href="course.courseUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-primary"
                        >
                            Відкрити курс в Moodle →
                        </a>
                    </div>
                </div>
            </div>

            <div v-if="showAssignmentUploadDialog" class="vnu-upload-dialog-overlay" @click.self="showAssignmentUploadDialog = false">
                <div class="vnu-upload-dialog">
                    <div class="vnu-upload-dialog-header">
                        <h3>Завантажити файл до завдання</h3>
                        <button class="vnu-upload-dialog-close" @click="showAssignmentUploadDialog = false">×</button>
                    </div>
                    <div class="vnu-upload-dialog-body">
                        <div class="vnu-form-group">
                            <p class="vnu-assignment-info">
                                <strong>Завдання:</strong> {{ assignmentUploadForm.assignmentName }}
                            </p>
                        </div>
                        <form @submit.prevent="uploadAssignmentFile" class="vnu-upload-form">
                            <div class="vnu-form-group">
                                <label for="assignment-upload-file">Файл:</label>
                                <input
                                    id="assignment-upload-file"
                                    ref="assignmentFileInput"
                                    type="file"
                                    required
                                    @change="handleAssignmentFileSelect"
                                    class="vnu-form-input"
                                />
                            </div>
                            <div v-if="assignmentUploadError" class="vnu-alert vnu-alert--error">
                                <p>{{ assignmentUploadError }}</p>
                            </div>
                            <div class="vnu-upload-dialog-footer">
                                <button type="button" class="btn btn-ghost" @click="showAssignmentUploadDialog = false">
                                    Скасувати
                                </button>
                                <button type="submit" class="btn btn-primary" :disabled="uploadingAssignmentFile">
                                    <span v-if="uploadingAssignmentFile">Завантаження...</span>
                                    <span v-else>Завантажити</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'

const props = defineProps({
    courseId: {
        type: Number,
        required: true
    }
})

const emit = defineEmits(['close'])

const course = ref(null)
const contents = ref([])
const loading = ref(true)
const error = ref(null)
const activeSection = ref(0)

const showAssignmentUploadDialog = ref(false)
const uploadingAssignmentFile = ref(false)
const assignmentUploadError = ref(null)
const assignmentFileInput = ref(null)
const assignmentUploadForm = ref({
    assignmentId: null,
    assignmentName: '',
    file: null
})

const sections = computed(() => {
    if (!contents.value || !Array.isArray(contents.value)) {
        return []
    }
    return contents.value.map((section, index) => {
        const sectionCopy = { ...section }
        
        if (!sectionCopy.name) {
            if (index === 0) {
                sectionCopy.name = 'Загальне'
            } else {
                sectionCopy.name = `Секція ${index}`
            }
        }
        
        if (sectionCopy.modules && Array.isArray(sectionCopy.modules)) {
            sectionCopy.modules = sectionCopy.modules.filter(module => {
                return !module.hasOwnProperty('visible') || module.visible !== 0
            })
        }
        
        sectionCopy.sectionId = sectionCopy.id || index
        
        return sectionCopy
    })
})

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

const loadCourse = async () => {
    loading.value = true
    error.value = null
    
    try {
        const [courseData, contentsData] = await Promise.all([
            fetchJSON(`/api/moodle/courses/${props.courseId}`),
            fetchJSON(`/api/moodle/courses/${props.courseId}/contents`).catch(() => [])
        ])
        
        course.value = courseData
        contents.value = Array.isArray(contentsData) ? contentsData : []
        
        if (contents.value.length === 0) {
        }
    } catch (e) {
        let errorMsg = 'Помилка завантаження курсу'
        try {
            const errorData = e.data || await e.response?.json()
            if (errorData?.message) {
                errorMsg = errorData.message
            } else if (errorData?.error) {
                errorMsg = errorData.error
            }
        } catch {
            errorMsg = e.message || 'Невідома помилка'
        }
        error.value = errorMsg
        course.value = null
        contents.value = []
    } finally {
        loading.value = false
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

const getModuleIcon = (modname) => {
    const icons = {
        'resource': '📄',
        'file': '📎',
        'folder': '📁',
        'url': '🔗',
        'page': '📃',
        'assign': '📝',
        'assignment': '📝',
        'quiz': '❓',
        'forum': '💬',
        'label': '🏷️',
        'book': '📖',
        'lesson': '📚',
        'scorm': '🎓',
        'workshop': '🔧',
        'glossary': '📖',
        'wiki': '📝',
        'chat': '💬',
        'choice': '☑️',
        'feedback': '📋',
        'survey': '📊',
        'data': '💾',
    }
    return icons[modname] || '📌'
}

const getContentIcon = (type) => {
    if (!type) return '📄'
    
    if (type.includes('pdf')) return '📕'
    if (type.includes('word') || type.includes('document')) return '📘'
    if (type.includes('excel') || type.includes('spreadsheet')) return '📗'
    if (type.includes('powerpoint') || type.includes('presentation')) return '📙'
    if (type.includes('image')) return '🖼️'
    if (type.includes('video')) return '🎥'
    if (type.includes('audio')) return '🎵'
    if (type.includes('zip') || type.includes('archive')) return '📦'
    
    return '📄'
}

const formatFileSize = (bytes) => {
    if (!bytes) return ''
    const sizes = ['Б', 'КБ', 'МБ', 'ГБ']
    if (bytes === 0) return '0 Б'
    const i = Math.floor(Math.log(bytes) / Math.log(1024))
    return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i]
}

const canPreview = (type) => {
    if (!type) return false
    const typeLower = type.toLowerCase()
    const previewable = ['pdf', 'image', 'text', 'html', 'video', 'audio']
    return previewable.some(t => typeLower.includes(t))
}

const downloadFile = (file) => {
}

const getSectionName = (sectionId) => {
    const section = sections.value.find(s => (s.id || s.sectionId) === sectionId)
    if (section) {
        return section.name || 'Загальне'
    }
    const sectionIndex = sections.value.findIndex(s => (s.id || s.sectionId) === sectionId)
    if (sectionIndex === 0) return 'Загальне'
    return `Секція ${sectionIndex}`
}



const openAssignmentUploadDialog = (assignmentId, assignmentName) => {
    assignmentUploadForm.value = {
        assignmentId: assignmentId,
        assignmentName: assignmentName || 'Завдання',
        file: null
    }
    assignmentUploadError.value = null
    showAssignmentUploadDialog.value = true
}

const handleAssignmentFileSelect = (event) => {
    const file = event.target.files[0]
    if (file) {
        assignmentUploadForm.value.file = file
    }
}

const uploadAssignmentFile = async () => {
    if (!assignmentUploadForm.value.file) {
        assignmentUploadError.value = 'Будь ласка, виберіть файл'
        return
    }

    uploadingAssignmentFile.value = true
    assignmentUploadError.value = null

    try {
        const formData = new FormData()
        formData.append('file', assignmentUploadForm.value.file)

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''
        
        const response = await fetch(`/api/moodle/courses/${props.courseId}/assignments/${assignmentUploadForm.value.assignmentId}/upload`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: formData
        })

        const data = await response.json()

        if (!response.ok) {
            throw new Error(data.message || data.error || 'Помилка завантаження файлу')
        }

        showAssignmentUploadDialog.value = false
        assignmentUploadForm.value = { assignmentId: null, assignmentName: '', file: null }
        if (assignmentFileInput.value) {
            assignmentFileInput.value.value = ''
        }
        
        alert('Файл успішно завантажено до завдання!')
    } catch (e) {
        assignmentUploadError.value = e.message || 'Помилка завантаження файлу. Перевірте права доступу та налаштування Moodle.'
    } finally {
        uploadingAssignmentFile.value = false
    }
}

onMounted(() => {
    loadCourse()
})
</script>

<style scoped>
.vnu-course-view {
    width: 100%;
    max-width: 100%;
}

.vnu-course-view--fullwidth {
    grid-column: 1 / -1 !important;
    width: 100% !important;
    max-width: 100% !important;
}

.vnu-course-header {
    margin-bottom: 16px;
}

.vnu-course-hero {
    margin-bottom: 16px;
}

.vnu-course-hero-inner {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

@media (min-width: 768px) {
    .vnu-course-hero-inner {
        flex-direction: row;
    }
}

.vnu-course-hero-image,
.vnu-course-hero-placeholder {
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: var(--bg, #f8fafc);
    border-radius: 12px;
}

@media (min-width: 768px) {
    .vnu-course-hero-image,
    .vnu-course-hero-placeholder {
        width: 300px;
        height: 200px;
        flex-shrink: 0;
    }
}

.vnu-course-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.vnu-course-hero-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--brand, #0ea5e9), #60a5fa);
    color: white;
    font-size: 72px;
    font-weight: 700;
}

.vnu-course-title {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 8px 0;
    color: var(--ink, #0f172a);
}

.vnu-course-shortname {
    font-size: 16px;
    color: var(--muted, #6b7280);
    margin: 0 0 16px 0;
}

.vnu-course-summary {
    font-size: 16px;
    line-height: 1.6;
    color: var(--ink, #0f172a);
}

.vnu-course-nav {
    margin-bottom: 16px;
}

.vnu-course-nav-wrapper {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    overflow-x: auto;
    padding-bottom: 8px;
    -webkit-overflow-scrolling: touch;
}

.vnu-course-nav-wrapper .btn {
    white-space: nowrap;
    flex-shrink: 0;
}

@media (max-width: 575px) {
    .vnu-course-nav-wrapper {
        gap: 6px;
    }
    
    .vnu-course-nav-wrapper .btn {
        font-size: 14px;
        padding: 8px 12px;
    }
}

.vnu-course-sections {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.vnu-course-section {
    margin-bottom: 0;
}

.vnu-section-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0;
    color: var(--ink, #0f172a);
}

.vnu-section-summary {
    margin-bottom: 16px;
    padding: 16px;
    background: var(--bg, #f8fafc);
    border-radius: 8px;
    line-height: 1.6;
}

.vnu-section-modules {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.vnu-module {
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 12px;
    padding: 20px;
    background: var(--bg, #f8fafc);
}

.vnu-module-header {
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
}

.vnu-module-title {
    font-size: 20px;
    font-weight: 600;
    margin: 0;
    color: var(--ink, #0f172a);
    display: flex;
    align-items: center;
    gap: 8px;
}

.vnu-module-icon {
    font-size: 24px;
}

.vnu-module-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.vnu-module-type {
    font-size: 12px;
    color: var(--muted, #6b7280);
    padding: 4px 8px;
    background: var(--bg, #f8fafc);
    border-radius: 4px;
}

.vnu-module-hidden {
    font-size: 12px;
    color: var(--warn, #f59e0b);
    padding: 4px 8px;
    background: rgba(245, 158, 11, 0.1);
    border-radius: 4px;
}

.vnu-module-link {
    margin-top: 12px;
    margin-bottom: 16px;
}

.vnu-module-description {
    margin-bottom: 16px;
    padding: 12px;
    background: var(--card, #ffffff);
    border-radius: 8px;
    line-height: 1.6;
}

.vnu-module-contents {
    margin-top: 16px;
}

.vnu-contents-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 12px 0;
    color: var(--ink, #0f172a);
}

.vnu-contents-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.vnu-content-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
    background: var(--card, #ffffff);
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.vnu-content-item:hover {
    border-color: var(--brand, #0ea5e9);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

@media (max-width: 575px) {
    .vnu-content-item {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .vnu-content-actions {
        width: 100%;
        display: flex;
        gap: 8px;
    }
    
    .vnu-content-actions .btn {
        flex: 1;
    }
}

.vnu-content-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
}

.vnu-content-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.vnu-content-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
    min-width: 0;
}

.vnu-content-name {
    flex: 1;
    min-width: 0;
    word-break: break-word;
    font-weight: 500;
}

.vnu-content-size {
    font-size: 12px;
    color: var(--muted, #6b7280);
}

.vnu-content-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
    align-items: center;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 14px;
}

.vnu-module-files {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--ring, #e2e8f0);
}

.vnu-files-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 12px 0;
    color: var(--ink, #0f172a);
}

.vnu-file-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.vnu-file-item {
    padding: 8px 12px;
    background: var(--card, #ffffff);
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 8px;
}

.vnu-file-link {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    color: var(--ink, #0f172a);
    transition: color 0.2s;
}

.vnu-file-link:hover {
    color: var(--brand, #0ea5e9);
}

.vnu-file-icon {
    font-size: 18px;
}

.vnu-file-size {
    font-size: 12px;
    color: var(--muted, #6b7280);
    margin-left: auto;
}

.vnu-empty-section {
    text-align: center;
    padding: 48px 16px;
    color: var(--muted, #6b7280);
}

.vnu-skeleton-course {
    background: var(--card, #ffffff);
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 16px;
    overflow: hidden;
}

.vnu-skeleton-image {
    width: 100%;
    height: 300px;
    background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 37%, #f3f4f6 63%);
    background-size: 400% 100%;
    animation: shimmer 1.4s ease infinite;
}

.vnu-skeleton-content {
    padding: 24px;
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
    height: 32px;
    width: 60%;
}

@keyframes shimmer {
    0% {
        background-position: 100% 0;
    }
    100% {
        background-position: 0 0;
    }
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

.btn-ghost {
    background: transparent;
    border: 1px solid var(--ring, #e2e8f0);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

[data-theme="dark"] .vnu-module,
[data-theme="dark"] .vnu-content-item,
[data-theme="dark"] .vnu-file-item {
    background: var(--card, #1e293b);
    border-color: var(--ring, #334155);
}

[data-theme="dark"] .vnu-course-title,
[data-theme="dark"] .vnu-section-title,
[data-theme="dark"] .vnu-module-title {
    color: var(--ink, #f8fafc);
}

[data-theme="dark"] .vnu-course-summary,
[data-theme="dark"] .vnu-section-summary,
[data-theme="dark"] .vnu-module-description {
    color: var(--ink, #f8fafc);
}

[data-theme="dark"] .vnu-file-link {
    color: var(--ink, #f8fafc);
}

.vnu-upload-dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    padding: 16px;
}

.vnu-upload-dialog {
    background: var(--card, #ffffff);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.vnu-upload-dialog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--ring, #e2e8f0);
}

.vnu-upload-dialog-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: var(--ink, #0f172a);
}

.vnu-upload-dialog-close {
    background: none;
    border: none;
    font-size: 28px;
    line-height: 1;
    color: var(--muted, #6b7280);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}

.vnu-upload-dialog-close:hover {
    background: var(--bg, #f8fafc);
    color: var(--ink, #0f172a);
}

.vnu-upload-dialog-body {
    padding: 24px;
}

.vnu-upload-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.vnu-form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.vnu-form-group label {
    font-weight: 600;
    font-size: 14px;
    color: var(--ink, #0f172a);
}

.vnu-form-input {
    padding: 10px 12px;
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 8px;
    font-size: 14px;
    background: var(--card, #ffffff);
    color: var(--ink, #0f172a);
    transition: border-color 0.2s;
    width: 100%;
    box-sizing: border-box;
}

.vnu-form-input:focus {
    outline: none;
    border-color: var(--brand, #0ea5e9);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

.vnu-form-input[type="file"] {
    padding: 8px;
    cursor: pointer;
}

.vnu-upload-dialog-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 8px;
}

.vnu-course-footer {
    margin-top: 16px;
}

.vnu-course-footer-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    justify-content: center;
}

@media (max-width: 575px) {
    .vnu-course-footer-actions {
        flex-direction: column;
    }
    
    .vnu-course-footer-actions .btn {
        width: 100%;
    }
}

[data-theme="dark"] .vnu-upload-dialog {
    background: var(--card, #1e293b);
    border-color: var(--ring, #334155);
}

[data-theme="dark"] .vnu-upload-dialog-header {
    border-color: var(--ring, #334155);
}

[data-theme="dark"] .vnu-form-input {
    background: var(--bg, #0f172a);
    border-color: var(--ring, #334155);
    color: var(--ink, #f8fafc);
}

.vnu-section-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 16px;
    border-top: 1px solid var(--ring, #e2e8f0);
    margin-top: 16px;
}

.vnu-section-info {
    padding: 8px 12px;
    background: var(--bg, #f8fafc);
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 8px;
    font-size: 14px;
    color: var(--muted, #6b7280);
}

[data-theme="dark"] .vnu-section-info {
    background: var(--bg, #0f172a);
    border-color: var(--ring, #334155);
    color: var(--muted, #94a3b8);
}

[data-theme="dark"] .vnu-section-actions {
    border-color: var(--ring, #334155);
}

.vnu-module[data-modname="assign"] {
    border-left: 4px solid var(--brand, #0ea5e9);
}

.vnu-module[data-modname="assign"] .vnu-module-title {
    color: var(--brand, #0ea5e9);
}

.vnu-module-actions {
    margin-top: 12px;
    margin-bottom: 16px;
}

.vnu-assignment-info {
    padding: 12px;
    background: var(--bg, #f8fafc);
    border: 1px solid var(--ring, #e2e8f0);
    border-radius: 8px;
    margin: 0;
    color: var(--ink, #0f172a);
}

[data-theme="dark"] .vnu-assignment-info {
    background: var(--bg, #0f172a);
    border-color: var(--ring, #334155);
    color: var(--ink, #f8fafc);
}
</style>

