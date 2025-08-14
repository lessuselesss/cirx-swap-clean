<template>
  <div
    v-if="visible"
    :class="[
      'relative p-4 rounded-xl border transition-all duration-300',
      alertClasses
    ]"
    role="alert"
    :aria-live="severity === 'error' ? 'assertive' : 'polite'"
  >
    <!-- Icon -->
    <div class="flex items-start gap-3">
      <div class="flex-shrink-0 mt-0.5">
        <component :is="iconComponent" :class="iconClasses" />
      </div>
      
      <!-- Content -->
      <div class="flex-1 min-w-0">
        <h4 v-if="title" :class="titleClasses">
          {{ title }}
        </h4>
        
        <div :class="messageClasses">
          <p v-if="typeof message === 'string'">{{ message }}</p>
          <div v-else>
            <p v-for="(msg, index) in message" :key="index">{{ msg }}</p>
          </div>
        </div>

        <!-- Actions -->
        <div v-if="actions.length > 0" class="mt-3 flex gap-2">
          <button
            v-for="action in actions"
            :key="action.label"
            @click="action.handler"
            :class="[
              'px-3 py-1.5 text-sm font-medium rounded-lg transition-colors',
              action.primary ? primaryActionClass : secondaryActionClass
            ]"
          >
            {{ action.label }}
          </button>
        </div>
      </div>
      
      <!-- Close Button -->
      <button
        v-if="dismissible"
        @click="handleDismiss"
        class="flex-shrink-0 p-1 rounded-lg hover:bg-black/10 transition-colors"
        :class="closeButtonClass"
        aria-label="Dismiss alert"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
          <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <!-- Auto-dismiss progress bar -->
    <div
      v-if="autoTimeoutMs && showProgress"
      class="absolute bottom-0 left-0 h-1 bg-current opacity-30"
      :style="{ width: progressWidth + '%' }"
    ></div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted, h } from 'vue'

// Props
const props = defineProps({
  message: {
    type: [String, Array],
    required: true
  },
  title: {
    type: String,
    default: null
  },
  severity: {
    type: String,
    default: 'error',
    validator: (value) => ['error', 'warning', 'info', 'success'].includes(value)
  },
  dismissible: {
    type: Boolean,
    default: true
  },
  autoTimeoutMs: {
    type: Number,
    default: null
  },
  showProgress: {
    type: Boolean,
    default: true
  },
  actions: {
    type: Array,
    default: () => []
  }
})

// Emits
const emit = defineEmits(['dismiss'])

// Local state
const visible = ref(true)
const progressWidth = ref(100)
let timeoutId = null
let progressInterval = null

// Computed classes based on severity
const alertClasses = computed(() => {
  const classes = {
    error: 'bg-red-500/10 border-red-500/30 text-red-400',
    warning: 'bg-yellow-500/10 border-yellow-500/30 text-yellow-400',
    info: 'bg-blue-500/10 border-blue-500/30 text-blue-400',
    success: 'bg-green-500/10 border-green-500/30 text-green-400'
  }
  return classes[props.severity]
})

const iconClasses = computed(() => {
  const classes = {
    error: 'w-5 h-5 text-red-400',
    warning: 'w-5 h-5 text-yellow-400',
    info: 'w-5 h-5 text-blue-400',
    success: 'w-5 h-5 text-green-400'
  }
  return classes[props.severity]
})

const titleClasses = computed(() => {
  const classes = {
    error: 'text-red-300 font-semibold text-sm mb-1',
    warning: 'text-yellow-300 font-semibold text-sm mb-1',
    info: 'text-blue-300 font-semibold text-sm mb-1',
    success: 'text-green-300 font-semibold text-sm mb-1'
  }
  return classes[props.severity]
})

const messageClasses = computed(() => {
  return 'text-sm leading-relaxed'
})

const primaryActionClass = computed(() => {
  const classes = {
    error: 'bg-red-500 text-white hover:bg-red-600',
    warning: 'bg-yellow-500 text-gray-900 hover:bg-yellow-600',
    info: 'bg-blue-500 text-white hover:bg-blue-600',
    success: 'bg-green-500 text-white hover:bg-green-600'
  }
  return classes[props.severity]
})

