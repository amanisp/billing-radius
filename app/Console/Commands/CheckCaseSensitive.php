<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\File;

class CheckCaseSensitive extends Command
{
    protected $signature = 'check:case-sensitive
                            {--paths=* : Extra paths to scan (relative to project root)}
                            {--fix : Automatically fix case-sensitivity issues}
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Scan project for common case-sensitivity mismatches (PSR-4 classes, controller references, and view calls). Can also auto-fix issues.';

    private $fixedFiles = [];
    private $fixedCount = 0;
    private $isDryRun = false;

    public function handle()
    {
        $this->info('Starting case-sensitivity scan...');

        $this->isDryRun = $this->option('dry-run');
        $shouldFix = $this->option('fix') || $this->isDryRun;

        $projectRoot = base_path();
        $appDir = $projectRoot . DIRECTORY_SEPARATOR . 'app';
        $viewDir = $projectRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

        $extraPaths = $this->option('paths') ?: [];

        // 1) Scan classes under app/ and compare expected PSR-4 path
        $this->line('Scanning PHP classes in app/ ...');
        $classIssues = $this->scanAppClasses($appDir);

        // 2) Scan routes files for controller references
        $this->line('Scanning routes for controller references ...');
        $routeIssues = $this->scanRoutesForControllers($projectRoot . DIRECTORY_SEPARATOR . 'routes');

        // 3) Scan code for view(...) calls and validate against resources/views
        $this->line('Scanning for view() / View::make() usages ...');
        $viewIssues = $this->scanViewUsages($projectRoot, $viewDir, $extraPaths);

        // Combine results
        $total = count($classIssues) + count($routeIssues) + count($viewIssues);

        if ($total === 0) {
            $this->info('✅ No obvious case-sensitivity mismatches found.');
            $this->line('Still recommended: run this on your Linux deployment or CI to be sure.');
            return 0;
        }

        $this->error("Found $total potential issues:\n");

        // Display and optionally fix issues
        if (count($classIssues)) {
            $this->warn('--- Class / Filename mismatches ---');
            foreach ($classIssues as $i => $issue) {
                $this->line(sprintf("%d) %s\n   Expected: %s\n   Found:    %s", $i+1, $issue['message'], $issue['expected'] ?? 'N/A', $issue['found'] ?? 'N/A'));

                if (isset($issue['manual_fix_suggestions'])) {
                    $this->line("   💡 Fix suggestions:");
                    foreach ($issue['manual_fix_suggestions'] as $suggestion) {
                        $this->line("      - " . $suggestion);
                    }
                }

                if ($shouldFix && isset($issue['fix_action'])) {
                    $this->fixClassIssue($issue);
                }
                $this->line('');
            }
        }

        if (count($routeIssues)) {
            $this->warn('--- Route controller reference issues ---');
            foreach ($routeIssues as $i => $issue) {
                $this->line(sprintf("%d) %s\n   Expected path: %s\n   File exists:   %s", $i+1, $issue['message'], $issue['expected'] ?? 'N/A', $issue['exists'] ? 'yes' : 'no'));
            }
        }

        if (count($viewIssues)) {
            $this->warn('--- View reference issues ---');
            foreach ($viewIssues as $i => $issue) {
                $this->line(sprintf("%d) %s\n   Expected view file: %s\n   Exists (case-exact): %s\n   Exists (case-insensitive): %s", $i+1, $issue['message'], $issue['expected_file'], $issue['exists_exact'] ? 'yes' : 'no', $issue['exists_ci'] ? 'yes' : 'no'));

                if ($shouldFix && isset($issue['fix_action'])) {
                    $this->fixViewIssue($issue);
                }
            }
        }

        if ($shouldFix) {
            $this->displayFixSummary();
        }

        $this->line('--- End of report ---');
        $this->line('Notes:');
        $this->line('- This command can REPORT potential mismatches and optionally fix them.');
        $this->line('- Some results may be false positives (complex code generation, dynamic view names, or unconventional PSR-4 mappings).');
        $this->line('- After fixing: run `composer dump-autoload` and clear caches on server.');
        $this->line('- Use --fix to automatically fix issues, or --dry-run to see what would be fixed.');

        return 0;
    }

