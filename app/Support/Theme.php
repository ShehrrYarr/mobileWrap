<?php

namespace App\Support;

/**
 * Storefront theme resolver. Themes live in resources/views/themes/{key};
 * any view a theme does not override falls back to themes/base.
 */
class Theme
{
    public function all(): array
    {
        return config('themes', []);
    }

    public function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, $this->all());
    }

    /** Active theme key (admins can preview another theme via session). */
    public function active(): string
    {
        $preview = session('preview_theme');
        if ($this->exists($preview)) {
            return $preview;
        }

        $key = setting('store.theme', 'noir');

        return $this->exists($key) ? $key : (string) array_key_first($this->all());
    }

    public function isPreviewing(): bool
    {
        return $this->exists(session('preview_theme')) && session('preview_theme') !== setting('store.theme');
    }

    public function get(?string $key = null): array
    {
        $key ??= $this->active();

        return ['key' => $key] + ($this->all()[$key] ?? []);
    }

    public function option(string $path, $default = null)
    {
        return data_get($this->get(), $path, $default);
    }

    public function view(string $name, ?string $theme = null): string
    {
        $theme ??= $this->active();
        $candidate = "themes.{$theme}.{$name}";

        return view()->exists($candidate) ? $candidate : "themes.base.{$name}";
    }

    public function cssUrl(?string $key = null): string
    {
        $key ??= $this->active();
        $file = "assets/store/css/{$key}.css";
        $version = @filemtime(public_path($file)) ?: '1';

        return asset($file).'?v='.$version;
    }

    /** Inline CSS variables for admin colour overrides of the active theme. */
    public function colorOverrides(): string
    {
        $key = $this->active();
        $primary = setting("theme_colors.{$key}.primary");
        $accent = setting("theme_colors.{$key}.accent");
        $css = '';

        if ($primary && preg_match('/^#[0-9a-f]{6}$/i', $primary)) {
            [$r, $g, $b] = sscanf($primary, '#%02x%02x%02x');
            $css .= "--sf-primary:{$primary};--bs-primary:{$primary};--bs-primary-rgb:{$r},{$g},{$b};";
        }
        if ($accent && preg_match('/^#[0-9a-f]{6}$/i', $accent)) {
            $css .= "--sf-accent:{$accent};";
        }

        return $css ? ':root{'.$css.'}' : '';
    }
}