const secondaryActionClass = computed(() => {
  const classes = {
    error: 'bg-red-500/20 text-red-300 hover:bg-red-500/30',
    warning: 'bg-yellow-500/20 text-yellow-300 hover:bg-yellow-500/30',
    info: 'bg-blue-500/20 text-blue-300 hover:bg-blue-500/30',
    success: 'bg-green-500/20 text-green-300 hover:bg-green-500/30'
  }
  return classes[props.severity]
})

const closeButtonClass = computed(() => {
  const classes = {
    error: 'text-red-400 hover:text-red-300',
    warning: 'text-yellow-400 hover:text-yellow-300',
    info: 'text-blue-400 hover:text-blue-300',
    success: 'text-green-400 hover:text-green-300'
  }
  return classes[props.severity]
})

// Icon components using render functions
const ErrorIcon = () => h('svg', {
  class: 'w-5 h-5',
  viewBox: '0 0 24 24',
  fill: 'none'
}, [
  h('circle', { cx: '12', cy: '12', r: '10', stroke: 'currentColor', 'stroke-width': '2' }),
  h('line', { x1: '15', y1: '9', x2: '9', y2: '15', stroke: 'currentColor', 'stroke-width': '2' }),
  h('line', { x1: '9', y1: '9', x2: '15', y2: '15', stroke: 'currentColor', 'stroke-width': '2' })
])

const WarningIcon = () => h('svg', {
  class: 'w-5 h-5',
  viewBox: '0 0 24 24',
  fill: 'none'
}, [
  h('path', { d: 'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z', stroke: 'currentColor', 'stroke-width': '2', fill: 'none' }),
  h('line', { x1: '12', y1: '9', x2: '12', y2: '13', stroke: 'currentColor', 'stroke-width': '2' }),
  h('circle', { cx: '12', cy: '17', r: '1', fill: 'currentColor' })
])

const InfoIcon = () => h('svg', {
  class: 'w-5 h-5',
  viewBox: '0 0 24 24',
  fill: 'none'
}, [
  h('circle', { cx: '12', cy: '12', r: '10', stroke: 'currentColor', 'stroke-width': '2' }),
  h('line', { x1: '12', y1: '16', x2: '12', y2: '12', stroke: 'currentColor', 'stroke-width': '2' }),
  h('circle', { cx: '12', cy: '8', r: '1', fill: 'currentColor' })
])

const SuccessIcon = () => h('svg', {
  class: 'w-5 h-5',
  viewBox: '0 0 24 24',
  fill: 'none'
}, [
  h('path', { d: 'M22 11.08V12a10 10 0 1 1-5.93-9.14', stroke: 'currentColor', 'stroke-width': '2', fill: 'none' }),
  h('polyline', { points: '22,4 12,14.01 9,11.01', stroke: 'currentColor', 'stroke-width': '2', fill: 'none' })
])

const iconComponent = computed(() => {
  const icons = {
    error: ErrorIcon,
    warning: WarningIcon,
    info: InfoIcon,
    success: SuccessIcon
  }
  return icons[props.severity]
})

// Methods
const handleDismiss = () => {
  visible.value = false
  clearTimeouts()
  emit('dismiss')
}

const clearTimeouts = () => {
  if (timeoutId) {
    clearTimeout(timeoutId)
    timeoutId = null
  }
  if (progressInterval) {
    clearInterval(progressInterval)
    progressInterval = null
  }
}

// Auto-dismiss logic
onMounted(() => {
  if (props.autoTimeoutMs) {
    timeoutId = setTimeout(() => {
      handleDismiss()
    }, props.autoTimeoutMs)

    // Progress bar animation
    if (props.showProgress) {
      const interval = 50 // Update every 50ms
      const totalSteps = props.autoTimeoutMs / interval
      let currentStep = 0

      progressInterval = setInterval(() => {
        currentStep++
        progressWidth.value = Math.max(0, 100 - (currentStep / totalSteps) * 100)
        
        if (currentStep >= totalSteps) {
          clearInterval(progressInterval)
          progressInterval = null
        }
      }, interval)
    }
  }
})

onUnmounted(() => {
  clearTimeouts()
})
</script>