    protected function scanAppClasses(string $appDir): array
    {
        $issues = [];
        if (!is_dir($appDir)) {
            return $issues;
        }

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appDir));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            if ($file->getExtension() !== 'php') continue;

            $path = $file->getPathname();
            $code = file_get_contents($path);
            if (!$code) continue;

            // Parse namespace and class name
            $ns = $this->parseNamespace($code);
            $class = $this->parseClassName($code);
            if (!$class) continue;

            // Build expected relative path from namespace + class using PSR-4 for App\ -> app/
            $expected = $this->expectedPathFromNamespace($ns, $class);
            $actualRel = $this->relativePathFromApp($path);

            if ($expected === null || $actualRel === null) continue;

            // Compare exact string equality
            if ($expected !== $actualRel) {
                $issue = [
                    'message' => "Case mismatch for class $ns\\$class",
                    'expected' => $expected,
                    'found' => $actualRel,
                    'file_path' => $path,
                    'namespace' => $ns,
                    'class' => $class,
                ];

                // If case-insensitive equal, it's a case-only mismatch - we can fix this
                if (strtolower($expected) === strtolower($actualRel)) {
                    $expectedFullPath = base_path($expected);
                    $issue['fix_action'] = 'rename_file';
                    $issue['target_path'] = $expectedFullPath;
                    $issue['message'] = "Case mismatch for class $ns\\$class (fixable)";
                } else {
                    // Analyze the type of mismatch for better guidance
                    $issue['message'] = "Path mismatch for class $ns\\$class (manual fix required)";
                    $issue['manual_fix_suggestions'] = $this->generateFixSuggestions($ns, $class, $expected, $actualRel, $path);
                }

                $issues[] = $issue;
            }
        }

        return $issues;
    }

    protected function scanRoutesForControllers(string $routesDir): array
    {
        $issues = [];
        if (!is_dir($routesDir)) return $issues;

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($routesDir));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            if ($file->getExtension() !== 'php') continue;

            $content = file_get_contents($file->getPathname());
            if (!$content) continue;

            // Find controller::class usages or 'Controller@method' strings
            if (preg_match_all('/([A-Za-z0-9_\\\\]+)::class/', $content, $m)) {
                foreach ($m[1] as $fqcn) {
                    $fqcn = ltrim($fqcn, '\\');
                    if (strpos($fqcn, 'App\\') === 0 || strpos($fqcn, 'App\\Http\\Controllers') === 0 || strpos($fqcn, 'App\Http\Controllers') === 0) {
                        $expectedPath = base_path('app' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, preg_replace('/^App\\\\?/', '', $fqcn)) . '.php');
                        $existsExact = file_exists($expectedPath);
                        $existsCI = $this->fileExistsCaseInsensitive(dirname($expectedPath), basename($expectedPath));

                        if (!$existsExact && $existsCI) {
                            $issues[] = [
                                'message' => "Controller reference $fqcn in routes (case mismatch)",
                                'expected' => $expectedPath,
                                'exists' => true,
                                'route_file' => $file->getPathname(),
                                'controller_class' => $fqcn,
                            ];
                        } elseif (!$existsExact) {
                            $issues[] = [
                                'message' => "Controller reference $fqcn in routes (missing file)",
                                'expected' => $expectedPath,
                                'exists' => false,
                            ];
                        }
                    }
                }
            }

            // Pattern 2: 'Controller@method' strings
            if (preg_match_all('/["\']([A-Za-z0-9_\\\\\/]+@[^"\']+)["\']/', $content, $m2)) {
                foreach ($m2[1] as $str) {
                    if (strpos($str, '@') === false) continue;
                    $parts = explode('@', $str);
                    $controller = $parts[0];

                    $fqcn = $this->resolveControllerFQCN($controller);
                    $expectedPath = base_path('app' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, preg_replace('/^App\\\\?/', '', $fqcn)) . '.php');
                    $existsExact = file_exists($expectedPath);
                    $existsCI = $this->fileExistsCaseInsensitive(dirname($expectedPath), basename($expectedPath));

                    if (!$existsExact && $existsCI) {
                        $issues[] = [
                            'message' => "Controller reference $controller in routes (case mismatch, string '@' style)",
                            'expected' => $expectedPath,
                            'exists' => true,
                        ];
                    } elseif (!$existsExact) {
                        $issues[] = [
                            'message' => "Controller reference $controller in routes (missing file, string '@' style)",
                            'expected' => $expectedPath,
                            'exists' => false,
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    protected function scanViewUsages(string $projectRoot, string $viewDir, array $extraPaths = []): array
    {
        $issues = [];

        // Build a case-sensitive map of available blade files
        $availableViews = $this->buildViewMap($viewDir);

        // Paths to scan for view(...) usages
        $scanPaths = array_merge(
            [$projectRoot . DIRECTORY_SEPARATOR . 'app', $projectRoot . DIRECTORY_SEPARATOR . 'routes'],
            array_map(function ($p) use ($projectRoot) {
                return $projectRoot . DIRECTORY_SEPARATOR . ltrim($p, DIRECTORY_SEPARATOR);
            }, $extraPaths)
        );

        foreach ($scanPaths as $sp) {
            if (!is_dir($sp)) continue;

            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sp));
            foreach ($it as $file) {
                if (!$file->isFile()) continue;
                if ($file->getExtension() !== 'php') continue;

                $content = file_get_contents($file->getPathname());
                if (!$content) continue;

                $issues = array_merge($issues, $this->findViewReferences($content, $file->getPathname(), $viewDir, $availableViews));
            }
        }

        return $issues;
    }

    protected function findViewReferences(string $content, string $filePath, string $viewDir, array $availableViews): array
    {
        $issues = [];
        $patterns = [
            '/\bview\s*\(\s*["\']([^"\']+)["\']/' => 'view()',
            '/View::make\(\s*["\']([^"\']+)["\']/' => 'View::make()',
            '/\@include\s*\(\s*["\']([^"\']+)["\']/' => '@include',
            '/\@extends\s*\(\s*["\']([^"\']+)["\']/' => '@extends',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $viewName) {
                    $expectedFile = $viewDir . DIRECTORY_SEPARATOR . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $viewName) . '.blade.php';
                    $existsExact = file_exists($expectedFile);

                    if (!$existsExact) {
                        // Check for case-insensitive match
                        $correctViewName = $this->findCorrectViewName($viewName, $availableViews);
                        $existsCI = $correctViewName !== null;

                        $issue = [
                            'message' => "$type reference '$viewName' in " . basename($filePath),
                            'expected_file' => $expectedFile,
                            'exists_exact' => $existsExact,
                            'exists_ci' => $existsCI,
                            'file_path' => $filePath,
                            'view_name' => $viewName,
                            'reference_type' => $type,
                        ];

                        if ($correctViewName) {
                            $issue['fix_action'] = 'rename_view_file';
                            $issue['correct_view_name'] = $correctViewName;
                            $issue['correct_file_path'] = $viewDir . DIRECTORY_SEPARATOR . str_replace(['.', '/'], DIRECTORY_SEPARATOR, $correctViewName) . '.blade.php';
                            $issue['message'] .= ' (fixable - case mismatch)';
                        } else {
                            $issue['message'] .= ' (missing view file)';
                        }

                        $issues[] = $issue;
                    }
                }
            }
        }

        return $issues;
    }

    protected function buildViewMap(string $viewDir): array
    {
        $views = [];
        if (!is_dir($viewDir)) return $views;

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewDir));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            if (substr($f->getFilename(), -10) !== '.blade.php') continue;

            $relativePath = substr($f->getPathname(), strlen($viewDir) + 1);
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '.', $relativePath);
            $viewName = substr($relativePath, 0, -10); // remove .blade.php

            $views[strtolower($viewName)] = [
                'actual_name' => $viewName,
                'file_path' => $f->getPathname(),
            ];
        }

        return $views;
    }

    protected function findCorrectViewName(string $searchName, array $availableViews): ?string
    {
        $searchLower = strtolower($searchName);
        return isset($availableViews[$searchLower]) ? $availableViews[$searchLower]['actual_name'] : null;
    }

    protected function fixClassIssue(array $issue): void
    {
        if ($issue['fix_action'] !== 'rename_file') return;

        $sourcePath = $issue['file_path'];
        $targetPath = $issue['target_path'];

        $this->info(($this->isDryRun ? '[DRY RUN] ' : '') . "Fixing: {$issue['message']}");
        $this->line("  Moving: " . basename($sourcePath) . " -> " . basename($targetPath));

        if (!$this->isDryRun) {
            // Ensure target directory exists
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            // Move the file
            if (File::move($sourcePath, $targetPath)) {
                $this->fixedFiles[] = $targetPath;
                $this->fixedCount++;
                $this->line("  ✅ Successfully moved file");
            } else {
                $this->error("  ❌ Failed to move file");
            }
        }
    }

    protected function fixViewIssue(array $issue): void
    {
        if ($issue['fix_action'] !== 'rename_view_file') return;

        $expectedPath = $issue['expected_file'];
        $correctPath = $issue['correct_file_path'];

        $this->info(($this->isDryRun ? '[DRY RUN] ' : '') . "Fixing: {$issue['message']}");
        $this->line("  Moving: " . basename($correctPath) . " -> " . basename($expectedPath));

        if (!$this->isDryRun) {
            // Ensure target directory exists
            $targetDir = dirname($expectedPath);
            if (!is_dir($targetDir)) {
                File::makeDirectory($targetDir, 0755, true);
            }

            // Move the file
            if (File::move($correctPath, $expectedPath)) {
                $this->fixedFiles[] = $expectedPath;
                $this->fixedCount++;
                $this->line("  ✅ Successfully moved view file");
            } else {
                $this->error("  ❌ Failed to move view file");
            }
        }
    }

    protected function displayFixSummary(): void
    {
        if ($this->isDryRun) {
            $this->info("\n--- DRY RUN SUMMARY ---");
            $this->line("Would fix {$this->fixedCount} files");
        } else {
            $this->info("\n--- FIX SUMMARY ---");
            $this->line("Fixed {$this->fixedCount} files:");
            foreach ($this->fixedFiles as $file) {
                $this->line("  ✅ " . str_replace(base_path(), '', $file));
            }

            if ($this->fixedCount > 0) {
                $this->warn("\nIMPORTANT: Run the following commands:");
                $this->line("composer dump-autoload");
                $this->line("php artisan config:clear");
                $this->line("php artisan cache:clear");
                $this->line("php artisan view:clear");
            }
        }
    }

    protected function resolveControllerFQCN(string $controller): string
    {
        $fqcn = $controller;
        if (strpos($fqcn, '\\') === false && strpos($fqcn, '/') === false) {
            $fqcn = 'App\\Http\\Controllers\\' . $controller;
        } else {
            $fqcn = str_replace('/', '\\', $controller);
            if (strpos($fqcn, 'App\\') !== 0) {
                $fqcn = 'App\\Http\\Controllers\\' . $fqcn;
            }
        }
        return $fqcn;
    }

    protected function parseNamespace(string $code): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/i', $code, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function parseClassName(string $code): ?string
    {
        // Match class, interface, trait
        if (preg_match('/class\s+([A-Za-z0-9_]+)/i', $code, $m)) return $m[1];
        if (preg_match('/interface\s+([A-Za-z0-9_]+)/i', $code, $m)) return $m[1];
        if (preg_match('/trait\s+([A-Za-z0-9_]+)/i', $code, $m)) return $m[1];
        return null;
    }

    protected function expectedPathFromNamespace(?string $namespace, string $class): ?string
    {
        if (!$namespace) return null;

        // Only handle PSR-4 App\ mapping
        if (stripos($namespace, 'App\\') !== 0 && stripos($namespace, 'App') !== 0) {
            return null;
        }

        // Remove leading App\\ or App\
        $relativeNs = preg_replace('/^App\\\\?/', '', $namespace);
        $relativeNs = trim($relativeNs, '\\');

        $parts = $relativeNs === '' ? [] : explode('\\', $relativeNs);
        $path = implode(DIRECTORY_SEPARATOR, array_merge(['app'], $parts, [$class . '.php']));
        return $path;
    }

    protected function relativePathFromApp(string $fullPath): ?string
    {
        $appPrefix = base_path('app') . DIRECTORY_SEPARATOR;
        $fullPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
        if (stripos($fullPath, $appPrefix) !== 0) return null;
        return substr($fullPath, strlen($appPrefix));
    }

    protected function generateFixSuggestions(string $namespace, string $class, string $expected, string $actual, string $filePath): array
    {
        $suggestions = [];

        // Parse expected and actual paths
        $expectedParts = explode(DIRECTORY_SEPARATOR, str_replace('.php', '', $expected));
        $actualParts = explode(DIRECTORY_SEPARATOR, str_replace('.php', '', $actual));

        // Remove 'app' from expected for comparison
        if ($expectedParts[0] === 'app') {
            array_shift($expectedParts);
        }

        $expectedNamespaceParts = explode('\\', str_replace('App\\', '', $namespace));

        // Check if it's a namespace issue
        if (count($expectedParts) !== count($actualParts)) {
            $suggestions[] = "Directory structure mismatch. Consider moving file or updating namespace.";
        }

        // Check if namespace matches directory structure
        $namespacePath = implode(DIRECTORY_SEPARATOR, $expectedNamespaceParts);
        $actualPath = implode(DIRECTORY_SEPARATOR, array_slice($actualParts, 0, -1));

        if (strtolower($namespacePath) !== strtolower($actualPath)) {
            $correctNamespace = 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $actualPath);
            $suggestions[] = "Update namespace to: namespace $correctNamespace;";

            $correctFile = base_path('app' . DIRECTORY_SEPARATOR . $namespacePath . DIRECTORY_SEPARATOR . $class . '.php');
            if ($namespacePath) {
                $suggestions[] = "Or move file to: " . str_replace(base_path(), '', $correctFile);
            }
        }

        // Check for common naming issues
        $expectedClass = end($expectedParts);
        if ($expectedClass !== $class) {
            $suggestions[] = "Class name '$class' doesn't match expected '$expectedClass'";
        }

        // Check if it's just a parent directory case issue
        $parentExpected = dirname($expected);
        $parentActual = dirname($actual);

        if (strtolower($parentExpected) === strtolower($parentActual) && $parentExpected !== $parentActual) {
            $suggestions[] = "Parent directory case mismatch. Consider renaming directory structure.";
        }

        return $suggestions;
    }

    protected function fileExistsCaseInsensitive(string $dir, string $fileName): bool
    {
        if (!is_dir($dir)) return false;
        $normalizedTarget = strtolower($fileName);

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (strtolower($entry) === $normalizedTarget) {
                return true;
            }
        }
        return false;
    }
}
