<?php

declare(strict_types=1);

namespace Whatsdiff\Services;

/**
 * Service for loading GitHub authentication tokens.
 *
 * Loads GitHub OAuth tokens from:
 * 1. Composer's auth.json files (local and global)
 * 2. GITHUB_TOKEN environment variable
 * 3. COMPOSER_AUTH environment variable
 */
class GithubAuthService
{
    private ?string $cachedToken = null;
    private bool $tokenLoaded = false;

    /**
     * Get GitHub OAuth token from available sources.
     *
     * Priority order:
     * 1. GITHUB_TOKEN environment variable
     * 2. Local auth.json (current directory)
     * 3. Global auth.json (~/.composer/auth.json)
     * 4. COMPOSER_AUTH environment variable
     *
     * @return string|null GitHub OAuth token or null if not found
     */
    public function getToken(): ?string
    {
        if ($this->tokenLoaded) {
            return $this->cachedToken;
        }

        // Priority 1: GITHUB_TOKEN environment variable
        $envToken = getenv('GITHUB_TOKEN');
        if ($envToken !== false && !empty($envToken)) {
            $this->cachedToken = $envToken;
            $this->tokenLoaded = true;
            return $this->cachedToken;
        }

        // Priority 2 & 3: Load from auth.json files
        $token = $this->loadTokenFromAuthJson();
        if ($token !== null) {
            $this->cachedToken = $token;
            $this->tokenLoaded = true;
            return $this->cachedToken;
        }

        // Priority 4: COMPOSER_AUTH environment variable
        $composerAuth = getenv('COMPOSER_AUTH');
        if ($composerAuth !== false && !empty($composerAuth)) {
            $authData = json_decode($composerAuth, true);
            if (is_array($authData) && isset($authData['github-oauth']['github.com'])) {
                $this->cachedToken = $authData['github-oauth']['github.com'];
                $this->tokenLoaded = true;
                return $this->cachedToken;
            }
        }

        $this->tokenLoaded = true;
        return null;
    }

    /**
     * Check if a GitHub token is available.
     *
     * @return bool True if a token is available
     */
    public function hasToken(): bool
    {
        return $this->getToken() !== null;
    }

    /**
     * Load GitHub OAuth token from auth.json files.
     * Checks both local (project) and global (home directory) auth.json files.
     * Local auth.json takes precedence over global.
     *
     * @return string|null GitHub token or null if not found
     */
    private function loadTokenFromAuthJson(): ?string
    {
        $currentDir = getcwd() ?: '';
        $localAuthPath = $currentDir . DIRECTORY_SEPARATOR . 'auth.json';

        $HOME = getenv('HOME') ?: getenv('USERPROFILE');
        $globalAuthPath = $HOME . DIRECTORY_SEPARATOR . '.composer/auth.json';

        // Check local auth.json first (higher priority)
        if (file_exists($localAuthPath)) {
            $token = $this->extractTokenFromFile($localAuthPath);
            if ($token !== null) {
                return $token;
            }
        }

        // Check global auth.json
        if (file_exists($globalAuthPath)) {
            $token = $this->extractTokenFromFile($globalAuthPath);
            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Extract GitHub OAuth token from an auth.json file.
     *
     * @param string $filePath Path to the auth.json file
     * @return string|null GitHub token or null if not found
     */
    private function extractTokenFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $authData = json_decode($content, true);
        if (!is_array($authData)) {
            return null;
        }

        if (isset($authData['github-oauth']['github.com'])) {
            $token = $authData['github-oauth']['github.com'];
            if (is_string($token) && !empty($token)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Clear the cached token (useful for testing).
     */
    public function clearCache(): void
    {
        $this->cachedToken = null;
        $this->tokenLoaded = false;
    }
}
