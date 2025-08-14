#!/usr/bin/env node
/**
 * Advanced console capture using chromium with JavaScript execution
 */

const { writeFileSync } = require('fs');
const { spawn } = require('child_process');

// Create a JavaScript snippet to capture console output
const consoleScript = `
// Override console methods to capture output
const originalLog = console.log;
const originalError = console.error;
const originalWarn = console.warn;
const originalInfo = console.info;

window.capturedLogs = [];

console.log = function(...args) {
  window.capturedLogs.push({type: 'log', args: args, timestamp: Date.now()});
  originalLog.apply(console, args);
};

console.error = function(...args) {
  window.capturedLogs.push({type: 'error', args: args, timestamp: Date.now()});
  originalError.apply(console, args);
};

console.warn = function(...args) {
  window.capturedLogs.push({type: 'warn', args: args, timestamp: Date.now()});
  originalWarn.apply(console, args);
};

console.info = function(...args) {
  window.capturedLogs.push({type: 'info', args: args, timestamp: Date.now()});
  originalInfo.apply(console, args);
};

// Wait for the app to initialize and capture logs
setTimeout(() => {
  // Try to run debug commands if available
  if (typeof otcDebug !== 'undefined') {
    console.log('🔍 Running otcDebug.diagnose()...');
    try {
      otcDebug.diagnose();
    } catch (e) {
      console.error('Failed to run otcDebug.diagnose():', e);
    }
  }
  
  // Output captured logs as JSON for easy parsing
  const output = {
    timestamp: new Date().toISOString(),
    logs: window.capturedLogs,
    userAgent: navigator.userAgent,
    url: window.location.href,
    errors: window.capturedLogs.filter(log => log.type === 'error'),
    warnings: window.capturedLogs.filter(log => log.type === 'warn')
  };
  
  console.log('=== CAPTURED_LOGS_START ===');
  console.log(JSON.stringify(output, null, 2));
  console.log('=== CAPTURED_LOGS_END ===');
  
}, 3000);
`;

async function captureConsoleLogs() {
  console.log('🔍 Capturing console logs from http://localhost:3000...\n');

  // Write the script to a temporary file
  writeFileSync('/tmp/console-capture.js', consoleScript);

  const chromiumArgs = [
    '--headless',
    '--disable-gpu',
    '--disable-dev-shm-usage', 
    '--no-sandbox',
    '--virtual-time-budget=5000',
    '--run-all-compositor-stages-before-draw',
    `--evaluate-script=file:///tmp/console-capture.js`,
    'http://localhost:3000'
  ];

  return new Promise((resolve, reject) => {
    const chromium = spawn('nix-shell', [
      '-p', 'chromium', 
      '--run', 
      `chromium ${chromiumArgs.join(' ')}`
    ], {
      stdio: ['pipe', 'pipe', 'pipe']
    });

    let stdout = '';
    let stderr = '';

    chromium.stdout.on('data', (data) => {
      stdout += data.toString();
    });

    chromium.stderr.on('data', (data) => {
      stderr += data.toString();
    });

    chromium.on('close', (code) => {
      console.log('📊 Results:\n');
      
      // Look for our captured logs JSON
      const logMatch = stderr.match(/=== CAPTURED_LOGS_START ===(.*?)=== CAPTURED_LOGS_END ===/s);
      
      if (logMatch) {
        try {
          const capturedData = JSON.parse(logMatch[1].trim());
          
          console.log('✅ Successfully captured console output!');
          console.log(`📅 Timestamp: ${capturedData.timestamp}`);
          console.log(`🌐 URL: ${capturedData.url}`);
          console.log(`📝 Total logs: ${capturedData.logs.length}`);
          console.log(`❌ Errors: ${capturedData.errors.length}`);
          console.log(`⚠️  Warnings: ${capturedData.warnings.length}\n`);
          
          if (capturedData.errors.length > 0) {
            console.log('🔴 ERRORS:');
            capturedData.errors.forEach((error, i) => {
              console.log(`${i + 1}. ${error.args.join(' ')}`);
            });
            console.log('');
          }
          
          if (capturedData.warnings.length > 0) {
            console.log('⚠️  WARNINGS:');
            capturedData.warnings.forEach((warning, i) => {
              console.log(`${i + 1}. ${warning.args.join(' ')}`);
            });
            console.log('');
          }
          
          console.log('📋 ALL CONSOLE MESSAGES:');
          capturedData.logs.forEach((log, i) => {
            const icon = log.type === 'error' ? '❌' : 
                        log.type === 'warn' ? '⚠️' : 
                        log.type === 'info' ? 'ℹ️' : '📝';
            console.log(`${icon} [${log.type.toUpperCase()}] ${log.args.join(' ')}`);
          });
          
        } catch (parseError) {
          console.error('Failed to parse captured logs:', parseError);
          console.log('Raw captured content:');
          console.log(logMatch[1]);
        }
      } else {
        console.log('❌ Could not find captured logs in output');
        console.log('\n🔍 Raw stderr (first 1000 chars):');
        console.log(stderr.substring(0, 1000));
        
        if (stderr.length > 1000) {
          console.log('\n... (truncated)');
        }
      }

      resolve({ stdout, stderr });
    });

    chromium.on('error', (error) => {
      console.error('❌ Error launching chromium:', error);
      reject(error);
    });

    // Timeout after 15 seconds
    setTimeout(() => {
      chromium.kill('SIGTERM');
      reject(new Error('Timeout after 15 seconds'));
    }, 15000);
  });
}

async function main() {
  try {
    await captureConsoleLogs();
  } catch (error) {
    console.error('❌ Error:', error.message);
  }
}

if (require.main === module) {
  main();
}