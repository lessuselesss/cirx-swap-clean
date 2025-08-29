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
import { ref } from 'vue'
import { useAppKit, useAppKitAccount } from '@reown/appkit/vue'

// Use AppKit composables directly
const { open } = useAppKit()
const { isConnected } = useAppKitAccount()

const error = ref('')

// Test basic AppKit functionality
const openModal = () => {
  try {
    open()
  } catch (err) {
    error.value = err.message
  }
}
</script>