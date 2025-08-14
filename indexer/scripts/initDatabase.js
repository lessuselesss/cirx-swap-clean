// Database initialization script
import Database from 'better-sqlite3';
import { config } from '../config.js';

export function initDatabase() {
  const db = new Database(config.database.path);

  // Create swaps table for both liquid and OTC swaps
  db.exec(`
    CREATE TABLE IF NOT EXISTS ${config.database.tables.swaps} (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      tx_hash TEXT UNIQUE NOT NULL,
      block_number INTEGER NOT NULL,
      block_timestamp INTEGER NOT NULL,
      user_address TEXT NOT NULL,
      input_token TEXT NOT NULL,
      input_amount TEXT NOT NULL,
      cirx_amount TEXT NOT NULL,
      swap_type TEXT NOT NULL, -- 'liquid' or 'otc'
      discount_bps INTEGER DEFAULT 0,
      fee_amount TEXT,
      gas_used INTEGER,
      gas_price TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_address),
      INDEX(block_number),
      INDEX(swap_type)
    )
  `);

  // Create vesting positions table
  db.exec(`
    CREATE TABLE IF NOT EXISTS ${config.database.tables.vestingPositions} (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      tx_hash TEXT UNIQUE NOT NULL,
      block_number INTEGER NOT NULL,
      block_timestamp INTEGER NOT NULL,
      user_address TEXT NOT NULL,
      total_amount TEXT NOT NULL,
      start_time INTEGER NOT NULL,
      end_time INTEGER NOT NULL, -- start_time + 6 months
      status TEXT DEFAULT 'active', -- 'active', 'completed'
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_address),
      INDEX(status)
    )
  `);

  // Create claims table for vesting claims
  db.exec(`
    CREATE TABLE IF NOT EXISTS ${config.database.tables.claims} (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      tx_hash TEXT UNIQUE NOT NULL,
      block_number INTEGER NOT NULL,
      block_timestamp INTEGER NOT NULL,
      user_address TEXT NOT NULL,
      claimed_amount TEXT NOT NULL,
      vesting_position_id INTEGER,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX(user_address),
      FOREIGN KEY(vesting_position_id) REFERENCES ${config.database.tables.vestingPositions}(id)
    )
  `);

  // Create metadata table for indexer state
  db.exec(`
    CREATE TABLE IF NOT EXISTS indexer_metadata (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
  `);

  // Insert initial metadata
  const insertMetadata = db.prepare(`
    INSERT OR REPLACE INTO indexer_metadata (key, value, updated_at) 
    VALUES (?, ?, CURRENT_TIMESTAMP)
  `);
  
  insertMetadata.run('last_processed_block', config.startBlock.toString());
  insertMetadata.run('indexer_status', 'initialized');

  console.log('✅ Database initialized successfully');
  console.log(`📄 Database location: ${config.database.path}`);
  
  return db;
}

// Run if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
  initDatabase();
}