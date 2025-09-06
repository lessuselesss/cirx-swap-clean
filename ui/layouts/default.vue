<template>
  <div class="min-h-screen flex flex-col">
    <!-- Header -->
    <header class="header">
      <div class="container">
        <nav class="nav">
          <NuxtLink to="/" class="logo flex items-center gap-2">
            <img 
              src="/circular-logo.svg" 
              alt="Circular Protocol" 
              class="h-8 w-auto"
            />
          </NuxtLink>
          <div class="flex items-center gap-4">
            <!-- Emergency Contact Notice -->
            <div class="emergency-contact">
              Phone broken. lessuseless[at]proton.me
            </div>
            <NuxtLink to="/swap" class="nav-link">
              Swap
            </NuxtLink>
            <AppKitButton />
          </div>
        </nav>
      </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 main-content">
      <div class="container">
        <slot />
      </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
      <div class="container">
        <p>&copy; 2024 Circular CIRX. Professional OTC Trading Platform.</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
// Uses custom AppKitButton component for reliable wallet connection (AppKit integration)
</script>

<style scoped>
.nav-link {
  color: #6b7280;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.2s;
}

.nav-link:hover {
  color: #111827;
}

.nav-link.router-link-active {
  color: #2563eb;
}

/* Emergency contact styling */
.emergency-contact {
  background-color: #dc2626; /* Red background */
  color: white;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  white-space: nowrap;
}

/* Removed disabled wallet button styles - now using custom AppKitButton */
</style>