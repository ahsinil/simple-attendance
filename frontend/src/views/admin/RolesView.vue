<script setup>
import { ref, onMounted, computed } from 'vue'
import { adminApi } from '@/services/api'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useAuthStore } from '@/stores/auth'

const toast = useToast()
const { confirmDelete, confirmAction } = useConfirm()
const authStore = useAuthStore()

// Permission checks
const canCreate = computed(() => authStore.hasPermission('admin.roles.create'))
const canUpdate = computed(() => authStore.hasPermission('admin.roles.update'))
const canDelete = computed(() => authStore.hasPermission('admin.roles.delete'))

const roles = ref([])
const permissionGroups = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingRole = ref(null)

const form = ref({
  name: '',
  permissions: [],
})

const systemRoleNames = ['super_admin', 'admin', 'supervisor', 'employee']

onMounted(async () => {
  await Promise.all([fetchRoles(), fetchPermissions()])
  loading.value = false
})

async function fetchRoles() {
  try {
    const response = await adminApi.getRoles()
    roles.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch roles:', error)
    toast.error('Failed to load roles')
  }
}

async function fetchPermissions() {
  try {
    const response = await adminApi.getPermissions()
    permissionGroups.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch permissions:', error)
    toast.error('Failed to load permissions')
  }
}

function openCreate() {
  editingRole.value = null
  form.value = { name: '', permissions: [] }
  showForm.value = true
}

function openEdit(role) {
  editingRole.value = role
  form.value = {
    name: role.name,
    permissions: [...role.permissions],
  }
  showForm.value = true
}

async function handleSubmit() {
  try {
    if (editingRole.value) {
      await adminApi.updateRole(editingRole.value.id, form.value)
      toast.success('Role updated successfully')
    } else {
      await adminApi.createRole(form.value)
      toast.success('Role created successfully')
    }
    showForm.value = false
    fetchRoles()
  } catch (error) {
    const message = error.response?.data?.message || error.response?.data?.error || 'Failed to save role'
    toast.error(message)
  }
}

async function deleteRole(role) {
  if (role.is_system) {
    toast.warning('System roles cannot be deleted')
    return
  }
  if (role.users_count > 0) {
    toast.warning('Cannot delete role with assigned users')
    return
  }
  
  const confirmed = await confirmDelete(role.name)
  if (!confirmed) return
  
  try {
    await adminApi.deleteRole(role.id)
    toast.success('Role deleted successfully')
    fetchRoles()
  } catch (error) {
    const message = error.response?.data?.message || error.response?.data?.error || 'Failed to delete role'
    toast.error(message)
  }
}

function togglePermission(permissionName) {
  const index = form.value.permissions.indexOf(permissionName)
  if (index === -1) {
    form.value.permissions.push(permissionName)
  } else {
    form.value.permissions.splice(index, 1)
  }
}

function toggleCategoryPermissions(group) {
  const permissionNames = group.permissions.map(p => p.name)
  const allSelected = permissionNames.every(p => form.value.permissions.includes(p))
  
  if (allSelected) {
    // Remove all permissions from this category
    form.value.permissions = form.value.permissions.filter(p => !permissionNames.includes(p))
  } else {
    // Add all permissions from this category
    permissionNames.forEach(p => {
      if (!form.value.permissions.includes(p)) {
        form.value.permissions.push(p)
      }
    })
  }
}

function isCategoryFullySelected(group) {
  const permissionNames = group.permissions.map(p => p.name)
  return permissionNames.every(p => form.value.permissions.includes(p))
}

function isCategoryPartiallySelected(group) {
  const permissionNames = group.permissions.map(p => p.name)
  const selectedCount = permissionNames.filter(p => form.value.permissions.includes(p)).length
  return selectedCount > 0 && selectedCount < permissionNames.length
}

