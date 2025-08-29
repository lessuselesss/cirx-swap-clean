import { ensureBackendRunning, stopBackend } from '../backend-manager.js'

export default defineNitroPlugin(async (nitroApp) => {
  // Start backend immediately when Nitro starts
  console.log('🚀 Nitro server starting, checking backend...')
  
  const success = await ensureBackendRunning()
  
  if (!success) {
    console.error('⚠️ Backend could not be started automatically')
    console.error('📝 Please ensure PHP is installed and accessible')
    console.error('📝 You can start it manually with:')
    console.error('   cd backend && php -S localhost:8080 public/index.php')
  }
  
  // Stop backend when Nitro shuts down
  nitroApp.hooks.hook('close', () => {
    console.log('🛑 Nitro shutting down, stopping backend...')
    stopBackend()
  })
})