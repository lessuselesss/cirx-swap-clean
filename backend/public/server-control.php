<?php
// Simple server control panel
// WARNING: This should only be used in development!

$action = $_GET['action'] ?? '';
$status = 'unknown';
$output = '';

// Check if server is running
exec("ps aux | grep 'php -S localhost:8080' | grep -v grep", $psOutput);
$isRunning = !empty($psOutput);

if ($action === 'start' && !$isRunning) {
    // Start server in background
    $command = 'cd ' . dirname(__DIR__) . ' && nohup php -S localhost:8080 public/index.php > server.log 2>&1 & echo $!';
    exec($command, $cmdOutput);
    $output = "Server starting... PID: " . implode("\n", $cmdOutput);
    sleep(1); // Give it a moment to start
    header("Location: server-control.php");
    exit;
} elseif ($action === 'stop' && $isRunning) {
    // Stop server
    exec("pkill -f 'php -S localhost:8080'");
    $output = "Server stopped";
    sleep(1);
    header("Location: server-control.php");
    exit;
}

$status = $isRunning ? 'running' : 'stopped';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backend Server Control</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .status.running {
            background: #d4edda;
            color: #155724;
        }
        .status.stopped {
            background: #f8d7da;
            color: #721c24;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            transition: opacity 0.3s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-start {
            background: #28a745;
        }
        .btn-stop {
            background: #dc3545;
        }
        .btn-api {
            background: #007bff;
        }
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .info {
            margin-top: 30px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 14px;
        }
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 CIRX Backend Server Control</h1>
        
        <div class="warning">
            ⚠️ Development Mode Only - Do not use in production!
        </div>
        
        <div class="status <?php echo $status; ?>">
            Status: <?php echo ucfirst($status); ?>
        </div>
        
        <?php if ($isRunning): ?>
            <a href="?action=stop" class="btn btn-stop">Stop Server</a>
            <a href="http://localhost:8080" target="_blank" class="btn btn-api">Open API</a>
            <a href="http://localhost:8080/api/v1/health" target="_blank" class="btn btn-api">Health Check</a>
            <a href="http://localhost:8080/admin" target="_blank" class="btn btn-api">Admin Panel</a>
        <?php else: ?>
            <a href="?action=start" class="btn btn-start">Start Server</a>
            <button class="btn btn-api" disabled>Open API</button>
        <?php endif; ?>
        
        <div class="info">
            <h3>Server Information</h3>
            <p><strong>Host:</strong> localhost:8080</p>
            <p><strong>API Base:</strong> http://localhost:8080/api/v1</p>
            <p><strong>Log File:</strong> backend/server.log</p>
            
            <?php if ($isRunning && !empty($psOutput)): ?>
                <h4>Process Details:</h4>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;"><?php echo htmlspecialchars($psOutput[0]); ?></pre>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>