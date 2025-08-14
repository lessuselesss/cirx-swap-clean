#\!/usr/bin/env node

const http = require('http');

console.log('🔍 Starting console check...');

function checkApp() {
  const options = {
    hostname: 'localhost',
    port: 3001,
    path: '/',
    method: 'GET'
  };

  const req = http.request(options, (res) => {
    let data = '';
    res.on('data', (chunk) => {
      data += chunk;
    });
    
    res.on('end', () => {
      console.log('✅ App responded, HTML length:', data.length);
      
      // Check for error patterns
      const hasError = data.toLowerCase().includes('error');
      const hasUndefined = data.includes('undefined');
      const hasConsole = data.includes('console');
      
      console.log('Error patterns:', { hasError, hasUndefined, hasConsole });
      
      if (hasError) {
        // Extract lines with error
        const lines = data.split('\n');
        const errorLines = lines.filter(line => line.toLowerCase().includes('error'));
        console.log('Error lines found:', errorLines.slice(0, 3));
      }
      
      // Check for specific patterns
      if (data.includes('Cannot read properties')) {
        console.log('❌ Found "Cannot read properties" error');
      }
      
      if (data.includes('ReferenceError')) {
        console.log('❌ Found ReferenceError');
      }
      
      // Look for script tags
      const scriptMatches = data.match(/<script[^>]*>/g);
      if (scriptMatches) {
        console.log('📜 Found script tags:', scriptMatches.length);
      }
    });
  });

  req.on('error', (e) => {
    console.error('❌ Request failed:', e.message);
  });

  req.end();
}

checkApp();
EOF < /dev/null
