<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const isFormVisible = ref(false)
const proxies = ref([])
const isLoading = ref(false)
const errorMessage = ref('')
const validationErrors = ref({})
const form = ref({ id: null, ip: '', port: 8080, type: 'http', username: '', password: '' })
const isEditing = ref(false)

const openCreateForm = () => {
    resetForm()
    isFormVisible.value = true
}

const fetchProxies = async () => {
    try {
        const res = await fetch('/api/proxies')
        if (!res.ok) throw new Error('Server error while retrieving list')
        proxies.value = await res.json()
        errorMessage.value = ''
    } catch (err) { 
        console.error('Loading error:', err) 
        errorMessage.value = 'Failed to load proxy list.'
    }
}

const handleSubmit = async () => {
    isLoading.value = true
    validationErrors.value = {}
    
    const url = isEditing.value ? `/api/proxies/${form.value.id}` : '/api/proxies'
    const method = isEditing.value ? 'PUT' : 'POST'

    try {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(form.value)
        })
        
        const result = await res.json()

        if (res.status === 422) {
            validationErrors.value = result.errors
            isLoading.value = false
            return
        }

        if (!res.ok) throw new Error('Error saving')
        
        resetForm()
        await fetchProxies()
    } catch (err) { 
        console.error(err) 
        alert('An error occurred while saving the proxy.')
    }
    isLoading.value = false
}

const handleFileUpload = async (event) => {
    const file = event.target.files[0]
    if (!file) return

    const formData = new FormData()
    formData.append('file', file)

    isLoading.value = true
    errorMessage.value = ''
    validationErrors.value = {}

    try {
        const res = await fetch('/api/proxies/import', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        })

        const result = await res.json()

        if (res.status === 422) {
            validationErrors.value = result.errors
            isLoading.value = false
            return
        }

        if (!res.ok) throw new Error('Import error')

        alert(`Proxy imported successfully: ${result.imported}`)
        await fetchProxies()
    } catch (err) {
        console.error(err)
        errorMessage.value = 'Failed to load proxy from file. Check the format.'
    } finally {
        isLoading.value = false
        event.target.value = ''
    }
}

const deleteProxy = async (id) => {
    if (!confirm('Are you sure you want to delete this proxy?')) return
    try {
        const res = await fetch(`/api/proxies/${id}`, { method: 'DELETE' })
        if (res.ok) {
            await fetchProxies()
        }
    } catch (err) {
        console.error(err)
    }
}

const checkProxyStatus = async (id) => {
    proxies.value = proxies.value.map(p => p.id === id ? { ...p, status: 'unchecked' } : p)
    try {
        const res = await fetch(`/api/proxies/${id}/check`, { method: 'POST' })
        if (res.ok) {
            const updated = await res.json()
            proxies.value = proxies.value.map(p => p.id === id ? updated : p)
        }
    } catch (err) { 
        console.error(err) 
    }
}

const editProxy = (proxy) => {
    form.value = { ...proxy }
    isEditing.value = true
    validationErrors.value = {}
    isFormVisible.value = true
}

const resetForm = () => {
    form.value = { id: null, ip: '', port: 8080, type: 'http', username: '', password: '' }
    isEditing.value = false
    validationErrors.value = {}
    isFormVisible.value = false
}

