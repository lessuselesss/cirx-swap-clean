<template>
  <div class="min-h-screen relative overflow-hidden bg-figma-base">
    <!-- Space Background -->
    <div key="static-background" class="absolute inset-0 z-0 space-background"></div>
    <!-- Gradient overlay -->
    <div key="static-gradient" class="absolute inset-0 z-10 gradient-overlay"></div>
    
    <!-- Header -->
    <header class="sticky top-0 z-50 relative">
      <div class="max-w-7xl mx-auto px-6 sm:px-8 lg:px-12">
        <div class="flex justify-between items-center h-24">
          <!-- Logo Section -->
          <div class="flex items-center gap-4">
            <img 
              src="/images/logo/SVG/color-logo-white-svg.svg" 
              alt="Circular Protocol" 
              class="h-10 w-auto drop-shadow-lg"
            />
          </div>

          <!-- Navigation & Wallet Section -->
          <div class="flex items-center gap-4">
            <!-- Status Tracking Link -->
            <NuxtLink 
              to="/transactions" 
              class="px-3 py-2 text-sm text-gray-300 hover:text-white transition-colors rounded-lg hover:bg-gray-800/50"
            >
              Transactions
            </NuxtLink>
            
            <!-- AppKit Wallet Connection -->
            <ClientOnly>
              <template #fallback>
                <div class="w-32 h-10 bg-gray-800 rounded-lg animate-pulse"></div>
              </template>
              <AppKitButton v-if="$appkit && typeof $appkit === 'object' && !$appkit.disabled" />
              <div v-else-if="$appkit?.disabled" class="px-4 py-2 bg-gray-600 text-gray-300 rounded-lg text-sm">
                Wallet Configuration Error
              </div>
              <div v-else class="px-4 py-2 bg-gray-600 text-gray-300 rounded-lg text-sm">
                Wallet Unavailable
              </div>
            </ClientOnly>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Content -->
    <main class="relative z-20 pt-6">
      <slot />
    </main>
  </div>
</template>

<script setup>
// Global layout with space background, header, and wallet integration
// Uses custom AppKitButton component for reliable wallet connection
</script>

<style>
/* Global background and overlay styles */
.space-background {
  background-color: #0B0E13; /* Fallback background */
  background-image: url('/background.png');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  /* Removed background-attachment: fixed for better mobile performance */
}

.gradient-overlay {
  background: linear-gradient(to bottom, rgba(11,14,19,0.95) 0%, rgba(11,14,19,0.60) 50%, rgba(0,0,0,0) 100%);
}
</style>