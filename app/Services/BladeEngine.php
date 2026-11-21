<?php
declare(strict_types=1);

namespace App\Services;

class BladeEngine
{
    protected static string $viewsPath = '';
    protected static string $cachePath = '';
    protected static array $sections = [];
    protected static ?string $currentSection = null;

    public static function init(): void
    {
        $base = dirname(__DIR__, 2);
        self::$viewsPath = $base . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
        self::$cachePath = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views';

        if (!is_dir(self::$cachePath)) {
            mkdir(self::$cachePath, 0777, true);
        }
    }

    /**
     * Render a Blade view template with data.
     *
     * @param string $view e.g. 'dashboard.index' or 'production.show'
     * @param array $data Data variables passed to the view
     * @return string Rendered HTML
     */
    public static function render(string $view, array $data = []): string
    {
        self::init();

        $viewPath = self::resolveViewPath($view);
        if (!$viewPath || !file_exists($viewPath)) {
            throw new \RuntimeException("Blade view [{$view}] tidak ditemukan di: " . self::$viewsPath);
        }

        $compiledPath = self::getCompiledPath($viewPath);

        // Recompile if cache does not exist or source file has been modified
        if (!file_exists($compiledPath) || filemtime($viewPath) > filemtime($compiledPath)) {
            $compiledContent = self::compile(file_get_contents($viewPath));
            file_put_contents($compiledPath, $compiledContent);
        }

        // Render the compiled template in an isolated scope
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $compiledPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return ob_get_clean();
    }

    /**
     * Resolve view name (e.g. 'dashboard.index') to full file path.
     */
    public static function resolveViewPath(string $view): ?string
    {
        $normalized = str_replace(['/', '\\', '.'], DIRECTORY_SEPARATOR, $view);
        $bladePath = self::$viewsPath . DIRECTORY_SEPARATOR . $normalized . '.blade.php';
        if (file_exists($bladePath)) {
            return $bladePath;
        }

        $phpPath = self::$viewsPath . DIRECTORY_SEPARATOR . $normalized . '.php';
        if (file_exists($phpPath)) {
            return $phpPath;
        }

        return null;
    }

    /**
     * Get compiled cache path for a template file.
     */
    protected static function getCompiledPath(string $filePath): string
    {
        $hash = md5($filePath);
        return self::$cachePath . DIRECTORY_SEPARATOR . $hash . '.php';
    }

    /**
     * Compile Blade template code into plain PHP.
     */
    public static function compile(string $template): string
    {
        // 1. Remove comments {{-- comment --}}
        $template = preg_replace('/\{\{--(.*?)--\}\}/s', '', $template);

        // 2. Compile legacy require statements if any
        $template = preg_replace(
            '/require\s+dirname\(__DIR__\)\s*\.\s*\'\/layouts\/(header|footer|sidebar)\.(php|blade\.php)\';/i',
            "echo \App\Services\BladeEngine::render('layouts.$1', get_defined_vars());",
            $template
        );
        $template = preg_replace(
            '/require\s+__DIR__\s*\.\s*\'\/(header|footer|sidebar)\.(php|blade\.php)\';/i',
            "echo \App\Services\BladeEngine::render('layouts.$1', get_defined_vars());",
            $template
        );

        // 3. Compile @include('view.name', ['key' => 'val'])
        $template = preg_replace_callback('/@include\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*(.*?))?\s*\)/s', function ($matches) {
            $view = $matches[1];
            $extra = !empty($matches[2]) ? $matches[2] : '[]';
            return "<?php echo \App\Services\BladeEngine::render('{$view}', array_merge(get_defined_vars(), {$extra})); ?>";
        }, $template);

        // 4. Compile @extends('layouts.app') and @section / @yield
        $template = preg_replace_callback('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', function ($matches) {
            return "<?php \$__layout = '{$matches[1]}'; ?>";
        }, $template);

        $template = preg_replace('/@section\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', '<?php \App\Services\BladeEngine::startSection(\'$1\'); ?>', $template);
        $template = preg_replace('/@endsection|@stop/', '<?php \App\Services\BladeEngine::endSection(); ?>', $template);
        $template = preg_replace('/@yield\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*[\'"]([^\'"]*)[\'"])?\s*\)/', '<?= \App\Services\BladeEngine::yieldSection(\'$1\', \'$2\') ?>', $template);

        // 5. Compile {{ $var }} -> htmlspecialchars escaped output
        $template = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', function ($matches) {
            $expr = trim($matches[1]);
            // If expression ends with semicolon, trim it
            $expr = rtrim($expr, ';');
            return "<?= htmlspecialchars((string)({$expr} ?? ''), ENT_QUOTES, 'UTF-8') ?>";
        }, $template);

        // 6. Compile {!! $var !!} -> raw unescaped output
        $template = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', function ($matches) {
            $expr = trim($matches[1]);
            $expr = rtrim($expr, ';');
            return "<?= ({$expr} ?? '') ?>";
        }, $template);

        // 7. Compile Control Directives
        $directives = [
            '/@if\s*\((.*?)\)/' => '<?php if ($1): ?>',
            '/@elseif\s*\((.*?)\)/' => '<?php elseif ($1): ?>',
            '/@else/' => '<?php else: ?>',
            '/@endif/' => '<?php endif; ?>',
            '/@unless\s*\((.*?)\)/' => '<?php if (!($1)): ?>',
            '/@endunless/' => '<?php endif; ?>',
            '/@foreach\s*\((.*?)\)/' => '<?php foreach ($1): ?>',
            '/@endforeach/' => '<?php endforeach; ?>',
            '/@for\s*\((.*?)\)/' => '<?php for ($1): ?>',
            '/@endfor/' => '<?php endfor; ?>',
            '/@while\s*\((.*?)\)/' => '<?php while ($1): ?>',
            '/@endwhile/' => '<?php endwhile; ?>',
            '/@isset\s*\((.*?)\)/' => '<?php if (isset($1)): ?>',
            '/@endisset/' => '<?php endif; ?>',
            '/@empty\s*\((.*?)\)/' => '<?php if (empty($1)): ?>',
            '/@endempty/' => '<?php endif; ?>',
            '/@php/' => '<?php ',
            '/@endphp/' => ' ?>',
            '/@csrf/' => '<input type="hidden" name="_token" value="<?= $_SESSION[\'_token\'] ?? \'\' ?>">',
            '/@method\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/' => '<input type="hidden" name="_method" value="$1">',
            '/@auth/' => '<?php if (!empty($_SESSION[\'user_id\'])): ?>',
            '/@endauth/' => '<?php endif; ?>',
            '/@guest/' => '<?php if (empty($_SESSION[\'user_id\'])): ?>',
            '/@endguest/' => '<?php endif; ?>',
        ];

        foreach ($directives as $pattern => $replacement) {
            $template = preg_replace($pattern, $replacement, $template);
        }

        return $template;
    }

    public static function startSection(string $name): void
    {
        self::$currentSection = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        if (self::$currentSection !== null) {
            self::$sections[self::$currentSection] = ob_get_clean();
            self::$currentSection = null;
        }
    }

    public static function yieldSection(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }
}