const formatTime = (dateString) => {
    if (!dateString) return 'Never'
    return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

let interval = null
onMounted(() => {
    fetchProxies()
    interval = setInterval(fetchProxies, 15000)
})
onBeforeUnmount(() => clearInterval(interval))
</script>

<template>
    <main class="proxy-manager">
        <h1>Managing proxy servers</h1>
        <!-- Common errors -->
        <div v-if="errorMessage" class="error-banner">{{ errorMessage }}</div>

        <div class="top-bar">
            <div class="import-section">
                <div class="import-meta">
                    <h4>📁 Bulk import from file</h4>
                    <p class="import-hint">Formats: <code>protocol:ip:port</code> or <code>protocol:user:pass:ip:port</code></p>
                </div>
                <div class="file-input-wrapper">
                    <input type="file" accept=".txt" @change="handleFileUpload" :disabled="isLoading" id="proxy-file" />
                    <label for="proxy-file" class="btn-file-label">
                        {{ isLoading ? 'Processing...' : 'Выбрать .txt файл с прокси' }}
                    </label>
                </div>
            </div>

            <div class="action-section">
                <button v-if="!isFormVisible" @click="openCreateForm" class="btn-primary btn-add">
                    ➕ Add proxy
                </button>
            </div>
        </div>

        <!-- ADD / EDIT -->
        <form v-if="isFormVisible" @submit.prevent="handleSubmit" class="proxy-form">
            <h3>{{ isEditing ? '✏️ Edit proxy' : '➕ Add a new proxy' }}</h3>
            
            <!-- Validate errors list -->
            <div v-if="Object.keys(validationErrors).length > 0" class="validation-summary">
                <ul>
                    <li v-for="(errors, field) in validationErrors" :key="field">
                        {{ errors[0] }}
                    </li>
                </ul>
            </div>

            <div class="form-row">
                <div class="form-group flex-2">
                    <label>IP address</label>
                    <input v-model="form.ip" type="text" placeholder="Например: 192.168.1.10" required />
                </div>
                <div class="form-group flex-1">
                    <label>Port</label>
                    <input v-model.number="form.port" type="number" placeholder="8080" required min="1" max="65535" />
                </div>
                <div class="form-group flex-1">
                    <label>Protocol</label>
                    <select v-model="form.type">
                        <option value="http">HTTP</option>
                        <option value="https">HTTPS</option>
                        <option value="socks4">SOCKS4</option>
                        <option value="socks5">SOCKS5</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>User</label>
                    <input v-model="form.username" type="text" placeholder="username" />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input v-model="form.password" type="password" placeholder="password" />
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit" :disabled="isLoading">
                    {{ isEditing ? 'Update' : 'Save and check' }}
                </button>
                <button type="button" @click="resetForm" class="btn-cancel">
                    Cancel
                </button>
            </div>
        </form>
        
        <!-- TABLE PROXIES -->
        <div class="table-responsive">
            <table class="proxy-table">
                <thead>
                    <tr>
                        <th>Protocol</th>
                        <th>Address (IP:Prot)</th>
                        <th>Auth</th>
                        <th>Status</th>
                        <th>Last check</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="proxy in proxies" :key="proxy.id">
                        <td><span class="badge-type">{{ proxy.type.toUpperCase() }}</span></td>
                        <td><strong class="ip-display">{{ proxy.ip }}:{{ proxy.port }}</strong></td>
                        <td>
                            <span v-if="proxy.username" class="auth-yes" :title="`Логин: ${proxy.username}`">🔒 Yes</span>
                            <span v-else class="auth-no">🔓 Нет</span>
                        </td>
                        <td>
                            <span :class="['status-badge', proxy.status]">
                                {{ proxy.status === 'active' ? '🟢 Good' : proxy.status === 'dead' ? '🔴 Dead' : '🟡 Check...' }}
                            </span>
                        </td>
                        <td class="time-display">{{ formatTime(proxy.last_checked_at) }}</td>
                        <td>
                            <div class="actions-cell">
                                <button @click="checkProxyStatus(proxy.id)" class="btn-table btn-check" title="Double check now">🔄</button>
                                <button @click="editProxy(proxy)" class="btn-table btn-edit" title="Edit">✏️</button>
                                <button @click="deleteProxy(proxy.id)" class="btn-table btn-delete" title="Delete">🗑️</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="proxies.length === 0">
                        <td colspan="6" class="empty-row">The proxy server list is empty. Add the first server above.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</template>

<style scoped>

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 1.2rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    gap: 2rem;
}

/* Блок импорта внутри панели */
.import-section {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 1;
    margin-top: 0;
    padding-top: 0;
    border: none;
}

.import-meta {
    display: flex;
    flex-direction: column;
}

.import-section h4 {
    margin: 0 0 0.2rem 0;
    color: #1e293b;
    font-size: 0.95rem;
}

.import-hint {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0;
}

.import-hint code {
    background: #e2e8f0;
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
    font-family: monospace;
}

/* Кнопка загрузки файла */
.file-input-wrapper input[type="file"] {
    display: none;
}

.btn-file-label {
    display: inline-block;
    padding: 0.55rem 1.2rem;
    background: #0284c7;
    color: white;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
    white-space: nowrap;
}

.btn-file-label:hover {
    background: #0369a1;
}

/* Правая часть панели с кнопкой Add proxy */
.action-section {
    display: flex;
    align-items: center;
}

.btn-primary {
    background: #2563eb;
    color: white;
    padding: 0.6rem 1.3rem;
    font-size: 0.95rem;
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    white-space: nowrap;
}

.btn-primary:hover {
    background: #1d4ed8;
}

