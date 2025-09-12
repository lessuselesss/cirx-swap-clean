<template>
  <div class="relative">
    <div class="relative p-6 sm:p-8 rounded-2xl border border-cyan-500/30 shadow-2xl shadow-cyan-500/10 transition-all duration-300 gradient-border min-h-[600px]" style="background-color: rgba(0, 3, 6, 0.9);">
      
      <!-- Tab Selector -->
      <SwapTabSelector 
        :activeTab="activeTab" 
        @tab-change="$emit('tab-change', $event)"
      />

      <!-- Swap Form -->
      <form @submit.prevent novalidate>
        <slot name="swap-fields" />
        <slot name="quote-panel" />
        <slot name="address-input" />
        <slot name="cta-button" />
      </form>

      <!-- Floating Action Pills -->
      <slot name="floating-actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  activeTab: {
    type: String,
    required: true,
    validator: (value) => ['liquid', 'otc'].includes(value)
  }
})

defineEmits(['tab-change'])
</script>

<style scoped>
.gradient-border {
  background: linear-gradient(135deg, rgba(0,255,255,0.1) 0%, rgba(128,0,255,0.1) 100%);
}
</style>