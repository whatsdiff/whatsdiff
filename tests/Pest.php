<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//     return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

require_once __DIR__.'/Helpers.php';

// Clear AI-agent env vars (see Laravel\AgentDetector\AgentDetector) so the test
// suite stays deterministic even when CI itself runs inside an agent shell.
foreach ([
    'AI_AGENT',
    'CURSOR_AGENT',
    'GEMINI_CLI',
    'CODEX_SANDBOX',
    'CODEX_CI',
    'CODEX_THREAD_ID',
    'AUGMENT_AGENT',
    'OPENCODE_CLIENT',
    'OPENCODE',
    'AMP_CURRENT_THREAD_ID',
    'CLAUDECODE',
    'CLAUDE_CODE',
    'CLAUDE_CODE_IS_COWORK',
    'REPL_ID',
    'COPILOT_MODEL',
    'COPILOT_ALLOW_ALL',
    'COPILOT_GITHUB_TOKEN',
    'COPILOT_CLI',
    'ANTIGRAVITY_AGENT',
    'PI_CODING_AGENT',
    'KIRO_AGENT_PATH',
] as $agentEnvVar) {
    putenv($agentEnvVar);
    unset($_ENV[$agentEnvVar], $_SERVER[$agentEnvVar]);
}