/* Стили для формы (теперь она отделена от верхней панели отступом) */
.proxy-form {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Контейнер */
.proxy-manager { 
    max-width: 1000px; 
    margin: 2rem auto; 
    padding: 0 1rem; 
    font-family: system-ui, -apple-system, sans-serif; 
    color: #333; 
}

h1 { 
    margin-bottom: 2rem; 
    font-size: 1.8rem; 
    text-align: center; 
}

.error-banner { 
    background: #fee2e2; 
    color: #dc2626; 
    padding: 1rem; 
    border-radius: 6px; 
    margin-bottom: 1.5rem; 
    font-weight: 500; 
}

.validation-summary { 
    background: #fff5f5; 
    border-left: 4px solid #e53e3e; 
    padding: 0.75rem 1rem; 
    margin-bottom: 1.5rem; 
    border-radius: 4px; 
}

.validation-summary ul { 
    margin: 0; 
    padding-left: 1.2rem; 
    color: #c53030; 
    font-size: 0.9rem; 
}

/* Form container */
.proxy-form { 
    background: #f8fafc; 
    border: 1px solid #e2e8f0; 
    padding: 1.5rem; 
    border-radius: 8px; 
    margin-bottom: 2rem; 
    box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
}

.proxy-form h3 { 
    margin-top: 0; 
    margin-bottom: 1.2rem; 
    font-size: 1.1rem; 
    color: #1e293b; 
}

.form-row { 
    display: flex; 
    gap: 1rem; 
    margin-bottom: 1rem; 
    flex-wrap: wrap; 
}

.form-group { 
    display: flex; 
    flex-direction: column; 
    flex: 1; 
    min-width: 150px; 
}

.flex-1 { flex: 1; }
.flex-2 { flex: 2; }

.form-group label { 
    font-size: 0.85rem; 
    font-weight: 600; 
    margin-bottom: 0.4rem; 
    color: #64748b; 
}

.form-group input, 
.form-group select { 
    padding: 0.6rem; 
    border: 1px solid #cbd5e1; 
    border-radius: 6px; 
    font-size: 0.95rem; 
    outline: none; 
    transition: border 0.2s; 
}

.form-group input:focus, 
.form-group select:focus { 
    border-color: #3b82f6; 
}

.form-actions { 
    display: flex; 
    gap: 0.5rem; 
    margin-top: 1.5rem; 
}

/* Кнопки */
button { 
    font-size: 0.9rem; 
    font-weight: 500; 
    cursor: pointer; 
    border-radius: 6px; 
    transition: background 0.2s; 
    border: none; 
}

.btn-submit { 
    background: #2563eb; 
    color: white; 
    padding: 0.6rem 1.2rem; 
}

.btn-submit:hover { 
    background: #1d4ed8; 
}

.btn-submit:disabled { 
    background: #94a3b8; 
    cursor: not-allowed; 
}

.btn-cancel { 
    background: #64748b; 
    color: white; 
    padding: 0.6rem 1.2rem; 
}

.btn-cancel:hover { 
    background: #475569; 
}

/* Стили таблицы */
.table-responsive { 
    background: white; 
    border: 1px solid #e2e8f0; 
    border-radius: 8px; 
    overflow: hidden; 
    box-shadow: 0 1px 3px rgba(0,0,0,0.05); 
}

.proxy-table { 
    width: 100%; 
    border-collapse: collapse; 
    text-align: left; 
    font-size: 0.95rem; 
}

.proxy-table th { 
    background: #f1f5f9; 
    padding: 1rem; 
    font-weight: 600; 
    color: #475569; 
    border-bottom: 1px solid #e2e8f0; 
}

.proxy-table td { 
    padding: 1rem; 
    border-bottom: 1px solid #f1f5f9; 
    vertical-align: middle; 
}

/* Элементы внутри ячеек */
.badge-type { 
    background: #e0f2fe; 
    color: #0369a1; 
    padding: 0.2rem 0.5rem; 
    border-radius: 4px; 
    font-size: 0.75rem; 
    font-weight: 700; 
}

.ip-display { 
    color: #0f172a; 
    font-family: monospace; 
    font-size: 1rem; 
}

.auth-yes { 
    color: #16a34a; 
    font-weight: 500; 
}

.auth-no { 
    color: #94a3b8; 
}

.time-display { 
    color: #64748b; 
    font-size: 0.85rem; 
}

.empty-row { 
    text-align: center; 
    color: #64748b; 
    padding: 3rem !important; 
    font-style: italic; 
}

/* Статусы прокси */
.status-badge { 
    display: inline-block; 
    padding: 0.25rem 0.6rem; 
    border-radius: 50px; 
    font-size: 0.8rem; 
    font-weight: 600; 
}

.status-badge.active { 
    background: #dcfce7; 
    color: #15803d; 
}

.status-badge.dead { 
    background: #fee2e2; 
    color: #b91c1c; 
}

.status-badge.unchecked { 
    background: #fef9c3; 
    color: #a16207; 
}

/* Действия в таблице */
.actions-cell { 
    display: flex; 
    gap: 0.3rem; 
}

.btn-table { 
    padding: 0.4rem 0.6rem; 
    background: #f1f5f9; 
    font-size: 1rem; 
}

.btn-table:hover { 
    background: #e2e8f0; 
}

.btn-check:hover { background: #dcfce7; }
.btn-delete:hover { background: #fee2e2; }

.import-section h4 {
    margin: 0 0 0.5rem 0;
    color: #1e293b;
    font-size: 0.95rem;
}
.import-hint {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0 0 1rem 0;
}
.import-hint code {
    background: #e2e8f0;
    padding: 0.1rem 0.3rem;
    border-radius: 4px;
    font-family: monospace;
}
.file-input-wrapper input[type="file"] {
    display: none;
}
.btn-file-label {
    display: inline-block;
    padding: 0.6rem 1.2rem;
    background: #0284c7;
    color: white;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-file-label:hover {
    background: #0369a1;
}
</style>
