<script setup>
import { ref, onMounted } from 'vue'

const data = ref([])
const isLoading = ref(true)
const errorMessage = ref('')

const fetchUsers = async () => {
  try {
    const response = await fetch('/api/proxy')
    if (!response.ok) throw new Error('Server error')
    data.value = await response.json()
  } catch (error) {
    errorMessage.value = 'Loading proxy error'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <main class="app-container">
    <h1>Proxy Manager (Vue 3 + Laravel 12)</h1>
    
    <div v-if="isLoading">Data loading...</div>
    <div v-else-if="errorMessage" class="error">{{ errorMessage }}</div>
    
    <ul v-else>
      <li v-for="proxy in data" :key="proxy.id">
        {{ proxy.name }} — <span>{{ proxy.email }}</span>
      </li>
    </ul>
  </main>
</template>

<style scoped>
.app-container { max-width: 800px; margin: 2rem auto; font-family: sans-serif; }
.error { color: #ff3333; font-weight: bold; }
li span { color: #666; }
</style>
