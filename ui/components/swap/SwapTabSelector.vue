<template>
  <div class="flex mb-6 rounded-xl p-1 gap-1 overflow-hidden" style="background-color: #11161f;">
    <button
      @click="$emit('tab-change', 'liquid')"
      :class="[
        'flex-1 px-3 sm:px-4 py-3 text-sm font-medium font-michroma transition-all duration-300 rounded-lg flex items-center justify-center gap-1 sm:gap-2 min-w-0 basis-0',
        activeTab === 'liquid' 
          ? 'text-circular-primary bg-circular-primary/20' 
          : 'text-gray-400 hover:text-white hover:bg-gray-700/50'
      ]"
    >
      <div class="flex items-center gap-1 sm:gap-2">
        <!-- Liquid Swap Icon -->
        <img src="/buy_liquid.svg" alt="Liquid" class="w-3 h-2.5 flex-shrink-0" />
        <span class="text-xs sm:text-sm text-center leading-tight">
          <span class="block md:inline">Buy</span>
          <span class="block md:inline"> Liquid</span>
        </span>
      </div>
    </button>
    <button
      @click="$emit('tab-change', 'otc')"
      :class="[
        'flex-1 px-3 sm:px-4 py-3 text-sm font-medium font-michroma transition-all duration-300 rounded-lg flex items-center justify-center gap-1 sm:gap-2 min-w-0 basis-0',
        activeTab === 'otc' 
          ? 'text-circular-purple bg-circular-purple/20' 
          : 'text-gray-400 hover:text-white hover:bg-gray-700/50'
      ]"
    >
      <div class="flex items-center gap-1 sm:gap-2">
        <!-- OTC Contract Icon -->
        <img src="/buy_otc_purple.svg" alt="OTC" class="w-3 h-3.5 flex-shrink-0" />
        <span class="text-xs sm:text-sm text-center leading-tight">
          <span class="block md:inline">Vested</span>
          <span class="block md:inline"> OTC</span>
        </span>
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
  }
})

defineEmits(['tab-change'])
</script>