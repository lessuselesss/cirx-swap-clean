<?php

namespace App\Controllers;

use App\Services\LoggerService;
use App\Models\Transaction;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

/**
 * Admin Controller
 * 
 * Provides admin dashboard interface and administrative endpoints
 */
class AdminController
{
    private LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerService::getLogger('admin_controller');
    }

    /**
     * Serve the admin dashboard HTML
     */
    public function dashboard(Request $request, Response $response): Response
    {
        $html = $this->getAdminDashboardHTML();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Admin login page
     */
    public function login(Request $request, Response $response): Response
    {
        $html = $this->getLoginPageHTML();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Handle admin login
     */
    public function authenticate(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $token = $body['admin_token'] ?? '';
        $expectedToken = $_ENV['ADMIN_TOKEN'] ?? 'admin_dev_token_2024_cirx_secure_debug_new';
        
        if ($token === $expectedToken) {
            // Set session or redirect
            $response = $response->withHeader('Location', '/admin/dashboard');
            return $response->withStatus(302);
        }
        
        // Invalid token - redirect back with error
        $response = $response->withHeader('Location', '/admin/login?error=invalid_token');
        return $response->withStatus(302);
    }

    /**
     * Get comprehensive system overview
     */
    public function getSystemOverview(Request $request, Response $response): Response
    {
        try {
            $overview = [
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s T'),
                'system' => [
                    'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                    'debug_mode' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
                    'php_version' => PHP_VERSION,
                    'server' => gethostname(),
                    'uptime' => $this->getSystemUptime()
                ],
                'database' => $this->getDatabaseStats(),
                'workers' => $this->getWorkerStats(),
                'transactions' => $this->getTransactionOverview(),
                'telegram' => $this->getTelegramStatus()
            ];

            return $this->jsonResponse($response, $overview);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to get system overview',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transaction management data
     */
    public function getTransactionManagement(Request $request, Response $response): Response
    {
        try {
            $data = [
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s T'),
                'recent_transactions' => $this->getRecentTransactions(),
                'failed_transactions' => $this->getFailedTransactions(),
                'stuck_transactions' => $this->getStuckTransactions(),
                'statistics' => $this->getDetailedTransactionStats()
            ];

            return $this->jsonResponse($response, $data);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => 'Failed to get transaction data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate the admin dashboard HTML
     */
    private function getAdminDashboardHTML(): string
    {
        $appEnv = $_ENV['APP_ENV'] ?? 'unknown';
        $serverName = gethostname();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIRX OTC Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen" x-data="adminDashboard()">
    
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">🔐 CIRX OTC Admin</h1>
                    <p class="text-sm text-gray-500">Server: {$serverName} • Environment: {$appEnv}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div :class="systemStatus === 'healthy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                             class="px-3 py-1 rounded-full text-sm font-medium">
                            <i :class="systemStatus === 'healthy' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle'"></i>
                            <span x-text="systemStatus === 'healthy' ? 'System Healthy' : 'Issues Detected'"></span>
                        </div>
                    </div>
                    <button @click="refreshData()" :disabled="loading" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="{'fa-spin': loading}"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <!-- System Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            
            <!-- Active Transactions -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-exchange-alt text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Transactions</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="stats.activeTransactions">-</p>
                    </div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Payments</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="stats.pendingPayments">-</p>
                    </div>
                </div>
            </div>

            <!-- Failed Transactions -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Failed Transactions</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="stats.failedTransactions">-</p>
                    </div>
                </div>
            </div>

            <!-- Completed Today -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Completed Today</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="stats.completedToday">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8">
                    <button @click="activeTab = 'overview'" 
                            :class="activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-6 border-b-2 font-medium text-sm">
                        <i class="fas fa-tachometer-alt mr-2"></i>Overview
                    </button>
                    <button @click="activeTab = 'transactions'" 
                            :class="activeTab === 'transactions' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-6 border-b-2 font-medium text-sm">
                        <i class="fas fa-list mr-2"></i>Transactions
                    </button>
                    <button @click="activeTab = 'workers'" 
                            :class="activeTab === 'workers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-6 border-b-2 font-medium text-sm">
                        <i class="fas fa-cogs mr-2"></i>Workers
                    </button>
                    <button @click="activeTab = 'tools'" 
                            :class="activeTab === 'tools' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="py-4 px-6 border-b-2 font-medium text-sm">
                        <i class="fas fa-tools mr-2"></i>Admin Tools
                    </button>
                </nav>
            </div>

            <div class="p-6">
                <!-- Overview Tab -->
                <div x-show="activeTab === 'overview'" class="space-y-6">
                    
                    <!-- System Status -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">System Status</h3>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Environment</span>
                                    <span class="text-sm text-gray-900" x-text="overview.system?.environment || '-'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">PHP Version</span>
                                    <span class="text-sm text-gray-900" x-text="overview.system?.php_version || '-'"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Last Updated</span>
                                    <span class="text-sm text-gray-900" x-text="formatTimestamp(overview.timestamp)"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Worker Status</h3>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Payment Verification</span>
                                    <span class="text-sm text-green-600">✅ Active</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">CIRX Transfers</span>
                                    <span class="text-sm text-green-600">✅ Active</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Telegram Alerts</span>
                                    <span class="text-sm text-green-600" x-text="overview.telegram?.status || '⚠️ Unknown'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Activity</h3>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="text-sm text-gray-600" x-text="overview.workers?.recommendation || 'Loading...'"></div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Tab -->
                <div x-show="activeTab === 'transactions'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Transaction Management</h3>
                        <button @click="refreshTransactions()" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-sync-alt mr-1" :class="{'fa-spin': loadingTransactions}"></i>
                            Refresh
                        </button>
                    </div>

                    <!-- Transaction Statistics -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600" x-text="transactionData.statistics?.total || 0"></div>
                            <div class="text-sm text-gray-500">Total Transactions</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600" x-text="transactionData.statistics?.completed || 0"></div>
                            <div class="text-sm text-gray-500">Completed</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600" x-text="transactionData.statistics?.pending || 0"></div>
                            <div class="text-sm text-gray-500">Pending</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600" x-text="transactionData.statistics?.failed || 0"></div>
                            <div class="text-sm text-gray-500">Failed</div>
                        </div>
                    </div>

                    <!-- Recent Transactions Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="tx in transactionData.recent_transactions || []" :key="tx.id">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="tx.id.substring(0, 8)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getStatusColor(tx.swap_status)" class="px-2 py-1 text-xs font-medium rounded-full" x-text="tx.swap_status"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="tx.amount_paid + ' ' + tx.payment_token"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatTimestamp(tx.created_at)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button @click="viewTransaction(tx.id)" class="text-blue-600 hover:text-blue-900">View</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Workers Tab -->
                <div x-show="activeTab === 'workers'" class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Worker Management</h3>
                        <div class="space-x-2">
                            <button @click="processWorkers()" :disabled="processingWorkers"
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 disabled:opacity-50">
                                <i class="fas fa-play mr-1" :class="{'fa-spin': processingWorkers}"></i>
                                Process Now
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Payment Verification Worker -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-credit-card text-blue-600 mr-2"></i>Payment Verification
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Pending Verification</span>
                                    <span class="text-sm text-gray-900" x-text="overview.workers?.payment_verification?.pending_verification || 0"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Pending Retries</span>
                                    <span class="text-sm text-gray-900" x-text="overview.workers?.payment_verification?.pending_retries || 0"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Failed Verification</span>
                                    <span class="text-sm text-gray-900" x-text="overview.workers?.payment_verification?.failed_verification || 0"></span>
                                </div>
                            </div>
                        </div>

                        <!-- CIRX Transfer Worker -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-medium text-gray-900 mb-4">
                                <i class="fas fa-coins text-green-600 mr-2"></i>CIRX Transfers
                            </h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Ready for Transfer</span>
                                    <span class="text-sm text-gray-900" x-text="overview.workers?.cirx_transfers?.ready_for_transfer || 0"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Transfer Pending</span>
                                    <span class="text-sm text-gray-900" x-text="overview.workers?.cirx_transfers?.transfer_pending || 0"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-500">Failed Transfers</span>
                                    <span class="text-sm text-gray-900" x-text="overview.workers?.cirx_transfers?.failed_transfers || 0"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Tools Tab -->
                <div x-show="activeTab === 'tools'" class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900">Administrative Tools</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Manual Retry -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                            <h4 class="text-lg font-medium text-red-900 mb-2">
                                <i class="fas fa-redo text-red-600 mr-2"></i>Manual Retry
                            </h4>
                            <p class="text-sm text-red-700 mb-4">Reset failed transactions and retry processing. Use with caution.</p>
                            <button @click="manualRetry()" :disabled="retrying"
                                    class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 disabled:opacity-50">
                                <i class="fas fa-exclamation-triangle mr-1" :class="{'fa-spin': retrying}"></i>
                                Manual Retry
                            </button>
                        </div>

                        <!-- System Information -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h4 class="text-lg font-medium text-blue-900 mb-2">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>System Information
                            </h4>
                            <div class="text-sm text-blue-700 space-y-1">
                                <div>Environment: <span class="font-medium" x-text="overview.system?.environment"></span></div>
                                <div>Debug Mode: <span class="font-medium" x-text="overview.system?.debug_mode ? 'Enabled' : 'Disabled'"></span></div>
                                <div>Server: <span class="font-medium" x-text="overview.system?.server"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Log -->
                    <div>
                        <h4 class="text-lg font-medium text-gray-900 mb-4">Activity Log</h4>
                        <div class="bg-gray-50 rounded-lg p-4 max-h-96 overflow-y-auto">
                            <template x-for="(log, index) in activityLog" :key="index">
                                <div class="text-sm py-2 border-b border-gray-200 last:border-b-0">
                                    <span class="text-gray-500" x-text="log.timestamp"></span>
                                    <span class="ml-2" x-text="log.message"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function adminDashboard() {
            return {
                loading: false,
                loadingTransactions: false,
                processingWorkers: false,
                retrying: false,
                activeTab: 'overview',
                systemStatus: 'healthy',
                overview: {},
                transactionData: {},
                stats: {
                    activeTransactions: 0,
                    pendingPayments: 0,
                    failedTransactions: 0,
                    completedToday: 0
                },
                activityLog: [],

                async init() {
                    await this.refreshData();
                    // Auto-refresh every 30 seconds
                    setInterval(() => this.refreshData(), 30000);
                },

                async refreshData() {
                    this.loading = true;
                    try {
                        const response = await fetch('/admin/api/overview');
                        this.overview = await response.json();
                        this.updateStats();
                        this.addToLog('System data refreshed');
                    } catch (error) {
                        console.error('Failed to refresh data:', error);
                        this.systemStatus = 'error';
                        this.addToLog('Failed to refresh system data: ' + error.message);
                    } finally {
                        this.loading = false;
                    }
                },

                async refreshTransactions() {
                    this.loadingTransactions = true;
                    try {
                        const response = await fetch('/admin/api/transactions');
                        this.transactionData = await response.json();
                        this.addToLog('Transaction data refreshed');
                    } catch (error) {
                        console.error('Failed to refresh transactions:', error);
                        this.addToLog('Failed to refresh transaction data: ' + error.message);
                    } finally {
                        this.loadingTransactions = false;
                    }
                },

                async processWorkers() {
                    this.processingWorkers = true;
                    try {
                        const response = await fetch('/api/v1/workers/process', { method: 'POST' });
                        const result = await response.json();
                        this.addToLog('Workers processed: ' + (result.payment_verification?.processed || 0) + ' payments, ' + (result.cirx_transfers?.processed || 0) + ' transfers');
                        await this.refreshData();
                    } catch (error) {
                        console.error('Failed to process workers:', error);
                        this.addToLog('Failed to process workers: ' + error.message);
                    } finally {
                        this.processingWorkers = false;
                    }
                },

                async manualRetry() {
                    if (!confirm('Are you sure you want to retry all failed transactions? This will reset their status and attempt processing again.')) {
                        return;
                    }

                    this.retrying = true;
                    try {
                        const adminToken = 'admin_dev_token_2024_cirx_secure_debug_new'; // TODO: Get from auth
                        const response = await fetch('/api/v1/workers/retry?admin_token=' + adminToken, { method: 'POST' });
                        const result = await response.json();
                        this.addToLog('Manual retry completed: ' + result.reset_count + ' transactions reset');
                        await this.refreshData();
                    } catch (error) {
                        console.error('Failed to perform manual retry:', error);
                        this.addToLog('Failed to perform manual retry: ' + error.message);
                    } finally {
                        this.retrying = false;
                    }
                },

                updateStats() {
                    if (this.overview.workers) {
                        const payment = this.overview.workers.payment_verification || {};
                        const cirx = this.overview.workers.cirx_transfers || {};
                        
                        this.stats.pendingPayments = (payment.pending_verification || 0) + (payment.pending_retries || 0);
                        this.stats.failedTransactions = (payment.failed_verification || 0) + (cirx.failed_transfers || 0);
                        this.stats.activeTransactions = (cirx.transfer_pending || 0) + (cirx.transfer_initiated || 0);
                        this.stats.completedToday = cirx.completed || 0;
                    }
                },

                getStatusColor(status) {
                    const colors = {
                        'completed': 'bg-green-100 text-green-800',
                        'payment_verified': 'bg-blue-100 text-blue-800',
                        'pending_payment_verification': 'bg-yellow-100 text-yellow-800',
                        'failed_payment_verification': 'bg-red-100 text-red-800',
                        'failed_cirx_transfer': 'bg-red-100 text-red-800',
                        'transfer_pending': 'bg-yellow-100 text-yellow-800'
                    };
                    return colors[status] || 'bg-gray-100 text-gray-800';
                },

                formatTimestamp(timestamp) {
                    if (!timestamp) return '-';
                    return new Date(timestamp).toLocaleString();
                },

                viewTransaction(id) {
                    // TODO: Implement transaction detail view
                    alert('Viewing transaction: ' + id);
                },

                addToLog(message) {
                    this.activityLog.unshift({
                        timestamp: new Date().toLocaleTimeString(),
                        message: message
                    });
                    // Keep only last 50 entries
                    if (this.activityLog.length > 50) {
                        this.activityLog = this.activityLog.slice(0, 50);
                    }
                }
            }
        }
    </script>
</body>
</html>
HTML;
    }

    /**
     * Generate the login page HTML
     */
    private function getLoginPageHTML(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIRX OTC Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-blue-100">
                <i class="fas fa-shield-alt text-blue-600 text-xl"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                CIRX OTC Admin
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Enter admin token to access dashboard
            </p>
        </div>
        <form class="mt-8 space-y-6" action="/admin/authenticate" method="POST">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="admin_token" class="sr-only">Admin Token</label>
                    <input id="admin_token" name="admin_token" type="password" required 
                           class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Admin Token">
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-lock text-blue-500 group-hover:text-blue-400"></i>
                    </span>
                    Sign in
                </button>
            </div>
        </form>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Helper methods for data gathering
     */
    private function getDatabaseStats(): array
    {
        try {
            $totalTransactions = Transaction::count();
            
            return [
                'status' => 'connected',
                'total_transactions' => $totalTransactions,
                'connection' => 'SQLite'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    private function getWorkerStats(): array
    {
        try {
            // Get stats directly from worker classes instead of HTTP
            $paymentWorker = new \App\Workers\PaymentVerificationWorker();
            $cirxWorker = new \App\Workers\CirxTransferWorker();
            
            return [
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'payment_verification' => $paymentWorker->getStatistics(),
                'cirx_transfers' => $cirxWorker->getStatistics()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getTransactionOverview(): array
    {
        try {
            $stats = [
                'total' => Transaction::count(),
                'completed' => Transaction::where('swap_status', 'completed')->count(),
                'pending' => Transaction::where('swap_status', 'not like', '%failed%')->where('swap_status', '!=', 'completed')->count(),
                'failed' => Transaction::where('swap_status', 'like', '%failed%')->count()
            ];
            
            return $stats;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getTelegramStatus(): array
    {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? null;
        
        if (empty($botToken) || empty($chatId)) {
            return ['status' => '⚠️ Not Configured'];
        }
        
        return ['status' => '✅ Configured'];
    }

    private function getSystemUptime(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return "Load: " . round($load[0], 2);
        }
        return 'Unknown';
    }

    private function getRecentTransactions(int $limit = 10): array
    {
        try {
            return Transaction::orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getFailedTransactions(): array
    {
        try {
            return Transaction::where('swap_status', 'like', '%failed%')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getStuckTransactions(): array
    {
        try {
            return Transaction::where('created_at', '<', Carbon::now()->subHour())
                ->whereNotIn('swap_status', ['completed', 'failed_payment_verification', 'failed_cirx_transfer'])
                ->orderBy('created_at', 'asc')
                ->limit(20)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDetailedTransactionStats(): array
    {
        try {
            $stats = [
                'total' => Transaction::count(),
                'completed' => Transaction::where('swap_status', 'completed')->count(),
                'failed' => Transaction::where('swap_status', 'like', '%failed%')->count(),
                'pending' => Transaction::where('swap_status', 'not like', '%failed%')
                    ->where('swap_status', '!=', 'completed')->count(),
                'today' => Transaction::whereDate('created_at', Carbon::today())->count(),
                'week' => Transaction::where('created_at', '>=', Carbon::now()->subWeek())->count()
            ];
            
            return $stats;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Helper method to format JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}