<script setup>
import { ref, onMounted } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import MiniMap from '@/components/MiniMap.vue'

const toast = useToast()
const { confirmDelete } = useConfirm()

const locations = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingLocation = ref(null)

const form = ref({ code: '', name: '', latitude: '', longitude: '', allowed_radius_m: 100, timezone: 'Asia/Jakarta' })

onMounted(fetchLocations)

async function fetchLocations() {
  loading.value = true
  try {
    const response = await adminApi.getLocations()
    locations.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch locations:', error)
  }
  loading.value = false
}

function openCreate() {
  editingLocation.value = null
  form.value = { code: '', name: '', latitude: '', longitude: '', allowed_radius_m: 100, timezone: 'Asia/Jakarta' }
  showForm.value = true
}

function openEdit(location) {
  editingLocation.value = location
  form.value = { ...location }
  showForm.value = true
}

async function handleSubmit() {
  try {
    if (editingLocation.value) {
      await adminApi.updateLocation(editingLocation.value.id, form.value)
    } else {
      await adminApi.createLocation(form.value)
    }
    showForm.value = false
    toast.success(editingLocation.value ? 'Location updated successfully' : 'Location created successfully')
    fetchLocations()
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to save location')
  }
}

async function deleteLocation(location) {
  const confirmed = await confirmDelete(location.name)
  if (!confirmed) return
  try {
    await adminApi.deleteLocation(location.id)
    toast.success('Location deleted successfully')
    fetchLocations()
  } catch (error) {
    toast.error(error.response?.data?.message || error.response?.data?.error || 'Failed to delete')
  }
}

async function getCurrentLocation() {
  if (!navigator.geolocation) {
    toast.error('Geolocation not supported')
    return
  }
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      form.value.latitude = pos.coords.latitude.toFixed(8)
      form.value.longitude = pos.coords.longitude.toFixed(8)
      toast.success('Location coordinates retrieved')
    },
    (err) => toast.error('Failed to get location: ' + err.message),
    { enableHighAccuracy: true }
  )
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-end">
      <button @click="openCreate" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        Add Location
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="location in locations" :key="location.id" class="card p-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="font-semibold text-gray-900 dark:text-white">{{ location.name }}</h3>
            <p class="text-sm text-gray-500">{{ location.code }}</p>
          </div>
          <div class="flex gap-1">
            <button @click="openEdit(location)" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded">
              <span class="material-symbols-outlined text-sm">edit</span>
            </button>
            <button @click="deleteLocation(location)" class="p-1 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500">
              <span class="material-symbols-outlined text-sm">delete</span>
            </button>
          </div>
        </div>
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
          <div class="flex justify-between">
            <span>Radius</span>
            <span class="font-medium">{{ location.allowed_radius_m }}m</span>
          </div>
          <div class="flex justify-between">
            <span>Timezone</span>
            <span class="font-medium">{{ location.timezone }}</span>
          </div>
          <div class="pt-3">
            <!-- Interactive Map -->
            <MiniMap 
              :latitude="location.latitude" 
              :longitude="location.longitude" 
              :name="location.name"
              height="100px"
            />
            <!-- Coordinates -->
            <div class="flex items-center justify-center gap-1 text-xs text-gray-400 mt-2">
              <span class="material-symbols-outlined text-sm">location_on</span>
              <span>{{ location.latitude }}, {{ location.longitude }}</span>
            </div>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-dark-border">
          <span :class="location.is_active ? 'text-green-500' : 'text-gray-400'" class="text-sm">
            {{ location.is_active ? '● Active' : '○ Inactive' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          {{ editingLocation ? 'Edit Location' : 'Add Location' }}
        </h3>
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Code *</label>
              <input v-model="form.code" class="input" placeholder="HQ-01" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
              <input v-model="form.name" class="input" placeholder="Headquarters" required />
            </div>
          </div>
          <div>
            <div class="flex justify-between items-center mb-1">
              <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Coordinates *</label>
              <button type="button" @click="getCurrentLocation" class="text-xs text-primary hover:underline">
                Use Current Location
              </button>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <input v-model="form.latitude" class="input" placeholder="Latitude" required />
              <input v-model="form.longitude" class="input" placeholder="Longitude" required />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Radius (m)</label>
              <input v-model.number="form.allowed_radius_m" type="number" class="input" min="10" max="5000" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Timezone</label>
              <input v-model="form.timezone" class="input" placeholder="Asia/Jakarta" />
            </div>
          </div>
          <div class="flex gap-3 pt-4">
            <button type="submit" class="btn btn-primary flex-1">Save</button>
            <button type="button" @click="showForm = false" class="btn btn-secondary flex-1">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
