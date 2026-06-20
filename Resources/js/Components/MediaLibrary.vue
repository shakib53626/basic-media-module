<script setup>
import { ref, computed, watch, onMounted, onUnmounted, getCurrentInstance } from 'vue'
import { useMediaLibrary } from '@modules/BasicMedia/Resources/js/Composables/useMediaLibrary'
import Sheet from '@/Components/UiComponent/Sheet.vue'

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    multiple:   { type: Boolean, default: false },
    accept:     { type: String,  default: '' },
    title:      { type: String,  default: '' },
})

const emit = defineEmits(['update:modelValue', 'select'])

// ── i18n (safe — no crash if plugin missing) ──────────────────────────────────
const _inst = getCurrentInstance()
function $t(key) {
    const fn = _inst?.appContext?.config?.globalProperties?.$t
    return fn ? fn(key) : key.split('.').pop()
}

// ── Shared file store ─────────────────────────────────────────────────────────
const { items, loading, fetchItems, uploadFile, deleteItem: apiDelete } = useMediaLibrary()

// ── Mobile detection ──────────────────────────────────────────────────────────
const isMobile = ref(false)
let mq = null
const checkMobile = () => { isMobile.value = mq ? mq.matches : window.innerWidth < 640 }
onMounted(() => {
    mq = window.matchMedia('(max-width: 639.98px)')
    checkMobile()
    mq.addEventListener?.('change', checkMobile)
})
onUnmounted(() => { mq?.removeEventListener?.('change', checkMobile) })

// ── Local state ───────────────────────────────────────────────────────────────
const searchQuery     = ref('')
const viewMode        = ref('grid')
const selectedIds     = ref(new Set())
const selectedItem    = ref(null)
const confirmDeleteId = ref(null)
const dragOver        = ref(false)

// ── Reset on open + scroll lock ──────────────────────────────────────────────
watch(() => props.modelValue, (val) => {
    document.body.style.overflow = val ? 'hidden' : ''
    if (val) {
        searchQuery.value     = ''
        selectedIds.value     = new Set()
        selectedItem.value    = null
        confirmDeleteId.value = null
        dragOver.value        = false
        fetchItems(null)
    }
})
onUnmounted(() => { document.body.style.overflow = '' })

// ── Escape key ────────────────────────────────────────────────────────────────
const onKey = (e) => { if (e.key === 'Escape' && props.modelValue) close() }
onMounted(() => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))

// ── Accepted types filter ─────────────────────────────────────────────────────
const acceptedTypes = computed(() => {
    if (!props.accept) return null
    return props.accept.split(',').map(s => s.trim()).filter(Boolean)
})

function isAccepted(item) {
    if (item.type === 'folder') return false
    if (!acceptedTypes.value) return true
    return acceptedTypes.value.includes(item.type)
}

// ── Flat file list ────────────────────────────────────────────────────────────
const allFiles = computed(() => items.value.filter(i => i.type !== 'folder' && isAccepted(i)))

