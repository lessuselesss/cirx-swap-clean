<template>
  <button
    :class="buttonClasses"
    :disabled="disabled || loading"
    :type="type"
    @click="$emit('click', $event)"
  >
    <div 
      v-if="loading" 
      class="animate-spin w-4 h-4 border-2 border-current border-t-transparent rounded-full mr-2"
    ></div>
    
    <slot name="icon" />
    
    <span v-if="$slots.default" :class="{ 'ml-2': $slots.icon }">
      <slot />
    </span>
  </button>
</template>

<script setup>
/**
 * Base Button Component
 * Consolidates duplicate button styling and behavior across the app
 */

const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (value) => ['primary', 'secondary', 'danger', 'outline', 'ghost'].includes(value)
  },
  size: {
    type: String, 
    default: 'md',
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl'].includes(value)
  },
  disabled: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  fullWidth: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'button',
    validator: (value) => ['button', 'submit', 'reset'].includes(value)
  }
})

defineEmits(['click'])

const buttonClasses = computed(() => [
  'inline-flex items-center justify-center font-medium transition-all duration-300 border-2 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-900',
  
  // Size classes
  {
    'px-2 py-1 text-xs rounded-md': props.size === 'xs',
    'px-3 py-1.5 text-sm rounded-lg': props.size === 'sm',
    'px-4 py-2 text-base rounded-xl': props.size === 'md', 
    'px-6 py-3 text-lg rounded-xl': props.size === 'lg',
    'px-8 py-4 text-xl rounded-2xl': props.size === 'xl'
  },
  
  // Variant classes
  {
    'bg-circular-primary text-gray-900 border-transparent hover:bg-circular-primary-hover focus:ring-circular-primary': props.variant === 'primary',
    'bg-gray-700 text-white border-gray-600 hover:bg-gray-600 focus:ring-gray-500': props.variant === 'secondary',
    'bg-red-600 text-white border-transparent hover:bg-red-700 focus:ring-red-500': props.variant === 'danger',
    'bg-transparent border-circular-primary text-circular-primary hover:bg-circular-primary hover:text-gray-900 focus:ring-circular-primary': props.variant === 'outline',
    'bg-transparent border-transparent text-gray-300 hover:text-white hover:bg-gray-700 focus:ring-gray-500': props.variant === 'ghost'
  },
  
  // State classes
  {
    'w-full': props.fullWidth,
    'opacity-50 cursor-not-allowed': props.disabled,
    'cursor-wait': props.loading && !props.disabled
  }
])
</script>