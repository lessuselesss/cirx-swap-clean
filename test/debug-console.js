// Simple script to check console logs from the Nuxt app
const puppeteer = require('puppeteer');

(async () => {
  console.log('🚀 Starting browser debug session...');
  
  try {
    const browser = await puppeteer.launch({
      headless: false,
      devtools: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    
    // Capture console logs
    page.on('console', (msg) => {
      const type = msg.type();
      const text = msg.text();
      console.log(`🔍 [${type.toUpperCase()}] ${text}`);
    });
    
    // Capture errors
    page.on('error', (error) => {
      console.error('❌ PAGE ERROR:', error.message);
    });
    
    page.on('pageerror', (error) => {
      console.error('❌ PAGE ERROR:', error.message);
    });
    
    // Navigate to the app
    console.log('📱 Navigating to http://localhost:3001...');
    await page.goto('http://localhost:3001', { 
      waitUntil: 'networkidle2',
      timeout: 30000 
    });
    
    console.log('✅ Page loaded, watching for console messages...');
    console.log('Press Ctrl+C to exit');
    
    // Keep the script running
    await new Promise(() => {});
    
  } catch (error) {
    console.error('❌ Debug failed:', error.message);
    process.exit(1);
  }
})();