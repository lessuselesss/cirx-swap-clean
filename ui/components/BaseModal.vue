<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-300"
      leave-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 min-h-screen p-4"
        @click="handleBackdropClick"
      >
        <Transition
          enter-active-class="transition-all duration-300"
          leave-active-class="transition-all duration-300"
          enter-from-class="opacity-0 scale-95 translate-y-4"
          leave-to-class="opacity-0 scale-95 translate-y-4"
        >
          <div
            v-if="show"
            :class="modalClasses"
            @click.stop
          >
            <!-- Header -->
            <div v-if="$slots.header || title" class="flex items-center justify-between mb-6">
              <slot name="header">
                <h3 class="text-xl font-semibold text-white">{{ title }}</h3>
              </slot>
              
              <button
                v-if="closable"
                @click="$emit('close')"
                class="text-gray-400 hover:text-white transition-colors p-1 rounded-lg hover:bg-gray-800"
                aria-label="Close modal"
              >
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
              </button>
            </div>

            <!-- Content -->
            <div class="flex-1 min-h-0">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="$slots.footer" class="mt-6 pt-4 border-t border-gray-700">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
/**
 * Base Modal Component
 * Consolidates duplicate modal logic and styling across the app
 */

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: ''
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl', 'full'].includes(value)
  },
  closable: {
    type: Boolean,
    default: true
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true
  },
  maxHeight: {
    type: String,
    default: '90vh'
  }
})

const emit = defineEmits(['close'])

const modalClasses = computed(() => [
  'bg-gray-900 border border-gray-700 rounded-xl p-6 mx-4 my-8 overflow-y-auto',
  {
    'w-full max-w-xs': props.size === 'xs',
    'w-full max-w-sm': props.size === 'sm',
    'w-full max-w-md': props.size === 'md',
    'w-full max-w-lg': props.size === 'lg', 
    'w-full max-w-4xl': props.size === 'xl',
    'w-full h-full max-w-none rounded-none': props.size === 'full'
  }
])

const handleBackdropClick = () => {
  if (props.closeOnBackdrop && props.closable) {
    emit('close')
  }
}

// ESC key handling
onMounted(() => {
  const handleEsc = (e) => {
    if (e.key === 'Escape' && props.show && props.closable) {
      emit('close')
    }
  }
  document.addEventListener('keydown', handleEsc)
  onUnmounted(() => document.removeEventListener('keydown', handleEsc))
})

// Body scroll lock when modal is open
watch(() => props.show, (isOpen) => {
  if (process.client) {
    if (isOpen) {
      document.body.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
    }
  }
})

// Cleanup on unmount
onUnmounted(() => {
  if (process.client) {
    document.body.style.overflow = ''
  }
})
</script>