function formatRoleName(name) {
  return name.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Roles & Permissions</h2>
        <p class="text-sm text-gray-500 mt-1">Manage user roles and their permissions</p>
      </div>
      <button v-if="canCreate" @click="openCreate" class="btn btn-primary">
        <span class="material-symbols-outlined text-sm">add</span>
        Add Role
      </button>
    </div>

    <!-- Roles Grid -->
    <div v-if="loading" class="card p-8 text-center text-gray-500">Loading...</div>

    <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
      <div 
        v-for="role in roles" 
        :key="role.id"
        class="card p-5 hover:shadow-md transition-shadow"
      >
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                 :class="role.is_system ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-gray-100 dark:bg-dark-border'">
              <span class="material-symbols-outlined text-lg"
                    :class="role.is_system ? 'text-blue-500' : 'text-gray-500'">
                {{ role.is_system ? 'verified_user' : 'person' }}
              </span>
            </div>
            <div>
              <h3 class="font-medium text-gray-900 dark:text-white">{{ formatRoleName(role.name) }}</h3>
              <span v-if="role.is_system" class="text-xs text-blue-500 font-medium">System Role</span>
            </div>
          </div>
          <div v-if="canUpdate || canDelete" class="flex gap-1">
            <button v-if="canUpdate" @click="openEdit(role)" class="p-1.5 hover:bg-gray-100 dark:hover:bg-dark-border rounded" title="Edit">
              <span class="material-symbols-outlined text-sm">edit</span>
            </button>
            <button 
              v-if="canDelete && !role.is_system"
              @click="deleteRole(role)" 
              class="p-1.5 hover:bg-red-100 dark:hover:bg-red-900/20 rounded text-red-500" 
              title="Delete"
              :disabled="role.users_count > 0"
            >
              <span class="material-symbols-outlined text-sm">delete</span>
            </button>
          </div>
        </div>

        <div class="flex items-center gap-4 text-sm text-gray-500">
          <div class="flex items-center gap-1">
            <span class="material-symbols-outlined text-base">security</span>
            {{ role.permissions_count }} permissions
          </div>
          <div class="flex items-center gap-1">
            <span class="material-symbols-outlined text-base">group</span>
            {{ role.users_count }} users
          </div>
        </div>

        <!-- Permission Preview -->
        <div class="mt-3 flex flex-wrap gap-1">
          <span 
            v-for="(perm, idx) in role.permissions.slice(0, 4)" 
            :key="perm"
            class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-dark-border rounded-full text-gray-600 dark:text-gray-400"
          >
            {{ perm }}
          </span>
          <span 
            v-if="role.permissions.length > 4"
            class="px-2 py-0.5 text-xs bg-primary/10 text-primary rounded-full"
          >
            +{{ role.permissions.length - 4 }} more
          </span>
        </div>
      </div>
    </div>

    <!-- Role Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="card p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ editingRole ? 'Edit Role' : 'Create New Role' }}
          </h3>
          <button @click="showForm = false" class="p-1 hover:bg-gray-100 dark:hover:bg-dark-border rounded">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- Role Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Role Name *
            </label>
            <input 
              v-model="form.name" 
              class="input" 
              :disabled="editingRole?.is_system"
              placeholder="e.g., manager"
              pattern="[a-z_]+"
              title="Lowercase letters and underscores only"
              required 
            />
            <p class="text-xs text-gray-500 mt-1">Use lowercase letters and underscores only</p>
          </div>

          <!-- Permissions -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
              Permissions
            </label>
            
            <div class="space-y-4">
              <div 
                v-for="group in permissionGroups" 
                :key="group.category"
                class="border border-gray-200 dark:border-dark-border rounded-lg overflow-hidden"
              >
                <!-- Category Header -->
                <button 
                  type="button"
                  @click="toggleCategoryPermissions(group)"
                  class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-dark-border hover:bg-gray-100 dark:hover:bg-dark-border/70 transition-colors"
                >
                  <div class="flex items-center gap-2">
                    <div 
                      class="w-5 h-5 rounded border-2 flex items-center justify-center"
                      :class="isCategoryFullySelected(group) 
                        ? 'bg-primary border-primary' 
                        : isCategoryPartiallySelected(group) 
                          ? 'bg-primary/50 border-primary' 
                          : 'border-gray-300 dark:border-gray-600'"
                    >
                      <span 
                        v-if="isCategoryFullySelected(group) || isCategoryPartiallySelected(group)" 
                        class="material-symbols-outlined text-white text-sm"
                      >
                        {{ isCategoryFullySelected(group) ? 'check' : 'remove' }}
                      </span>
                    </div>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ group.label }}</span>
                  </div>
                  <span class="text-xs text-gray-500">
                    {{ group.permissions.filter(p => form.permissions.includes(p.name)).length }}/{{ group.permissions.length }}
                  </span>
                </button>

                <!-- Permission Checkboxes -->
                <div class="px-4 py-3 grid grid-cols-2 gap-2">
                  <label 
                    v-for="permission in group.permissions" 
                    :key="permission.name"
                    class="flex items-center gap-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-dark-border/50 px-2 py-1.5 rounded"
                  >
                    <input 
                      type="checkbox"
                      :checked="form.permissions.includes(permission.name)"
                      @change="togglePermission(permission.name)"
                      class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ permission.label }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4 border-t border-gray-200 dark:border-dark-border">
            <button type="submit" class="btn btn-primary flex-1">
              {{ editingRole ? 'Update Role' : 'Create Role' }}
            </button>
            <button type="button" @click="showForm = false" class="btn btn-secondary flex-1">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
