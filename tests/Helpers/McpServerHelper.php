<?php

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Helper class for testing MCP server via stdio transport.
 *
 * Handles communication with the MCP server using JSON-RPC protocol.
 */
class McpServerHelper
{
    /** @var resource */
    private $process;

    /** @var resource */
    private $stdin;

    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    private int $requestId = 1;
    private bool $initialized = false;

    public function __construct()
    {
        $serverPath = __DIR__ . '/../../bin/whatsdiff-mcp';

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $this->process = proc_open(
            ['php', $serverPath],
            $descriptorspec,
            $pipes
        );

        if (!is_resource($this->process)) {
            throw new \RuntimeException('Failed to start MCP server process');
        }

        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        // Make stdout non-blocking
        stream_set_blocking($this->stdout, false);

        // Give the server time to initialize
        usleep(500000); // 500ms
    }

    /**
     * Initialize the MCP server session.
     */
    public function initialize(): array
    {
        $response = $this->sendRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => new \stdClass(),
            ],
            'clientInfo' => [
                'name' => 'whatsdiff-test-client',
                'version' => '1.0.0',
            ],
        ]);

        // Send initialized notification to complete the handshake
        $this->sendNotification('notifications/initialized', []);

        $this->initialized = true;

        return $response;
    }

    /**
     * Send a JSON-RPC notification (no response expected).
     *
     * @param string $method JSON-RPC method name
     * @param array<string, mixed> $params Method parameters
     */
    private function sendNotification(string $method, array $params): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];

        $notificationJson = json_encode($notification, JSON_UNESCAPED_SLASHES) . "\n";

        // Write notification to server stdin
        fwrite($this->stdin, $notificationJson);
        fflush($this->stdin);

        // Give the server a moment to process the notification
        usleep(100000); // 100ms
    }

    /**
     * List all available tools from the MCP server.
     */
    public function listTools(): array
    {
        $this->ensureInitialized();

        return $this->sendRequest('tools/list', []);
    }

    /**
     * Call a specific tool with given arguments.
     *
     * @param string $toolName Tool name to call
     * @param array<string, mixed> $arguments Tool arguments
     */
    public function callTool(string $toolName, array $arguments): array
    {
        $this->ensureInitialized();

        return $this->sendRequest('tools/call', [
            'name' => $toolName,
            'arguments' => $arguments,
        ]);
    }

    /**
     * Send a JSON-RPC request to the server and wait for response.
     *
     * @param string $method JSON-RPC method name
     * @param array<string, mixed> $params Method parameters
     * @return array<string, mixed> Response data
     * @throws \RuntimeException If server communication fails
     */
    public function sendRequest(string $method, array $params): array
    {
        $requestId = $this->requestId++;

        $request = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $params,
        ];

        $requestJson = json_encode($request, JSON_UNESCAPED_SLASHES) . "\n";

        // Write request to server stdin
        fwrite($this->stdin, $requestJson);
        fflush($this->stdin);

        // Wait for and read response
        $output = $this->readResponse($requestId);

        return $output;
    }

    /**
     * Read a JSON-RPC response from the server stdout.
     *
     * @param int $expectedId Expected request ID
     * @return array<string, mixed> Response data
     * @throws \RuntimeException If response cannot be read or parsed
     */
    private function readResponse(int $expectedId): array
    {
        $maxAttempts = 100; // 10 seconds total
        $attempts = 0;
        $accumulatedOutput = '';

        while ($attempts < $maxAttempts) {
            // Check if process is still running
            $status = proc_get_status($this->process);
            if (!$status['running']) {
                $errorOutput = stream_get_contents($this->stderr);
                throw new \RuntimeException(
                    "MCP server process terminated unexpectedly. Error output: {$errorOutput}"
                );
            }

            // Read available output from stdout
            $output = fread($this->stdout, 8192);

            if ($output !== false && !empty($output)) {
                $accumulatedOutput .= $output;

                // Try to parse complete JSON lines
                $lines = explode("\n", $accumulatedOutput);

                // Keep the last incomplete line for next iteration
                $accumulatedOutput = array_pop($lines);

                foreach ($lines as $line) {
                    $line = trim($line);

                    if (empty($line)) {
                        continue;
                    }

                    $response = json_decode($line, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // Skip invalid JSON, might be a log line
                        continue;
                    }

                    // Check if this is a JSON-RPC response for our request
                    if (!isset($response['jsonrpc']) || $response['jsonrpc'] !== '2.0') {
                        continue;
                    }

                    // Check if this is the response we're waiting for
                    if (isset($response['id']) && $response['id'] === $expectedId) {
                        // Check for JSON-RPC error
                        if (isset($response['error'])) {
                            throw new \RuntimeException(
                                'MCP server returned error: ' . json_encode($response['error'])
                            );
                        }

                        return $response;
                    }
                }
            }

            usleep(100000); // Wait 100ms before next attempt
            $attempts++;
        }

        throw new \RuntimeException('Timeout waiting for MCP server response (ID: ' . $expectedId . ')');
    }

    /**
     * Ensure the server is initialized before sending requests.
     */
    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }
    }

    /**
     * Stop the MCP server process.
     */
    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return; // Already stopped
        }

        $status = proc_get_status($this->process);

        if ($status['running']) {
            // Close stdin to signal the process to terminate
            if (is_resource($this->stdin)) {
                @fclose($this->stdin);
            }

            // Give it a moment to shut down gracefully
            usleep(100000); // 100ms

            // Force terminate if still running
            if (is_resource($this->process)) {
                $status = proc_get_status($this->process);
                if ($status['running']) {
                    proc_terminate($this->process, 15); // SIGTERM
                }
            }
        }

        // Close remaining streams
        if (is_resource($this->stdout)) {
            @fclose($this->stdout);
        }

        if (is_resource($this->stderr)) {
            @fclose($this->stderr);
        }

        // Close the process
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
    }

    /**
     * Clean up resources.
     */
    public function __destruct()
    {
        $this->stop();
    }
}
