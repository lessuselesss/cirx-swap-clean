<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-to-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    >
      <div
        v-if="show"
        class="fixed top-20 right-4 z-50 max-w-sm w-full bg-gray-800/95 backdrop-blur-xl border border-gray-700/50 rounded-xl shadow-2xl p-4"
      >
        <div class="flex items-start">
          <!-- Icon -->
          <div class="flex-shrink-0">
            <!-- Wallet Icon (when provided) -->
            <div v-if="walletIcon" :class="[
              'w-6 h-6 rounded-full overflow-hidden border-2',
              type === 'success' ? 'border-green-500' : type === 'error' ? 'border-red-500' : 'border-gray-600/50'
            ]">
              <img :src="walletIcon" :alt="title" class="w-full h-full object-cover" @error="$event.target.style.display='none'" />
            </div>
            <!-- Default status icons -->
            <div v-else-if="type === 'success'" class="w-6 h-6 bg-green-500/20 rounded-full flex items-center justify-center">
              <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </div>
            <div v-else-if="type === 'loading'" class="w-6 h-6 bg-blue-500/20 rounded-full flex items-center justify-center">
              <div class="w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div v-else class="w-6 h-6 bg-red-500/20 rounded-full flex items-center justify-center">
              <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
          
          <!-- Content -->
          <div class="ml-3 flex-1">
            <p class="text-sm font-medium text-white">{{ title }}</p>
            <p v-if="message" class="mt-1 text-sm text-gray-300">{{ message }}</p>
          </div>
          
          <!-- Close button -->
          <button
            @click="close"
            class="ml-4 flex-shrink-0 bg-gray-700/50 hover:bg-gray-600/50 rounded-lg p-1 transition-colors"
          >
            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
  show: Boolean,
  type: {
    type: String,
    default: 'success',
    validator: (value) => ['success', 'error', 'loading'].includes(value)
  },
  title: String,
  message: String,
  walletIcon: String,
  duration: {
    type: Number,
    default: 4000
  }
})

const emit = defineEmits(['close'])

let timeout = null

const close = () => {
  emit('close')
}

watch(() => props.show, (newValue) => {
  if (newValue && props.type !== 'loading' && props.duration > 0) {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
      close()
    }, props.duration)
  }
})

onMounted(() => {
  if (props.show && props.type !== 'loading' && props.duration > 0) {
    timeout = setTimeout(() => {
      close()
    }, props.duration)
  }
})
</script>