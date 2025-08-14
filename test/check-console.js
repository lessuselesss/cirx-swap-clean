#!/usr/bin/env node
/**
 * Console log checker using puppeteer with Nix compatibility
 */

const { spawn } = require('child_process');

// Function to launch chromium with nix and capture console
async function captureConsole() {
  console.log('🔍 Checking console output from http://localhost:3000...\n');

  const chromiumArgs = [
    '--headless',
    '--disable-gpu', 
    '--disable-dev-shm-usage',
    '--no-sandbox',
    '--enable-logging',
    '--log-level=0',
    '--dump-dom',
    '--virtual-time-budget=5000',  // Wait 5 seconds for JS to execute
    'http://localhost:3000'
  ];

  return new Promise((resolve, reject) => {
    const chromium = spawn('nix-shell', ['-p', 'chromium', '--run', `chromium ${chromiumArgs.join(' ')}`], {
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
      console.log('📊 Chrome Console Output:');
      console.log('=' .repeat(50));
      
      if (stderr) {
        console.log('🔍 Console Messages/Errors:');
        // Filter out system messages and focus on JS console output
        const lines = stderr.split('\n');
        const relevantLines = lines.filter(line => 
          line.includes('console') ||
          line.includes('error') ||
          line.includes('warn') ||
          line.includes('INFO') ||
          line.includes('✅') ||
          line.includes('❌') ||
          line.includes('⚠️') ||
          line.includes('🔴') ||
          line.includes('🚨')
        );
        
        if (relevantLines.length > 0) {
          relevantLines.forEach(line => console.log(line));
        } else {
          console.log('No specific console messages found in stderr');
          console.log('\nRaw stderr output:');
          console.log(stderr);
        }
      }

      if (stdout) {
        console.log('\n🌐 DOM Content (first 500 chars):');
        console.log(stdout.substring(0, 500) + '...');
      }

      resolve({ stdout, stderr });
    });

    chromium.on('error', (error) => {
      console.error('Error launching chromium:', error);
      reject(error);
    });

    // Timeout after 10 seconds
    setTimeout(() => {
      chromium.kill('SIGTERM');
      reject(new Error('Timeout after 10 seconds'));
    }, 10000);
  });
}

// Alternative: Check if we can see the debug commands in the served HTML
async function checkDebugCommands() {
  console.log('\n🔧 Checking for debug commands availability...');
  
  const { spawn } = require('child_process');
  
  return new Promise((resolve) => {
    const curl = spawn('curl', ['-s', 'http://localhost:3000/debug-console-commands.js']);
    
    let output = '';
    curl.stdout.on('data', (data) => {
      output += data.toString();
    });
    
    curl.on('close', () => {
      if (output.includes('otcDebug')) {
        console.log('✅ Debug commands are available!');
        console.log('   You can run in browser console:');
        console.log('   - otcDebug.diagnose()');
        console.log('   - otcDebug.checkOtcDropdown()');
        console.log('   - otcDebug.checkConsoleErrors()');
      } else {
        console.log('❌ Debug commands not found or not loaded');
      }
      resolve(output);
    });
  });
}

// Main execution
async function main() {
  try {
    // Check debug commands availability
    await checkDebugCommands();
    
    // Try to capture console with chromium
    await captureConsole();
    
    console.log('\n📖 To manually check console:');
    console.log('1. Open http://localhost:3000 in Chrome');
    console.log('2. Press F12 to open DevTools');
    console.log('3. Go to Console tab');
    console.log('4. Look for the messages patterns shown above');
    
  } catch (error) {
    console.error('❌ Error:', error.message);
    console.log('\n📖 Manual checking recommended:');
    console.log('Open http://localhost:3000 in Chrome and check console manually');
  }
}

if (require.main === module) {
  main();
}