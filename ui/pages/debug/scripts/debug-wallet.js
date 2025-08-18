// Debug script to test wallet balance fetching
// Run this in browser console to see what's happening
// ~~~ File Marked for Refactor/Migration 2025-08-16 ~~~~
console.log('🔍 Wallet Debug Test');

// Check if Solana Web3.js is loaded
if (typeof window !== 'undefined') {
  // Test if phantom wallet is available
  console.log('🔗 Phantom available:', !!window.solana?.isPhantom);
  console.log('🔗 Phantom connected:', !!window.solana?.isConnected);
  
  if (window.solana?.isConnected && window.solana.publicKey) {
    console.log('📍 Phantom address:', window.solana.publicKey.toString());
    
    // Test live balance fetching
    const testLiveBalance = async () => {
      try {
        console.log('💰 Testing live SOL balance fetch...');
        
        // Import Solana Web3.js dynamically
        const { Connection, PublicKey, LAMPORTS_PER_SOL } = await import('@solana/web3.js');
        
        // Create connection
        const connection = new Connection('https://api.mainnet-beta.solana.com');
        const publicKey = new PublicKey(window.solana.publicKey.toString());
        
        // Get balance
        const balanceInLamports = await connection.getBalance(publicKey);
        const balanceInSol = balanceInLamports / LAMPORTS_PER_SOL;
        
        console.log('✅ Live SOL balance:', balanceInSol.toFixed(4));
        console.log('💵 Balance in lamports:', balanceInLamports);
        
        return balanceInSol.toFixed(4);
      } catch (error) {
        console.error('❌ Balance fetch failed:', error);
        return null;
      }
    };
    
    // Run the test
    testLiveBalance();
  } else {
    console.log('⚠️ Phantom not connected or no public key');
  }
} else {
  console.log('❌ Not running in browser');
}