import { ref } from 'vue'

const axios = window.axios

// Module-level singletons — shared across all BasicMediaLibrary instances
const items   = ref([])
const loading = ref(false)
const folder  = ref(null)

async function fetchItems(folderPath = null) {
    loading.value = true
    folder.value  = folderPath ?? null
    try {
        const params = folderPath ? { folder: folderPath } : {}
        const { data } = await axios.get(route('admin.basic-media.index'), { params })
        items.value = data.items ?? []
    } finally {
        loading.value = false
    }
}

async function uploadFile(file, folderPath = null) {
    const form = new FormData()
    form.append('file', file)
    if (folderPath) form.append('folder', folderPath)
    const { data } = await axios.post(route('admin.basic-media.upload'), form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    })
    items.value.push(data.item)
    return data.item
}

async function deleteItem(path) {
    await axios.delete(route('admin.basic-media.destroy'), { data: { path } })
    items.value = items.value.filter(i => i.path !== path)
}

export function useMediaLibrary() {
    return { items, loading, folder, fetchItems, uploadFile, deleteItem }
}
