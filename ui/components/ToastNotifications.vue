<template>
  <Teleport to="body">
    <!-- General Zone (top-right) -->
    <div
      v-if="generalNotifications.length > 0"
      class="fixed top-4 right-4 z-50 space-y-3 max-w-sm w-full"
      role="region"
      aria-label="General Notifications"
    >
      <TransitionGroup
        name="toast"
        tag="div"
        class="space-y-3"
      >
        <div
          v-for="notification in generalNotifications"
          :key="notification.id"
          :class="[
            'relative p-4 rounded-xl border shadow-lg backdrop-blur-sm transition-all duration-300',
            getNotificationClasses(notification.type, notification.zone)
          ]"
          :role="notification.type === 'error' ? 'alert' : 'status'"
          :aria-live="notification.type === 'error' ? 'assertive' : 'polite'"
        >
          <div class="flex items-start gap-3">
            <!-- Icon -->
            <div class="flex-shrink-0 mt-0.5">
              <img 
                v-if="notification.customIcon" 
                :src="notification.customIcon" 
                :alt="notification.title || 'Notification'" 
                :class="[
                  'w-6 h-6 rounded border-2',
                  notification.type === 'error' ? 'border-red-500' : 'border-transparent'
                ]"
                @error="$event.target.style.display='none'"
              />
              <component 
                v-else
                :is="getIconComponent(notification.type, notification.zone)" 
                :class="getIconClasses(notification.type, notification.zone)" 
              />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <h4 
                v-if="notification.title" 
                :class="getTitleClasses(notification.type, notification.zone)"
              >
                {{ notification.title }}
              </h4>
              
              <p :class="getMessageClasses(notification.type, notification.zone)">
                {{ notification.message }}
              </p>

              <!-- Action buttons -->
              <div v-if="notification.actions && notification.actions.length > 0" class="mt-2 flex gap-2">
                <button
                  v-for="action in notification.actions"
                  :key="action.label"
                  @click="handleAction(notification.id, action)"
                  :class="[
                    'px-2 py-1 text-xs font-medium rounded transition-colors',
                    action.primary ? getPrimaryActionClass(notification.type, notification.zone) : getSecondaryActionClass(notification.type, notification.zone)
                  ]"
                >
                  {{ action.label }}
                </button>
              </div>
            </div>

            <!-- Close button -->
            <button
              @click="removeNotification(notification.id)"
              :class="[
                'flex-shrink-0 p-1 rounded transition-colors',
                getCloseButtonClass(notification.type, notification.zone)
              ]"
              aria-label="Dismiss notification"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>

          <!-- Progress bar for auto-dismiss -->
          <div
            v-if="notification.autoTimeoutMs && notification.showProgress !== false"
            class="absolute bottom-0 left-0 h-1 bg-current opacity-30 transition-all duration-100"
            :style="{ width: notification.progress + '%' }"
          ></div>
        </div>
      </TransitionGroup>
    </div>

    <!-- Connection Zone (top-center) -->
    <div
      v-if="connectionNotifications.length > 0"
      class="fixed top-20 left-1/2 transform -translate-x-1/2 z-50 space-y-3 max-w-sm w-full"
      role="region"
      aria-label="Connection Notifications"
    >
      <TransitionGroup
        name="toast-connection"
        tag="div"
        class="space-y-3"
      >
        <div
          v-for="notification in connectionNotifications"
          :key="notification.id"
          :class="[
            'relative p-4 rounded-xl border shadow-lg backdrop-blur-xl transition-all duration-300',
            getNotificationClasses(notification.type, notification.zone)
          ]"
          :role="notification.type === 'error' ? 'alert' : 'status'"
          :aria-live="notification.type === 'error' ? 'assertive' : 'polite'"
        >
          <!-- Connection Zone Content (simpler layout) -->
          <div class="flex items-start">
            <!-- Icon -->
            <div class="flex-shrink-0">
              <component 
                :is="getIconComponent(notification.type, notification.zone)" 
                :class="getIconClasses(notification.type, notification.zone)" 
              />
            </div>
            
            <!-- Content -->
            <div class="ml-3 flex-1">
              <p :class="getTitleClasses(notification.type, notification.zone)">{{ notification.title || notification.message }}</p>
              <p v-if="notification.title && notification.message" :class="getMessageClasses(notification.type, notification.zone)">{{ notification.message }}</p>
            </div>
            
            <!-- Close button -->
            <button
              @click="removeNotification(notification.id)"
              :class="[
                'ml-4 flex-shrink-0 p-1 rounded transition-colors',
                getCloseButtonClass(notification.type, notification.zone)
              ]"
              aria-label="Dismiss notification"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, provide, h } from 'vue'

// Notification store
const notifications = ref([])

// Zone-based computed properties
const generalNotifications = computed(() => 
  notifications.value.filter(n => !n.zone || n.zone === 'general')
)

const connectionNotifications = computed(() => 
  notifications.value.filter(n => n.zone === 'connection')
)

