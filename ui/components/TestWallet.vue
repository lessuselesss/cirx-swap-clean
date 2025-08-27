<template>
  <div>
    <button @click="openModal" class="px-4 py-2 bg-blue-500 text-white rounded">
      Test AppKit: {{ isConnected ? 'Connected' : 'Disconnected' }}
    </button>
    <div v-if="error" class="text-red-500 mt-2">
      Error: {{ error }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const isConnected = ref(false)
const error = ref('')

// Test basic AppKit functionality
const openModal = () => {
  try {
    if (typeof window !== 'undefined' && window.$appKit) {
      window.$appKit.open()
    } else {
      error.value = 'AppKit not available on window'
    }
  } catch (err) {
    error.value = err.message
  }
}

onMounted(() => {
  try {
    // Try to import AppKit hooks
    import('@reown/appkit/vue').then(({ useAppKitAccount }) => {
      const { isConnected: connected } = useAppKitAccount()
      isConnected.value = connected.value
    }).catch(err => {
      error.value = 'Failed to import AppKit: ' + err.message
    })
  } catch (err) {
    error.value = 'Error in onMounted: ' + err.message
  }
})
</script>