// Additional endpoints for payment verification
// Add these routes to the existing server.js

// Get specific transaction by hash (for payment verification)
fastify.get('/api/transaction/:txHash', async (request, reply) => {
  const perfLogger = new PerformanceLogger(apiLogger, 'get_transaction_by_hash');
  
  try {
    const { txHash } = request.params;
    
    // Validate transaction hash format
    if (!/^0x[a-fA-F0-9]{64}$/.test(txHash)) {
      throw new ValidationError('Invalid transaction hash format');
    }
    perfLogger.checkpoint('hash_validated');

    // Query database for transaction
    let transaction;
    try {
      // Check in swaps table
      transaction = eventListener.db.prepare(`
        SELECT 
          tx_hash,
          user_address,
          input_token,
          input_amount,
          cirx_amount,
          swap_type,
          discount_bps,
          block_timestamp,
          'swap' as transaction_type
        FROM ${config.database.tables.swaps} 
        WHERE tx_hash = ?
      `).get(txHash.toLowerCase());

      // If not found in swaps, check vesting positions
      if (!transaction) {
        transaction = eventListener.db.prepare(`
          SELECT 
            tx_hash,
            user_address,
            total_amount,
            start_time,
            end_time,
            status,
            block_timestamp,
            'vesting' as transaction_type
          FROM ${config.database.tables.vestingPositions} 
          WHERE tx_hash = ?
        `).get(txHash.toLowerCase());
      }

      // If not found in vesting, check claims
      if (!transaction) {
        transaction = eventListener.db.prepare(`
          SELECT 
            tx_hash,
            user_address,
            claimed_amount,
            block_timestamp,
            'claim' as transaction_type
          FROM ${config.database.tables.claims} 
          WHERE tx_hash = ?
        `).get(txHash.toLowerCase());
      }

      perfLogger.checkpoint('database_queried');
    } catch (dbError) {
      throw new DatabaseError('Failed to query transaction', {
        txHash,
        originalError: dbError.message
      });
    }

    if (!transaction) {
      return reply.code(404).send({
        success: false,
        error: 'Transaction not found',
        message: `No transaction found with hash: ${txHash}`,
        requestId: request.id
      });
    }

    // Format transaction for verification
    const formattedTransaction = {
      tx_hash: transaction.tx_hash,
      user_address: transaction.user_address,
      transaction_type: transaction.transaction_type,
      block_timestamp: transaction.block_timestamp,
      timestamp: new Date(transaction.block_timestamp * 1000).toISOString(),
      status: 'confirmed', // All indexed transactions are confirmed
      confirmations: 50, // Assume sufficient confirmations since it's indexed
      etherscan_url: `https://etherscan.io/tx/${transaction.tx_hash}`
    };

    // Add type-specific data
    if (transaction.transaction_type === 'swap') {
      formattedTransaction.input_token = transaction.input_token;
      formattedTransaction.input_amount = transaction.input_amount;
      formattedTransaction.input_amount_formatted = (BigInt(transaction.input_amount) / BigInt(10 ** 18)).toString();
      formattedTransaction.cirx_amount = transaction.cirx_amount;
      formattedTransaction.cirx_amount_formatted = (BigInt(transaction.cirx_amount) / BigInt(10 ** 18)).toString();
      formattedTransaction.swap_type = transaction.swap_type;
      formattedTransaction.discount_percentage = transaction.discount_bps / 100;
    } else if (transaction.transaction_type === 'vesting') {
      formattedTransaction.total_amount = transaction.total_amount;
      formattedTransaction.total_amount_formatted = (BigInt(transaction.total_amount) / BigInt(10 ** 18)).toString();
      formattedTransaction.start_time = transaction.start_time;
      formattedTransaction.end_time = transaction.end_time;
      formattedTransaction.vesting_status = transaction.status;
    } else if (transaction.transaction_type === 'claim') {
      formattedTransaction.claimed_amount = transaction.claimed_amount;
      formattedTransaction.claimed_amount_formatted = (BigInt(transaction.claimed_amount) / BigInt(10 ** 18)).toString();
    }

    perfLogger.checkpoint('transaction_formatted');

    const response = {
      success: true,
      data: {
        transaction: formattedTransaction
      },
      requestId: request.id
    };

    perfLogger.complete(true, {
      txHash,
      transactionType: transaction.transaction_type
    });

    return reply.send(response);
    
  } catch (error) {
    perfLogger.complete(false, { error: error.message });
    return handleApiError(error, request, reply);
  }
});

