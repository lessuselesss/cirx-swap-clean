//~~~ Marked for Refactor/Migration 2025-08-16 ~~~~

import http from 'http';

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
        const lines = data.split('\n');
        const errorLines = lines.filter(line => line.toLowerCase().includes('error'));
        console.log('Error lines found:', errorLines.slice(0, 3));
      }
      
      if (data.includes('Cannot read properties')) {
        console.log('❌ Found "Cannot read properties" error');
      }
      
      if (data.includes('ReferenceError')) {
        console.log('❌ Found ReferenceError');
      }
      
      const scriptMatches = data.match(/<script[^>]*>/g);
      if (scriptMatches) {
        console.log('📜 Found script tags:', scriptMatches.length);
      }
      
      // Log a sample of the HTML for inspection
      console.log('📄 HTML Sample (first 1000 chars):', data.substring(0, 1000));
    });
  });

  req.on('error', (e) => {
    console.error('❌ Request failed:', e.message);
  });

  req.end();
}

checkApp();
EOF < /dev/null
