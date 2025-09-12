<template>
  <div class="swap-arrow-container">
    <button
      type="button"
      :class="[
        'swap-arrow-button',
        activeTab === 'liquid' ? 'swap-arrow-liquid' : 'swap-arrow-otc'
      ]"
      @click="$emit('reverse-swap')"
      :disabled="disabled"
    >
      <!-- Loading Spinner -->
      <svg v-if="isRefreshing" class="animate-spin w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
      </svg>
      
      <!-- Swap Arrow Icon -->
      <div v-else class="text-xl font-bold" style="letter-spacing: -0.1em;">
        ⥯
      </div>
    </button>
  </div>
</template>

<script setup>
defineProps({
  activeTab: {
    type: String,
    required: true,
    validator: (value) => ['liquid', 'otc'].includes(value)
  },
  isRefreshing: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

defineEmits(['reverse-swap'])
</script>

<style scoped>
.swap-arrow-container {
  @apply flex justify-center items-center py-2;
}

.swap-arrow-button {
  @apply relative w-12 h-12 rounded-full border-2 flex items-center justify-center transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed;
}

.swap-arrow-liquid {
  @apply border-circular-primary bg-circular-primary/10 text-circular-primary hover:bg-circular-primary/20;
}

.swap-arrow-otc {
  @apply border-circular-purple bg-circular-purple/10 text-circular-purple hover:bg-circular-purple/20;
}

.swap-arrow-button:hover:not(:disabled) {
  @apply scale-105;
}

.swap-arrow-button:active:not(:disabled) {
  @apply scale-95;
}
</style>