const visibleFiles = computed(() => {
    if (!searchQuery.value.trim()) return allFiles.value
    const q = searchQuery.value.toLowerCase()
    return allFiles.value.filter(i => i.name.toLowerCase().includes(q))
})

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatSize(bytes) {
    if (!bytes) return '—'
    if (bytes < 1024)        return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function fileColor(type) {
    return ({ image: 'text-violet-500', pdf: 'text-red-500', doc: 'text-blue-500', video: 'text-pink-500', zip: 'text-orange-500' })[type] ?? 'text-semidark'
}

function fileBg(type) {
    return ({ image: 'bg-violet-50 dark:bg-violet-500/10', pdf: 'bg-red-50 dark:bg-red-500/10', doc: 'bg-blue-50 dark:bg-blue-500/10', video: 'bg-pink-50 dark:bg-pink-500/10', zip: 'bg-orange-50 dark:bg-orange-500/10' })[type] ?? 'bg-page'
}

function formatDate(d) {
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

// ── Upload ────────────────────────────────────────────────────────────────────
function handleDrop(e) {
    dragOver.value = false
    addFiles(Array.from(e.dataTransfer?.files ?? []))
}
function handleFileInput(e) {
    addFiles(Array.from(e.target.files ?? []))
    e.target.value = ''
}
async function addFiles(files) {
    for (const f of files) {
        try { await uploadFile(f, null) } catch (e) { console.error('Upload failed:', f.name, e) }
    }
}

// ── Selection ─────────────────────────────────────────────────────────────────
function isSelected(item) {
    return props.multiple ? selectedIds.value.has(item.id) : selectedItem.value?.id === item.id
}
function toggleSelect(item) {
    if (props.multiple) {
        const s = new Set(selectedIds.value)
        s.has(item.id) ? s.delete(item.id) : s.add(item.id)
        selectedIds.value = s
    } else {
        selectedItem.value = isSelected(item) ? null : item
    }
}
const hasSelection  = computed(() => props.multiple ? selectedIds.value.size > 0 : selectedItem.value !== null)
const selectedCount = computed(() => props.multiple ? selectedIds.value.size : (selectedItem.value ? 1 : 0))

// ── Delete ────────────────────────────────────────────────────────────────────
function askDelete(item, e) { e.stopPropagation(); confirmDeleteId.value = item.id }
async function doDelete() {
    const id   = confirmDeleteId.value
    const item = items.value.find(i => i.id === id)
    if (!item) { confirmDeleteId.value = null; return }
    try {
        await apiDelete(item.path)
        if (!props.multiple && selectedItem.value?.id === id) selectedItem.value = null
        if (props.multiple) { const s = new Set(selectedIds.value); s.delete(id); selectedIds.value = s }
    } catch (e) { console.error('Delete failed:', e) }
    confirmDeleteId.value = null
}

// ── Confirm select ────────────────────────────────────────────────────────────
function confirmSelect() {
    if (!hasSelection.value) return
    if (props.multiple) {
        emit('select', items.value.filter(i => selectedIds.value.has(i.id)))
    } else {
        emit('select', selectedItem.value)
    }
    close()
}
function close() { emit('update:modelValue', false) }

const bottomFile = computed(() => props.multiple ? null : selectedItem.value)
</script>

<template>
    <!-- ── DESKTOP modal ─────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="bml-modal">
            <div
                v-if="modelValue && !isMobile"
                class="fixed inset-0 z-200 flex items-center justify-center p-4"
            >
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close" />

                <div
                    class="relative z-10 w-full max-w-4xl bg-card border border-stroke1 rounded-2xl shadow-2xl flex flex-col overflow-hidden"
                    style="height: min(85vh, 640px)"
                >
                    <!-- header -->
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-stroke1 shrink-0">
                        <div>
                            <h3 class="text-base font-semibold text-dark">{{ title || $t('navigation.media_library.title') }}</h3>
                            <p class="text-xs text-semidark mt-0.5">{{ allFiles.length }} {{ $t('navigation.media_library.files_count') }}</p>
                        </div>
                        <button class="w-7 h-7 flex items-center justify-center rounded-md text-semidark hover:text-dark hover:bg-stroke1 transition-colors" @click="close">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <!-- toolbar -->
                    <div class="flex items-center gap-2.5 px-4 py-2.5 border-b border-stroke1 shrink-0 bg-page/50">
                        <div class="relative flex-1 max-w-xs">
                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-semidark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            <input v-model="searchQuery" type="text" :placeholder="$t('navigation.media_library.search_placeholder')" class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-stroke1 bg-card focus:outline-none focus:border-primary" />
                        </div>
                        <div class="flex-1" />
                        <!-- view toggle -->
                        <div class="flex items-center gap-0.5 border border-stroke1 rounded-lg p-0.5">
                            <button @click="viewMode = 'grid'" class="p-1.5 rounded-md transition-colors" :class="viewMode === 'grid' ? 'bg-primary text-white' : 'text-semidark hover:bg-page'">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
                            </button>
                            <button @click="viewMode = 'list'" class="p-1.5 rounded-md transition-colors" :class="viewMode === 'list' ? 'bg-primary text-white' : 'text-semidark hover:bg-page'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                            </button>
                        </div>
                        <!-- upload -->
                        <label class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-primary text-white hover:bg-primary/90 transition-colors cursor-pointer shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            {{ $t('navigation.media_library.upload') }}
                            <input type="file" multiple class="hidden" @change="handleFileInput" />
                        </label>
                    </div>

                    <!-- file area -->
                    <div
                        class="flex-1 min-h-0 overflow-y-auto relative"
                        :class="dragOver ? 'bg-primary/5' : ''"
                        @dragover.prevent="dragOver = true"
                        @dragleave.self="dragOver = false"
                        @drop.prevent="handleDrop"
                    >
                        <!-- drag overlay -->
                        <div v-if="dragOver" class="absolute inset-0 z-10 flex items-center justify-center pointer-events-none">
                            <div class="flex flex-col items-center gap-2 text-primary">
                                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                <span class="text-sm font-medium">{{ $t('navigation.media_library.drop_here') }}</span>
                            </div>
                        </div>

                        <!-- loading -->
                        <div v-if="loading" class="flex items-center justify-center h-full min-h-48">
                            <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                        </div>

                        <!-- empty -->
                        <div v-else-if="visibleFiles.length === 0" class="flex flex-col items-center justify-center h-full min-h-48 py-16 text-center">
                            <div class="w-14 h-14 rounded-2xl bg-page flex items-center justify-center mb-3 border border-stroke1">
                                <svg class="w-7 h-7 text-stroke3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            <p class="text-sm font-medium text-dark">{{ $t('navigation.media_library.empty_title') }}</p>
                            <p class="text-xs text-semidark mt-1">{{ $t('navigation.media_library.empty_desc') }}</p>
                        </div>

                        <!-- grid view -->
                        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2.5 p-4">
                            <div
                                v-for="item in visibleFiles"
                                :key="item.id"
                                class="group relative rounded-xl border-2 cursor-pointer transition-all duration-150 select-none overflow-hidden bg-card"
                                :class="isSelected(item) ? 'border-primary ring-1 ring-primary/30' : 'border-stroke1 hover:border-stroke2 hover:shadow-sm'"
                                @click="toggleSelect(item)"
                            >
                                <div class="aspect-square flex items-center justify-center overflow-hidden" :class="item.type === 'image' ? 'bg-stroke1/30' : fileBg(item.type)">
                                    <img v-if="item.type === 'image' && item.url" :src="item.url" class="w-full h-full object-cover" alt="" />
                                    <svg v-else-if="item.type === 'image'" class="w-8 h-8" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <svg v-else-if="item.type === 'pdf'" class="w-8 h-8" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                    <svg v-else-if="item.type === 'video'" class="w-8 h-8" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                                    <svg v-else class="w-8 h-8" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>

                                <div
                                    class="absolute top-1.5 left-1.5 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                    :class="isSelected(item) ? 'bg-primary border-primary shadow-sm' : 'border-white/80 bg-black/20 opacity-0 group-hover:opacity-100'"
                                >
                                    <svg v-if="isSelected(item)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>

                                <button class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-500" @click.stop="askDelete(item, $event)">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                </button>

                                <div class="px-2 py-1.5">
                                    <p class="text-[10px] font-medium text-dark truncate leading-tight" :title="item.name">{{ item.name }}</p>
                                    <p class="text-[9px] text-semidark mt-0.5">{{ formatSize(item.size) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- list view -->
                        <div v-else class="divide-y divide-stroke1">
                            <div
                                v-for="item in visibleFiles"
                                :key="item.id"
                                class="group flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors hover:bg-page select-none"
                                :class="isSelected(item) ? 'bg-primary/5' : ''"
                                @click="toggleSelect(item)"
                            >
                                <div class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-colors" :class="isSelected(item) ? 'bg-primary border-primary' : 'border-stroke2'">
                                    <svg v-if="isSelected(item)" class="w-2.5 h-2.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                </div>
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 overflow-hidden" :class="item.type === 'image' ? 'bg-stroke1/30' : fileBg(item.type)">
                                    <img v-if="item.type === 'image' && item.url" :src="item.url" class="w-full h-full object-cover" alt="" />
                                    <svg v-else class="w-4.5 h-4.5" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-dark truncate">{{ item.name }}</p>
                                    <p class="text-xs text-semidark">{{ formatSize(item.size) }}</p>
                                </div>
                                <span class="text-xs text-semidark shrink-0 hidden lg:block">{{ formatDate(item.date) }}</span>
                                <button class="w-7 h-7 flex items-center justify-center rounded-md text-semidark hover:text-red-500 hover:bg-red-50 transition-colors opacity-0 group-hover:opacity-100 shrink-0" @click.stop="askDelete(item, $event)">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- bottom action bar -->
                    <div class="flex items-center gap-3 px-4 py-3 border-t border-stroke1 bg-page shrink-0">
                        <template v-if="bottomFile">
                            <div class="w-9 h-9 rounded-lg overflow-hidden shrink-0 border border-stroke1">
                                <img v-if="bottomFile.type === 'image' && bottomFile.url" :src="bottomFile.url" class="w-full h-full object-cover" alt="" />
                                <div v-else class="w-full h-full flex items-center justify-center" :class="fileBg(bottomFile.type)">
                                    <svg class="w-4 h-4" :class="fileColor(bottomFile.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-dark truncate">{{ bottomFile.name }}</p>
                                <p class="text-xs text-semidark">{{ formatSize(bottomFile.size) }}</p>
                            </div>
                        </template>
                        <div v-else-if="multiple && selectedCount > 0" class="flex-1 min-w-0">
                            <p class="text-sm text-dark"><span class="font-medium">{{ selectedCount }}</span> {{ $t('navigation.media_library.files_selected') }}</p>
                        </div>
                        <div v-else class="flex-1 min-w-0">
                            <p class="text-sm text-semidark">{{ $t('navigation.media_library.nothing_selected') }}</p>
                        </div>
                        <button @click="close" class="px-4 py-2 text-sm rounded-lg border border-stroke1 text-semidark hover:bg-stroke1 transition-colors shrink-0">{{ $t('navigation.media_library.cancel') }}</button>
                        <button @click="confirmSelect" :disabled="!hasSelection" class="px-4 py-2 text-sm rounded-lg bg-primary text-white transition-colors shrink-0" :class="hasSelection ? 'hover:bg-primary/90' : 'opacity-40 cursor-not-allowed'">
                            {{ hasSelection && multiple && selectedCount > 1 ? `${$t('navigation.media_library.select')} (${selectedCount})` : $t('navigation.media_library.select') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── MOBILE: bottom Sheet ──────────────────────────────────────────── -->
    <Sheet v-if="isMobile" :model-value="modelValue && isMobile" @update:model-value="$emit('update:modelValue', $event)" side="bottom" size="full" :title="title || $t('navigation.media_library.title')">
        <div class="flex flex-col h-full -mx-5 -my-4">
            <!-- mobile toolbar -->
            <div class="flex items-center gap-2 px-4 py-2.5 border-b border-stroke1 shrink-0 bg-page/50">
                <div class="relative flex-1">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-semidark" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input v-model="searchQuery" type="text" :placeholder="$t('navigation.media_library.search_placeholder')" class="w-full pl-8 pr-3 py-1.5 text-sm rounded-lg border border-stroke1 bg-card focus:outline-none focus:border-primary" />
                </div>
                <div class="flex items-center gap-0.5 border border-stroke1 rounded-lg p-0.5 shrink-0">
                    <button @click="viewMode = 'grid'" class="p-1.5 rounded-md transition-colors" :class="viewMode === 'grid' ? 'bg-primary text-white' : 'text-semidark hover:bg-page'">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>
                    </button>
                    <button @click="viewMode = 'list'" class="p-1.5 rounded-md transition-colors" :class="viewMode === 'list' ? 'bg-primary text-white' : 'text-semidark hover:bg-page'">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    </button>
                </div>
                <label class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-primary text-white cursor-pointer shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    {{ $t('navigation.media_library.upload') }}
                    <input type="file" multiple class="hidden" @change="handleFileInput" />
                </label>
            </div>

            <!-- mobile file area -->
            <div class="flex-1 overflow-y-auto min-h-0" @dragover.prevent="dragOver = true" @dragleave.self="dragOver = false" @drop.prevent="handleDrop">
                <div v-if="loading" class="flex items-center justify-center h-full min-h-48">
                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                </div>
                <div v-else-if="visibleFiles.length === 0" class="flex flex-col items-center justify-center h-full min-h-48 py-12 text-center">
                    <p class="text-sm font-medium text-dark">{{ $t('navigation.media_library.empty_title') }}</p>
                    <p class="text-xs text-semidark mt-1">{{ $t('navigation.media_library.empty_desc') }}</p>
                </div>
                <!-- mobile grid -->
                <div v-else-if="viewMode === 'grid'" class="grid grid-cols-3 gap-2 p-3">
                    <div v-for="item in visibleFiles" :key="item.id" class="group relative rounded-xl border-2 cursor-pointer transition-all overflow-hidden bg-card" :class="isSelected(item) ? 'border-primary ring-1 ring-primary/30' : 'border-stroke1'" @click="toggleSelect(item)">
                        <div class="aspect-square flex items-center justify-center overflow-hidden" :class="item.type === 'image' ? 'bg-stroke1/30' : fileBg(item.type)">
                            <img v-if="item.type === 'image' && item.url" :src="item.url" class="w-full h-full object-cover" alt="" />
                            <svg v-else class="w-7 h-7" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="absolute top-1.5 left-1.5 w-5 h-5 rounded-full border-2 flex items-center justify-center" :class="isSelected(item) ? 'bg-primary border-primary' : 'border-white/80 bg-black/20'">
                            <svg v-if="isSelected(item)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <button class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-black/50 text-white flex items-center justify-center hover:bg-red-500 transition-colors" @click.stop="askDelete(item, $event)">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                        <div class="px-1.5 py-1"><p class="text-[9px] font-medium text-dark truncate">{{ item.name }}</p></div>
                    </div>
                </div>
                <!-- mobile list -->
                <div v-else class="divide-y divide-stroke1">
                    <div v-for="item in visibleFiles" :key="item.id" class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors" :class="isSelected(item) ? 'bg-primary/5' : 'hover:bg-page'" @click="toggleSelect(item)">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 overflow-hidden" :class="item.type === 'image' ? 'bg-stroke1/30' : fileBg(item.type)">
                            <img v-if="item.type === 'image' && item.url" :src="item.url" class="w-full h-full object-cover" alt="" />
                            <svg v-else class="w-4 h-4" :class="fileColor(item.type)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <span class="flex-1 text-sm font-medium text-dark truncate">{{ item.name }}</span>
                        <span class="text-xs text-semidark shrink-0">{{ formatSize(item.size) }}</span>
                        <button class="w-7 h-7 flex items-center justify-center rounded-md text-semidark hover:text-red-500 shrink-0" @click.stop="askDelete(item, $event)">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- mobile bottom bar -->
            <div class="flex items-center gap-2 px-4 py-3 border-t border-stroke1 bg-page shrink-0">
                <div class="flex-1 min-w-0">
                    <p v-if="bottomFile" class="text-sm font-medium text-dark truncate">{{ bottomFile.name }}</p>
                    <p v-else-if="multiple && selectedCount > 0" class="text-sm text-dark"><span class="font-medium">{{ selectedCount }}</span> selected</p>
                    <p v-else class="text-sm text-semidark">{{ $t('navigation.media_library.nothing_selected') }}</p>
                </div>
                <button @click="close" class="px-3 py-2 text-sm rounded-lg border border-stroke1 text-semidark hover:bg-stroke1 transition-colors shrink-0">{{ $t('navigation.media_library.cancel') }}</button>
                <button @click="confirmSelect" :disabled="!hasSelection" class="px-3 py-2 text-sm rounded-lg bg-primary text-white transition-colors shrink-0" :class="hasSelection ? 'hover:bg-primary/90' : 'opacity-40 cursor-not-allowed'">{{ $t('navigation.media_library.select') }}</button>
            </div>
        </div>
    </Sheet>

    <!-- ── Delete confirm ────────────────────────────────────────────────── -->
    <Teleport to="body">
        <Transition name="bml-modal">
            <div v-if="confirmDeleteId !== null" class="fixed inset-0 z-300 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="confirmDeleteId = null" />
                <div class="relative z-10 bg-card border border-stroke1 rounded-2xl shadow-xl w-80 p-5">
                    <div class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-500/10 flex items-center justify-center mb-3">
                        <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-dark">{{ $t('navigation.media_library.delete_title') }}</p>
                    <p class="text-xs text-semidark mt-1">{{ $t('navigation.media_library.delete_desc') }}</p>
                    <div class="flex justify-end gap-2 mt-4">
                        <button @click="confirmDeleteId = null" class="px-3 py-1.5 text-sm rounded-lg border border-stroke1 text-semidark hover:bg-page transition-colors">{{ $t('navigation.media_library.cancel') }}</button>
                        <button @click="doDelete" class="px-3 py-1.5 text-sm rounded-lg bg-red-500 text-white hover:bg-red-600 transition-colors">{{ $t('navigation.media_library.delete_confirm') }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.bml-modal-enter-active,
.bml-modal-leave-active { transition: all 0.2s ease; }
.bml-modal-enter-from,
.bml-modal-leave-to     { opacity: 0; }
.bml-modal-enter-from .relative,
.bml-modal-leave-to .relative { transform: scale(0.97) translateY(-6px); }
</style>