// Notification methods
const addNotification = (notification) => {
  const id = Date.now() + Math.random()
  const defaultTimeouts = {
    general: { success: 5000, error: 8000, warning: 5000, info: 5000 },
    connection: { success: 4000, error: 4000, loading: 0 }
  }
  
  const zone = notification.zone || 'general'
  const defaultTimeout = defaultTimeouts[zone][notification.type] ?? defaultTimeouts.general[notification.type] ?? 5000
  
  const newNotification = {
    id,
    type: 'info',
    zone,
    autoTimeoutMs: defaultTimeout,
    showProgress: zone === 'general',
    progress: 100,
    ...notification
  }

  notifications.value.push(newNotification)

  // Setup auto-dismiss
  if (newNotification.autoTimeoutMs) {
    setupAutoTimeoutForNotification(newNotification)
  }

  return id
}

const removeNotification = (id) => {
  const index = notifications.value.findIndex(n => n.id === id)
  if (index > -1) {
    notifications.value.splice(index, 1)
  }
}

const clearAll = () => {
  notifications.value = []
}

const handleAction = (notificationId, action) => {
  if (action.handler) {
    action.handler()
  }
  if (action.dismiss !== false) {
    removeNotification(notificationId)
  }
}

const setupAutoTimeoutForNotification = (notification) => {
  if (!notification.autoTimeoutMs) return

  const interval = 50
  const totalSteps = notification.autoTimeoutMs / interval
  let currentStep = 0

  const progressInterval = setInterval(() => {
    currentStep++
    notification.progress = Math.max(0, 100 - (currentStep / totalSteps) * 100)
    
    if (currentStep >= totalSteps) {
      clearInterval(progressInterval)
      removeNotification(notification.id)
    }
  }, interval)

  // Store interval ID for cleanup if needed
  notification._progressInterval = progressInterval
}

// Style helper methods
const getNotificationClasses = (type, zone = 'general') => {
  if (zone === 'connection') {
    return 'bg-gray-800/95 border-gray-700/50 text-white'
  }
  
  const classes = {
    error: 'bg-red-900/90 border-red-500/50 text-red-100',
    warning: 'bg-yellow-900/90 border-yellow-500/50 text-yellow-100',
    info: 'bg-blue-900/90 border-blue-500/50 text-blue-100',
    success: 'bg-green-900/90 border-green-500/50 text-green-100'
  }
  return classes[type] || classes.info
}

const getIconClasses = (type, zone = 'general') => {
  if (zone === 'connection') {
    const connectionClasses = {
      success: 'w-6 h-6 text-green-400',
      error: 'w-6 h-6 text-red-400',
      loading: 'w-6 h-6 text-blue-400'
    }
    return connectionClasses[type] || 'w-6 h-6 text-gray-400'
  }
  
  const classes = {
    error: 'w-5 h-5 text-red-400',
    warning: 'w-5 h-5 text-yellow-400',
    info: 'w-5 h-5 text-blue-400',
    success: 'w-5 h-5 text-green-400'
  }
  return classes[type] || classes.info
}

const getTitleClasses = (type, zone = 'general') => {
  if (zone === 'connection') {
    return 'text-sm font-medium text-white'
  }
  
  const classes = {
    error: 'text-red-200 font-semibold text-sm mb-1',
    warning: 'text-yellow-200 font-semibold text-sm mb-1',
    info: 'text-blue-200 font-semibold text-sm mb-1',
    success: 'text-green-200 font-semibold text-sm mb-1'
  }
  return classes[type] || classes.info
}

const getMessageClasses = (type, zone = 'general') => {
  if (zone === 'connection') {
    return 'mt-1 text-sm text-gray-300'
  }
  return 'text-sm leading-relaxed'
}

const getPrimaryActionClass = (type, zone = 'general') => {
  if (zone === 'connection') {
    return 'bg-gray-600 text-white hover:bg-gray-700'
  }
  
  const classes = {
    error: 'bg-red-600 text-white hover:bg-red-700',
    warning: 'bg-yellow-600 text-white hover:bg-yellow-700',
    info: 'bg-blue-600 text-white hover:bg-blue-700',
    success: 'bg-green-600 text-white hover:bg-green-700'
  }
  return classes[type] || classes.info
}

const getSecondaryActionClass = (type, zone = 'general') => {
  if (zone === 'connection') {
    return 'bg-gray-600/20 text-gray-200 hover:bg-gray-600/30'
  }
  
  const classes = {
    error: 'bg-red-600/20 text-red-200 hover:bg-red-600/30',
    warning: 'bg-yellow-600/20 text-yellow-200 hover:bg-yellow-600/30',
    info: 'bg-blue-600/20 text-blue-200 hover:bg-blue-600/30',
    success: 'bg-green-600/20 text-green-200 hover:bg-green-600/30'
  }
  return classes[type] || classes.info
}

const getCloseButtonClass = (type, zone = 'general') => {
  if (zone === 'connection') {
    return 'text-gray-400 hover:text-white hover:bg-gray-700/50'
  }
  
  const classes = {
    error: 'text-red-400 hover:text-red-300 hover:bg-red-800/30',
    warning: 'text-yellow-400 hover:text-yellow-300 hover:bg-yellow-800/30',
    info: 'text-blue-400 hover:text-blue-300 hover:bg-blue-800/30',
    success: 'text-green-400 hover:text-green-300 hover:bg-green-800/30'
  }
  return classes[type] || classes.info
}