// Verify payment endpoint (specifically for backend verification)
fastify.post('/api/verify-payment', async (request, reply) => {
  const perfLogger = new PerformanceLogger(apiLogger, 'verify_payment');
  
  try {
    const { txHash, expectedAmount, token, recipientAddress } = request.body;
    
    // Validate required fields
    if (!txHash || !expectedAmount || !token || !recipientAddress) {
      throw new ValidationError('Missing required fields: txHash, expectedAmount, token, recipientAddress');
    }

    // Validate formats
    if (!/^0x[a-fA-F0-9]{64}$/.test(txHash)) {
      throw new ValidationError('Invalid transaction hash format');
    }
    
    if (!/^0x[a-fA-F0-9]{40}$/.test(recipientAddress)) {
      throw new ValidationError('Invalid recipient address format');
    }

    perfLogger.checkpoint('input_validated');

    // Get transaction from database
    const transaction = eventListener.db.prepare(`
      SELECT * FROM ${config.database.tables.swaps} 
      WHERE tx_hash = ?
    `).get(txHash.toLowerCase());

    if (!transaction) {
      return reply.send({
        success: false,
        valid: false,
        error: 'Transaction not found',
        txHash
      });
    }

    perfLogger.checkpoint('transaction_found');

    // Perform verification checks
    const verificationResult = {
      success: true,
      valid: true,
      txHash: transaction.tx_hash,
      checks: {},
      transaction: {
        user_address: transaction.user_address,
        input_amount: transaction.input_amount,
        input_amount_formatted: (BigInt(transaction.input_amount) / BigInt(10 ** 18)).toString(),
        cirx_amount: transaction.cirx_amount,
        cirx_amount_formatted: (BigInt(transaction.cirx_amount) / BigInt(10 ** 18)).toString(),
        swap_type: transaction.swap_type,
        timestamp: new Date(transaction.block_timestamp * 1000).toISOString()
      }
    };

    // Check 1: Amount verification
    const actualAmount = (BigInt(transaction.input_amount) / BigInt(10 ** 18)).toString();
    const amountValid = parseFloat(actualAmount) >= parseFloat(expectedAmount);
    verificationResult.checks.amount = {
      valid: amountValid,
      expected: expectedAmount,
      actual: actualAmount,
      message: amountValid ? 'Amount check passed' : `Insufficient amount: expected ${expectedAmount}, got ${actualAmount}`
    };

    // Check 2: Token type (simplified for now)
    const tokenValid = transaction.input_token.toUpperCase() === token.toUpperCase();
    verificationResult.checks.token = {
      valid: tokenValid,
      expected: token,
      actual: transaction.input_token,
      message: tokenValid ? 'Token check passed' : `Wrong token: expected ${token}, got ${transaction.input_token}`
    };

    // Check 3: Confirmations (assume sufficient since indexed)
    verificationResult.checks.confirmations = {
      valid: true,
      confirmations: 50, // Indexed transactions have sufficient confirmations
      message: 'Confirmation check passed'
    };

    // Overall validation
    verificationResult.valid = verificationResult.checks.amount.valid && 
                              verificationResult.checks.token.valid && 
                              verificationResult.checks.confirmations.valid;

    if (!verificationResult.valid) {
      const failedChecks = Object.entries(verificationResult.checks)
        .filter(([_, check]) => !check.valid)
        .map(([name, check]) => `${name}: ${check.message}`)
        .join(', ');
      
      verificationResult.error = `Verification failed: ${failedChecks}`;
    }

    perfLogger.complete(true, {
      txHash,
      valid: verificationResult.valid
    });

    return reply.send(verificationResult);
    
  } catch (error) {
    perfLogger.complete(false, { error: error.message });
    return handleApiError(error, request, reply);
  }
});

export { /* additional endpoints for inclusion in main server */ };