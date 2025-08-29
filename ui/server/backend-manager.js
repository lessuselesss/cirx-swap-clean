import { spawn } from 'child_process'
import { existsSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
let backendProcess = null
let isBackendStarting = false

async function checkBackendHealth() {
  try {
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 3000) // Increased timeout
    
    // Use ping endpoint for faster response
    const response = await fetch('http://localhost:18423/api/v1/ping', {
      signal: controller.signal
    })
    
    clearTimeout(timeoutId)
    
    if (response.ok) {
      const data = await response.json()
      // Check that it's a proper backend response
      if (data && data.ping === true) {
        return true
      }
    }
    
    return false
  } catch (error) {
    // Be more specific about the error for debugging
    console.log(`Backend health check failed: ${error.name} - ${error.message}`)
    return false
  }
}

async function startBackendProcess() {
  if (isBackendStarting || backendProcess) {
    console.log('⚠️ Backend is already starting or running')
    return true
  }

  isBackendStarting = true
  
  // Find backend directory (relative to ui/server directory)
  const backendPath = join(__dirname, '..', '..', '..', 'backend')
  const indexPath = join(backendPath, 'public', 'index.php')
  
  if (!existsSync(indexPath)) {
    console.error('❌ Backend index.php not found at:', indexPath)
    isBackendStarting = false
    return false
  }

  console.log('🚀 Starting PHP backend server on port 18423...')
  console.log('📁 Backend path:', backendPath)
  
  // Try different PHP commands in order of preference
  const phpCommands = [
    // NixOS with nix run
    ['nix', ['run', 'nixpkgs#php', '--', '-S', 'localhost:18423', 'public/index.php']],
    // System PHP
    ['php', ['-S', 'localhost:18423', 'public/index.php']],
    // Common PHP installations
    ['php8.2', ['-S', 'localhost:18423', 'public/index.php']],
    ['php8.1', ['-S', 'localhost:18423', 'public/index.php']],
    ['php8.0', ['-S', 'localhost:18423', 'public/index.php']]
  ]
  
  for (const [command, args] of phpCommands) {
    try {
      console.log(`🔧 Trying: ${command} ${args.join(' ')}`)
      
      backendProcess = spawn(command, args, {
        cwd: backendPath,
        stdio: ['ignore', 'pipe', 'pipe'],
        detached: false,
        env: {
          ...process.env,
          // Ensure PHP uses the right environment
          PHP_CLI_SERVER_WORKERS: '4'
        }
      })
      
      // Handle process events
      backendProcess.stdout.on('data', (data) => {
        const output = data.toString().trim()
        if (output) console.log(`[Backend] ${output}`)
      })
      
      backendProcess.stderr.on('data', (data) => {
        const output = data.toString().trim()
        if (output && !output.includes('Development Server')) {
          console.error(`[Backend Error] ${output}`)
        }
      })
      
      backendProcess.on('error', (err) => {
        if (err.code === 'ENOENT') {
          // Command not found, try next
          backendProcess = null
        } else {
          console.error(`❌ Backend process error: ${err.message}`)
        }
      })
      
      backendProcess.on('exit', (code, signal) => {
        console.log(`⚠️ Backend process exited (code: ${code}, signal: ${signal})`)
        backendProcess = null
        isBackendStarting = false
        
        // Auto-restart if it crashes
        if (code !== 0 && code !== null) {
          console.log('🔄 Attempting to restart backend in 5 seconds...')
          setTimeout(() => startBackendProcess(), 5000)
        }
      })
      
      // Wait a bit to see if process starts successfully
      await new Promise(resolve => setTimeout(resolve, 500))
      
      if (backendProcess && !backendProcess.killed) {
        console.log(`✅ Backend process started with ${command}`)
        break
      }
    } catch (err) {
      console.log(`⚠️ Failed to start with ${command}: ${err.message}`)
      backendProcess = null
      continue
    }
  }
  
  if (!backendProcess) {
    console.error('❌ Could not start backend with any PHP command')
    isBackendStarting = false
    return false
  }
  
  // Wait for backend to be ready (up to 30 seconds)
  console.log('⏳ Waiting for backend to be ready...')
  let attempts = 0
  while (attempts < 30) {
    await new Promise(resolve => setTimeout(resolve, 1000))
    if (await checkBackendHealth()) {
      console.log('✅ Backend is ready and responding at http://localhost:18423')
      isBackendStarting = false
      return true
    }
    attempts++
  }
  
  console.error('❌ Backend failed to become ready within 30 seconds')
  if (backendProcess) {
    backendProcess.kill()
    backendProcess = null
  }
  isBackendStarting = false
  return false
}

export async function ensureBackendRunning() {
  // Check if backend is already running
  const isRunning = await checkBackendHealth()
  
  if (isRunning) {
    console.log('✅ Backend is already running on port 18423')
    return true
  }
  
  console.log('⚠️ Backend not detected, starting it...')
  return await startBackendProcess()
}

export function stopBackend() {
  if (backendProcess) {
    console.log('🛑 Stopping backend process...')
    backendProcess.kill('SIGTERM')
    backendProcess = null
  }
}

// Handle process termination
process.on('SIGINT', () => {
  stopBackend()
  process.exit(0)
})

process.on('SIGTERM', () => {
  stopBackend()
  process.exit(0)
})