const getIconComponent = (type, zone = 'general') => {
  // Connection zone icons (simpler, more focused)
  if (zone === 'connection') {
    const connectionIcons = {
      success: () => h('div', { class: 'w-6 h-6 bg-green-500/20 rounded-full flex items-center justify-center' }, [
        h('svg', { class: 'w-4 h-4 text-green-400', fill: 'currentColor', viewBox: '0 0 20 20' }, [
          h('path', { 'fill-rule': 'evenodd', d: 'M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z', 'clip-rule': 'evenodd' })
        ])
      ]),
      error: () => h('div', { class: 'w-6 h-6 bg-red-500/20 rounded-full flex items-center justify-center' }, [
        h('svg', { class: 'w-4 h-4 text-red-400', fill: 'currentColor', viewBox: '0 0 20 20' }, [
          h('path', { 'fill-rule': 'evenodd', d: 'M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z', 'clip-rule': 'evenodd' })
        ])
      ]),
      loading: () => h('div', { class: 'w-6 h-6 bg-blue-500/20 rounded-full flex items-center justify-center' }, [
        h('div', { class: 'w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full animate-spin' })
      ])
    }
    return connectionIcons[type] || connectionIcons.success
  }
  
  // General zone icons (detailed)
  const icons = {
    error: () => h('svg', { class: 'w-5 h-5', viewBox: '0 0 24 24', fill: 'none' }, [
      h('circle', { cx: '12', cy: '12', r: '10', stroke: 'currentColor', 'stroke-width': '2' }),
      h('line', { x1: '15', y1: '9', x2: '9', y2: '15', stroke: 'currentColor', 'stroke-width': '2' }),
      h('line', { x1: '9', y1: '9', x2: '15', y2: '15', stroke: 'currentColor', 'stroke-width': '2' })
    ]),
    warning: () => h('svg', { class: 'w-5 h-5', viewBox: '0 0 24 24', fill: 'none' }, [
      h('path', { d: 'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z', stroke: 'currentColor', 'stroke-width': '2', fill: 'none' }),
      h('line', { x1: '12', y1: '9', x2: '12', y2: '13', stroke: 'currentColor', 'stroke-width': '2' }),
      h('circle', { cx: '12', cy: '17', r: '1', fill: 'currentColor' })
    ]),
    info: () => h('svg', { class: 'w-5 h-5', viewBox: '0 0 24 24', fill: 'none' }, [
      h('circle', { cx: '12', cy: '12', r: '10', stroke: 'currentColor', 'stroke-width': '2' }),
      h('line', { x1: '12', y1: '16', x2: '12', y2: '12', stroke: 'currentColor', 'stroke-width': '2' }),
      h('circle', { cx: '12', cy: '8', r: '1', fill: 'currentColor' })
    ]),
    success: () => h('svg', { class: 'w-5 h-5', viewBox: '0 0 24 24', fill: 'none' }, [
      h('path', { d: 'M22 11.08V12a10 10 0 1 1-5.93-9.14', stroke: 'currentColor', 'stroke-width': '2', fill: 'none' }),
      h('polyline', { points: '22,4 12,14.01 9,11.01', stroke: 'currentColor', 'stroke-width': '2', fill: 'none' })
    ])
  }
  return icons[type] || icons.info
}

// Expose methods globally
const notificationManager = {
  // General zone methods (backward compatible)
  success: (message, options = {}) => addNotification({ ...options, type: 'success', message, zone: 'general' }),
  error: (message, options = {}) => addNotification({ ...options, type: 'error', message, zone: 'general' }),
  warning: (message, options = {}) => addNotification({ ...options, type: 'warning', message, zone: 'general' }),
  info: (message, options = {}) => addNotification({ ...options, type: 'info', message, zone: 'general' }),
  
  // Connection zone methods
  connection: {
    success: (message, options = {}) => addNotification({ ...options, type: 'success', message, zone: 'connection' }),
    loading: (message, options = {}) => addNotification({ ...options, type: 'loading', message, zone: 'connection', autoTimeoutMs: 0 }),
    error: (message, options = {}) => addNotification({ ...options, type: 'error', message, zone: 'connection' })
  },
  
  // Utility methods
  add: addNotification,
  remove: removeNotification,
  clear: clearAll
}

// Make available globally
onMounted(() => {
  if (typeof window !== 'undefined') {
    window.$toast = notificationManager
  }
})

// Provide to child components
provide('toast', notificationManager)

// Expose for parent components
defineExpose(notificationManager)
</script>

<style scoped>
/* General zone toast animations (slide from right) */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.toast-move {
  transition: transform 0.3s ease;
}

/* Connection zone toast animations (fade + slide from top) */
.toast-connection-enter-active,
.toast-connection-leave-active {
  transition: all 0.3s ease;
}

.toast-connection-enter-from {
  opacity: 0;
  transform: translateY(-20px);
}

.toast-connection-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

.toast-connection-move {
  transition: transform 0.3s ease;
}
</style>