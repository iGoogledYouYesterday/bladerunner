<?php

use Illuminate\Contracts\Container\Container as ContainerContract;

/**
 * Setup Bladerunner options
 */
add_action('after_setup_theme', function () {
    /**
     * Bladerunner config
     */
    $paths = [
        'dir.stylesheet' => get_stylesheet_directory(),
        'dir.template' => get_template_directory(),
        'dir.upload' => wp_upload_dir()['basedir'],
        'uri.stylesheet' => get_stylesheet_directory_uri(),
        'uri.template' => get_template_directory_uri(),
    ];

    $viewPaths = collect(preg_replace('%[\/]?(templates)?[\/.]*?$%', '', [
        apply_filters('bladerunner/template/bladepath', $paths['dir.stylesheet']),
        $paths['dir.stylesheet'] . DIRECTORY_SEPARATOR . 'views',
        STYLESHEETPATH,
        TEMPLATEPATH,
    ]))
        ->flatMap(function ($path) {
            return ["{$path}/templates", $path];
        })->unique()->toArray();

    \Bladerunner\Config::repo([
            'view.compiled' => "{$paths['dir.upload']}/.cache",
            'view.paths' => $viewPaths,
        ] + $paths);

    /**
     * Add Blade to Bladerunner container
     */
    Bladerunner\Container::current()->singleton('bladerunner.blade', function (ContainerContract $app) {
        $cachePath = Bladerunner\Config::repo('view.compiled');
        if (!file_exists($cachePath)) {
            wp_mkdir_p($cachePath);
        }
        (new \Bladerunner\BladeProvider($app))->register();
        return new \Bladerunner\Blade($app['view'], $app);
    });

    \Bladerunner\Container::current('blade')->compiler()->directive('controller', function () {
        return '<?php (new \Bladerunner\ControllerDebug(get_defined_vars())); ?>';
    });

    $extensions = apply_filters('bladerunner/extend', []);
    if ($extensions && is_array($extensions)) {
        foreach ($extensions as $extension) {
            if (is_callable($extension)) {
                \Bladerunner\Container::current('blade')->compiler()->extend($extension);
            }
        }
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        array_map('unlink',
        glob(\Bladerunner\Config::repo('view.compiled') . '/*'));
    }
});

/**
 * Init config
 */
Bladerunner\Container::current()->bindIf('config', Bladerunner\Repository::class, true);
