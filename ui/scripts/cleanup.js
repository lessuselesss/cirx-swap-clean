#!/usr/bin/env node

/**
 * Cleanup script for development environment
 * Removes build artifacts, cache files, and temporary data
 */

import { promises as fs } from 'fs'
import { exec } from 'child_process'
import { promisify } from 'util'
import path from 'path'

const execAsync = promisify(exec)

// Directories and files to clean
const CLEANUP_TARGETS = [
  '.nuxt',
  '.output', 
  '.cache',
  '.nitro',
  '.data',
  'dist',
  'build',
  'coverage',
  '.nyc_output',
  'node_modules/.cache',
  '*.log',
  '*.tmp',
  '*.temp'
]

// Colors for console output
const colors = {
  reset: '\x1b[0m',
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  magenta: '\x1b[35m',
  cyan: '\x1b[36m'
}

const log = (message, color = 'reset') => {
  console.log(`${colors[color]}${message}${colors.reset}`)
}

/**
 * Check if path exists
 */
const pathExists = async (targetPath) => {
  try {
    await fs.access(targetPath)
    return true
  } catch {
    return false
  }
}

/**
 * Get directory size
 */
const getDirectorySize = async (dirPath) => {
  try {
    const { stdout } = await execAsync(`du -sh "${dirPath}" 2>/dev/null || echo "0B"`)
    return stdout.trim().split('\t')[0]
  } catch {
    return '0B'
  }
}

/**
 * Remove directory or file
 */
const removeTarget = async (target) => {
  try {
    const stats = await fs.stat(target)
    const size = stats.isDirectory() ? await getDirectorySize(target) : '0B'
    
    if (stats.isDirectory()) {
      await fs.rm(target, { recursive: true, force: true })
      log(`✅ Removed directory: ${target} (${size})`, 'green')
    } else {
      await fs.unlink(target)
      log(`✅ Removed file: ${target}`, 'green')
    }
    
    return true
  } catch (error) {
    if (error.code !== 'ENOENT') {
      log(`❌ Failed to remove ${target}: ${error.message}`, 'red')
    }
    return false
  }
}

/**
 * Clean glob patterns (like *.log)
 */
const cleanGlobPattern = async (pattern) => {
  try {
    const { stdout } = await execAsync(`find . -name "${pattern}" -type f 2>/dev/null || true`)
    const files = stdout.trim().split('\n').filter(f => f.length > 0)
    
    let removed = 0
    for (const file of files) {
      if (await removeTarget(file)) {
        removed++
      }
    }
    
    if (removed > 0) {
      log(`✅ Removed ${removed} files matching ${pattern}`, 'green')
    }
  } catch (error) {
    log(`❌ Failed to clean pattern ${pattern}: ${error.message}`, 'red')
  }
}

/**
 * Main cleanup function
 */
const cleanup = async () => {
  log('🧹 Starting cleanup process...', 'cyan')
  
  let totalRemoved = 0
  
  for (const target of CLEANUP_TARGETS) {
    if (target.includes('*')) {
      // Handle glob patterns
      await cleanGlobPattern(target)
    } else if (await pathExists(target)) {
      if (await removeTarget(target)) {
        totalRemoved++
      }
    }
  }
  
  // Clean npm cache if requested
  if (process.argv.includes('--npm-cache')) {
    log('🗑️ Cleaning npm cache...', 'yellow')
    try {
      await execAsync('npm cache clean --force')
      log('✅ npm cache cleaned', 'green')
    } catch (error) {
      log(`❌ Failed to clean npm cache: ${error.message}`, 'red')
    }
  }
  
  // Show completion message
  if (totalRemoved > 0) {
    log(`\n🎉 Cleanup completed! Removed ${totalRemoved} targets.`, 'green')
  } else {
    log('\n✨ Nothing to clean - workspace is already tidy!', 'blue')
  }
  
  // Show current disk usage
  try {
    const nodeModulesSize = await getDirectorySize('node_modules')
    log(`📊 Current node_modules size: ${nodeModulesSize}`, 'magenta')
  } catch {
    // Ignore if node_modules doesn't exist
  }
}

/**
 * Show help message
 */
const showHelp = () => {
  log('🧹 Cleanup Script for Circular CIRX Platform', 'cyan')
  log('')
  log('Usage:', 'yellow')
  log('  node scripts/cleanup.js [options]')
  log('')
  log('Options:', 'yellow')
  log('  --help        Show this help message')
  log('  --npm-cache   Also clean npm cache')
  log('')
  log('Targets cleaned:', 'yellow')
  CLEANUP_TARGETS.forEach(target => {
    log(`  - ${target}`)
  })
}

// Run the script
if (process.argv.includes('--help')) {
  showHelp()
} else {
  cleanup().catch(error => {
    log(`💥 Cleanup failed: ${error.message}`, 'red')
    process.exit(1)
  })
}