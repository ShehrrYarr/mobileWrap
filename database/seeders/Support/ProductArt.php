<?php

namespace Database\Seeders\Support;

use GdImage;

/**
 * Draws simple flat "studio shots" of accessories in any colour with GD, so
 * demo products get one distinct image set per colour variant. Images are
 * transparent PNGs; every theme supplies its own card background.
 */
class ProductArt
{
    protected int $S = 1200;   // working canvas (drawn at 2× then downsampled)

    protected int $out = 600;  // saved size

    public function save(string $shape, string $hex, string $path, int $view = 0, array $opts = []): void
    {
        $img = $this->render($shape, $hex, $view, $opts);
        @mkdir(dirname($path), 0775, true);
        imagepng($img, $path, 7);
        imagedestroy($img);
    }

    public function render(string $shape, string $hex, int $view = 0, array $opts = []): GdImage
    {
        $S = $this->S;
        $canvas = $this->blank($S, $S);
        $layer = $this->blank($S, $S);
        $c = $this->rgb($hex);

        match ($shape) {
            'case' => $this->drawCase($layer, $c, $opts),
            'wrap' => $this->drawWrap($layer, $c, $opts),
            'glass' => $this->drawGlass($layer, $c, $opts),
            'lens' => $this->drawLens($layer, $c),
            'charger' => $this->drawCharger($layer, $c, $opts),
            'puck' => $this->drawPuck($layer, $c),
            'cable' => $this->drawCable($layer, $c, $opts),
            'powerbank' => $this->drawPowerBank($layer, $c, $opts),
            'earbuds' => $this->drawEarbuds($layer, $c, $view),
            'stand' => $this->drawStand($layer, $c),
            'ring' => $this->drawRing($layer, $c),
            'selfie' => $this->drawSelfie($layer, $c),
            'roll' => $this->drawRoll($layer, $c, $opts),
            default => $this->drawCase($layer, $c, $opts),
        };

        // Floor shadow
        $this->softEllipse($canvas, $S / 2 + ($view ? 40 : 0), (int) ($S * 0.90), (int) ($S * 0.52), (int) ($S * 0.07), [0, 0, 0], 26);

        imagesetinterpolation($layer, IMG_NEAREST_NEIGHBOUR);
        if ($view === 1 && ! in_array($shape, ['earbuds'], true)) {
            // second angle: rotated + slightly smaller, with a ghost copy behind
            $ghost = imagerotate($layer, 14, imagecolorallocatealpha($layer, 0, 0, 0, 127));
            imagesavealpha($ghost, true);
            $this->copyScaled($canvas, $ghost, 0.78, -150, -40, 55);
            imagedestroy($ghost);

            $rot = imagerotate($layer, -12, imagecolorallocatealpha($layer, 0, 0, 0, 127));
            imagesavealpha($rot, true);
            $this->copyScaled($canvas, $rot, 0.86, 90, 30);
            imagedestroy($rot);
        } else {
            imagecopy($canvas, $layer, 0, 0, 0, 0, $S, $S);
        }
        imagedestroy($layer);

        $final = $this->blank($this->out, $this->out);
        imagealphablending($final, false);
        imagecopyresampled($final, $canvas, 0, 0, 0, 0, $this->out, $this->out, $S, $S);
        imagesavealpha($final, true);
        imagedestroy($canvas);

        return $final;
    }

    // ── Shapes ───────────────────────────────────────────────────────

    protected function drawCase(GdImage $im, array $c, array $o): void
    {
        $S = $this->S;
        [$x1, $y1, $x2, $y2, $r] = $this->phoneBox(0.40, 0.80, 0.085);
        $clear = $o['clear'] ?? false;

        $this->roundRect($im, $x1, $y1, $x2, $y2, $r, $this->col($im, $this->shade($c, -0.28)));
        $inset = (int) ($S * 0.012);
        if ($clear) {
            $this->roundRect($im, $x1 + $inset, $y1 + $inset, $x2 - $inset, $y2 - $inset, $r - $inset, $this->col($im, [46, 49, 56]));
            $this->roundRect($im, $x1 + $inset, $y1 + $inset, $x2 - $inset, $y2 - $inset, $r - $inset, $this->col($im, $c, 70));
            imagesetthickness($im, 10);
            imageellipse($im, (int) (($x1 + $x2) / 2), (int) ($y1 + ($y2 - $y1) * 0.52), (int) ($S * 0.2), (int) ($S * 0.2), $this->col($im, [235, 238, 242], 40));
            imagesetthickness($im, 1);
        } else {
            $this->roundRect($im, $x1 + $inset, $y1 + $inset, $x2 - $inset, $y2 - $inset, $r - $inset, $this->col($im, $c));
            // subtle emblem
            imagesetthickness($im, 6);
            imageellipse($im, (int) (($x1 + $x2) / 2), (int) ($y1 + ($y2 - $y1) * 0.5), (int) ($S * 0.075), (int) ($S * 0.075), $this->col($im, $this->shade($c, -0.12)));
            imagesetthickness($im, 1);
        }

        $this->camera($im, $x1, $y1, $c, $o['camera'] ?? 'square');
        // side buttons
        $this->roundRect($im, $x2 - 8, (int) ($y1 + $S * 0.20), $x2 + 10, (int) ($y1 + $S * 0.29), 8, $this->col($im, $this->shade($c, -0.35)));
        $this->roundRect($im, $x1 - 10, (int) ($y1 + $S * 0.17), $x1 + 8, (int) ($y1 + $S * 0.22), 8, $this->col($im, $this->shade($c, -0.35)));
        $this->glare($im, $x1, $y1, $x2, $y2, $r, 104);
    }

    protected function drawWrap(GdImage $im, array $c, array $o): void
    {
        $S = $this->S;
        [$x1, $y1, $x2, $y2, $r] = $this->phoneBox(0.40, 0.80, 0.085);
        // phone frame
        $this->roundRect($im, $x1, $y1, $x2, $y2, $r, $this->col($im, [38, 40, 45]));
        $i = (int) ($S * 0.007);
        $skin = $this->blank($S, $S);
        $px1 = $x1 + $i; $py1 = $y1 + $i; $px2 = $x2 - $i; $py2 = $y2 - $i;

        switch ($o['pattern'] ?? 'solid') {
            case 'carbon':
                imagefilledrectangle($skin, $px1, $py1, $px2, $py2, $this->col($skin, $c));
                $light = $this->col($skin, $this->shade($c, 0.16));
                $dark = $this->col($skin, $this->shade($c, -0.18));
                for ($y = $py1; $y < $py2; $y += 22) {
                    for ($x = $px1; $x < $px2; $x += 22) {
                        $odd = (($x - $px1) / 22 + ($y - $py1) / 22) % 2;
                        imagefilledrectangle($skin, $x, $y, min($x + 10, $px2), min($y + 21, $py2), $odd ? $light : $dark);
                        if ($x + 11 <= $px2) {
                            imagefilledrectangle($skin, $x + 11, $y, min($x + 21, $px2), min($y + 10, $py2), $odd ? $dark : $light);
                        }
                    }
                }
                break;
            case 'gradient':
                $stops = array_map(fn ($h) => $this->rgb($h), $o['stops'] ?? ['#FF5F6D', '#FFC371']);
                $h = max(1, $py2 - $py1);
                for ($y = $py1; $y <= $py2; $y++) {
                    $t = ($y - $py1) / $h;
                    imageline($skin, $px1, $y, $px2, $y, $this->col($skin, $this->mixStops($stops, $t)));
                }
                break;
            case 'marble':
                imagefilledrectangle($skin, $px1, $py1, $px2, $py2, $this->col($skin, $c));
                mt_srand(crc32(implode(',', $c)));
                for ($v = 0; $v < 9; $v++) {
                    $x = mt_rand($px1, $px2); $y = $py1;
                    $thick = mt_rand(2, 6);
                    $vein = $this->col($skin, $this->shade($c, ($c[0] > 128 ? -0.35 : 0.35)), mt_rand(40, 85));
                    while ($y < $py2) {
                        $nx = $x + mt_rand(-38, 38); $ny = $y + mt_rand(20, 60);
                        imagesetthickness($skin, $thick);
                        imageline($skin, $x, $y, $nx, $ny, $vein);
                        $x = $nx; $y = $ny;
                    }
                }
                imagesetthickness($skin, 1);
                break;
            case 'wood':
                imagefilledrectangle($skin, $px1, $py1, $px2, $py2, $this->col($skin, $c));
                mt_srand(crc32(implode(',', $c)) + 7);
                for ($x = $px1; $x < $px2; $x += mt_rand(9, 22)) {
                    $grain = $this->col($skin, $this->shade($c, mt_rand(-28, 12) / 100), 30);
                    imagesetthickness($skin, mt_rand(2, 6));
                    $prevY = $py1; $prevX = $x;
                    for ($y = $py1; $y <= $py2; $y += 30) {
                        $nx = (int) ($x + sin(($y + $x) / 90) * 12);
                        imageline($skin, $prevX, $prevY, $nx, $y, $grain);
                        $prevX = $nx; $prevY = $y;
                    }
                }
                imagesetthickness($skin, 1);
                break;
            default:
                imagefilledrectangle($skin, $px1, $py1, $px2, $py2, $this->col($skin, $c));
        }

        $this->clipRoundRect($skin, $px1, $py1, $px2, $py2, $r - $i);
        imagecopy($im, $skin, 0, 0, 0, 0, $S, $S);
        imagedestroy($skin);

        $this->camera($im, $x1, $y1, [30, 31, 35], 'square', true);
        $this->glare($im, $x1, $y1, $x2, $y2, $r, ($o['pattern'] ?? '') === 'solid' ? 114 : 108);
    }

    protected function drawGlass(GdImage $im, array $c, array $o): void
    {
        $S = $this->S;
        [$x1, $y1, $x2, $y2, $r] = $this->phoneBox(0.40, 0.82, 0.075);
        $privacy = ($c[0] + $c[1] + $c[2]) < 260;

        $this->roundRect($im, $x1, $y1, $x2, $y2, $r, $this->col($im, [250, 252, 255], 20));
        $b = (int) ($S * 0.012);
        $this->roundRect($im, $x1 + $b, $y1 + $b, $x2 - $b, $y2 - $b, $r - $b, $this->col($im, [17, 18, 21]));
        $g = (int) ($S * 0.024);
        $inner = $privacy ? $c : $this->shade($c, 0.05);
        $this->roundRect($im, $x1 + $g, $y1 + $g, $x2 - $g, $y2 - $g, max(8, $r - $g), $this->col($im, $inner));

        // soft vertical sheen
        for ($k = 0; $k < 6; $k++) {
            $xx = (int) ($x1 + $g + ($x2 - $x1 - 2 * $g) * (0.12 + $k * 0.03));
            imagefilledrectangle($im, $xx, $y1 + $g + 10, $xx + 6, $y2 - $g - 10, $this->col($im, [255, 255, 255], 118));
        }
        // dynamic island
        $cx = (int) (($x1 + $x2) / 2);
        $this->roundRect($im, $cx - (int) ($S * 0.05), $y1 + (int) ($S * 0.045), $cx + (int) ($S * 0.05), $y1 + (int) ($S * 0.075), (int) ($S * 0.015), $this->col($im, [10, 10, 12]));
        $this->glare($im, $x1, $y1, $x2, $y2, $r, $privacy ? 110 : 96);
        $this->glare($im, $x1, $y1, $x2, $y2, $r, 112, 0.35);
    }

    protected function drawLens(GdImage $im, array $c): void
    {
        $S = $this->S;
        $cx = $S / 2; $cy = $S / 2;
        $this->roundRect($im, (int) ($cx - $S * 0.24), (int) ($cy - $S * 0.24), (int) ($cx + $S * 0.24), (int) ($cy + $S * 0.24), (int) ($S * 0.08), $this->col($im, [240, 242, 245], 30));
        foreach ([[-0.1, -0.1], [-0.1, 0.11], [0.11, 0]] as [$dx, $dy]) {
            $x = (int) ($cx + $S * $dx); $y = (int) ($cy + $S * $dy);
            imagefilledellipse($im, $x, $y, (int) ($S * 0.19), (int) ($S * 0.19), $this->col($im, $this->shade($c, -0.2)));
            imagefilledellipse($im, $x, $y, (int) ($S * 0.165), (int) ($S * 0.165), $this->col($im, $c));
            imagefilledellipse($im, $x, $y, (int) ($S * 0.12), (int) ($S * 0.12), $this->col($im, [14, 16, 24]));
            imagefilledellipse($im, $x, $y, (int) ($S * 0.06), (int) ($S * 0.06), $this->col($im, [32, 42, 70]));
            imagefilledellipse($im, (int) ($x - $S * 0.022), (int) ($y - $S * 0.022), (int) ($S * 0.03), (int) ($S * 0.03), $this->col($im, [255, 255, 255], 50));
        }
    }

    protected function drawCharger(GdImage $im, array $c, array $o): void
    {
        $S = $this->S;
        $w = (int) ($S * ($o['wide'] ?? false ? 0.40 : 0.34));
        $h = (int) ($S * 0.36);
        $x1 = (int) ($S / 2 - $w / 2); $y1 = (int) ($S * 0.42); $x2 = $x1 + $w; $y2 = $y1 + $h; $r = (int) ($S * 0.06);
        $metal = $this->col($im, [196, 201, 207]);
        // round pins (Pakistan type C/D)
        foreach ([-0.07, 0.07] as $dx) {
            $px = (int) ($S / 2 + $S * $dx);
            $this->roundRect($im, $px - 18, (int) ($y1 - $S * 0.15), $px + 18, $y1 + 20, 18, $metal);
            $this->roundRect($im, $px - 18, (int) ($y1 - $S * 0.15), $px + 18, (int) ($y1 - $S * 0.13), 16, $this->col($im, [150, 155, 160]));
        }
        $d = (int) ($S * 0.035);
        $this->roundRect($im, $x1 + $d, $y1 + $d, $x2 + $d, $y2 + $d, $r, $this->col($im, $this->shade($c, -0.3)));
        $this->roundRect($im, $x1, $y1, $x2, $y2, $r, $this->col($im, $c));
        $ports = $o['ports'] ?? 1;
        $dark = $this->col($im, [24, 25, 28]);
        for ($p = 0; $p < $ports; $p++) {
            $py = (int) ($y1 + $h * (0.36 + $p * 0.2));
            if ($p === 2) {
                $this->roundRect($im, (int) ($S / 2 - $S * 0.06), $py, (int) ($S / 2 + $S * 0.06), $py + (int) ($S * 0.035), 6, $dark);
            } else {
                $this->roundRect($im, (int) ($S / 2 - $S * 0.05), $py, (int) ($S / 2 + $S * 0.05), $py + (int) ($S * 0.03), 20, $dark);
            }
        }
        imagefilledellipse($im, (int) ($x2 - $S * 0.04), (int) ($y1 + $S * 0.04), 18, 18, $this->col($im, [64, 224, 160]));
        $this->glare($im, $x1, $y1, $x2, $y2, $r, 110);
    }

    protected function drawPuck(GdImage $im, array $c): void
    {
        $S = $this->S; $cx = $S / 2; $cy = (int) ($S * 0.48);
        $this->stroke($im, fn ($t) => [$cx + sin($t * 3) * 120, $cy + $S * 0.22 + $t * $S * 0.22], (int) ($S * 0.022), $this->shade($c, -0.15));
        imagefilledellipse($im, (int) $cx, $cy + 24, (int) ($S * 0.52), (int) ($S * 0.52), $this->col($im, $this->shade($c, -0.3)));
        imagefilledellipse($im, (int) $cx, $cy, (int) ($S * 0.52), (int) ($S * 0.52), $this->col($im, $c));
        imagefilledellipse($im, (int) $cx, $cy, (int) ($S * 0.42), (int) ($S * 0.42), $this->col($im, $this->shade($c, 0.06)));
        imagesetthickness($im, 8);
        imageellipse($im, (int) $cx, $cy, (int) ($S * 0.22), (int) ($S * 0.22), $this->col($im, $this->shade($c, -0.12)));
        imagesetthickness($im, 1);
        imagefilledellipse($im, (int) ($cx - $S * 0.08), (int) ($cy - $S * 0.1), (int) ($S * 0.12), (int) ($S * 0.05), $this->col($im, [255, 255, 255], 96));
    }

    protected function drawCable(GdImage $im, array $c, array $o): void
    {
        $S = $this->S; $cx = $S / 2; $cy = (int) ($S * 0.42);
        $th = (int) ($S * 0.03);
        $col = $c;
        // coil loops
        foreach ([[-0.06, 0], [0, 0.02], [0.06, -0.01]] as $k => [$dx, $dy]) {
            $rx = $S * 0.24; $ry = $S * 0.2;
            $ox = $cx + $S * $dx; $oy = $cy + $S * $dy;
            $this->stroke($im, fn ($t) => [$ox + cos($t * 2 * M_PI) * $rx, $oy + sin($t * 2 * M_PI) * $ry], $th, $this->shade($col, $k * 0.05), 900, $o['braided'] ?? true);
        }
        // tails
        $this->stroke($im, fn ($t) => [$cx - $S * 0.2 - $t * $S * 0.1, $cy + $S * 0.17 + $t * $S * 0.2], $th, $col, 200, $o['braided'] ?? true);
        $this->stroke($im, fn ($t) => [$cx + $S * 0.22 + $t * $S * 0.08, $cy + $S * 0.15 + $t * $S * 0.22], $th, $col, 200, $o['braided'] ?? true);
        // connectors
        $head = $this->col($im, $this->shade($col, -0.08));
        $metal = $this->col($im, [205, 210, 216]);
        foreach ([[$cx - $S * 0.3, $cy + $S * 0.37, $o['left'] ?? 'c'], [$cx + $S * 0.3, $cy + $S * 0.37, $o['right'] ?? 'c']] as [$hx, $hy, $type]) {
            $hx = (int) $hx; $hy = (int) $hy;
            $this->roundRect($im, $hx - 45, $hy - 40, $hx + 45, $hy + 110, 26, $head);
            if ($type === 'a') {
                imagefilledrectangle($im, $hx - 42, $hy + 110, $hx + 42, $hy + 190, $metal);
                imagefilledrectangle($im, $hx - 28, $hy + 140, $hx + 28, $hy + 150, $this->col($im, [140, 145, 150]));
            } elseif ($type === 'l') {
                $this->roundRect($im, $hx - 18, $hy + 110, $hx + 18, $hy + 175, 10, $metal);
            } else {
                $this->roundRect($im, $hx - 30, $hy + 110, $hx + 30, $hy + 170, 14, $metal);
            }
        }
    }

    protected function drawPowerBank(GdImage $im, array $c, array $o): void
    {
        $S = $this->S;
        $square = $o['square'] ?? false;
        $w = (int) ($S * ($square ? 0.46 : 0.40)); $h = (int) ($S * ($square ? 0.56 : 0.72));
        $x1 = (int) ($S / 2 - $w / 2); $y1 = (int) ($S * 0.48 - $h / 2); $x2 = $x1 + $w; $y2 = $y1 + $h; $r = (int) ($S * 0.06);
        $d = (int) ($S * 0.03);
        $this->roundRect($im, $x1 + $d, $y1 + $d, $x2 + $d, $y2 + $d, $r, $this->col($im, $this->shade($c, -0.3)));
        $this->roundRect($im, $x1, $y1, $x2, $y2, $r, $this->col($im, $c));
        if ($square) {
            imagesetthickness($im, 10);
            imageellipse($im, (int) ($S / 2), (int) (($y1 + $y2) / 2), (int) ($S * 0.26), (int) ($S * 0.26), $this->col($im, $this->shade($c, -0.12)));
            imagesetthickness($im, 1);
        } else {
            for ($k = 0; $k < 5; $k++) {
                $yy = (int) ($y1 + $S * 0.05 + $k * 16);
                imagefilledrectangle($im, $x1 + 40, $yy, $x2 - 40, $yy + 4, $this->col($im, $this->shade($c, 0.08)));
            }
        }
        for ($k = 0; $k < 4; $k++) {
            imagefilledellipse($im, (int) ($S / 2 - 54 + $k * 36), (int) ($y2 - $S * 0.06), 18, 18, $this->col($im, $k < 3 ? [64, 224, 160] : $this->shade($c, -0.25)));
        }
        $this->roundRect($im, (int) ($S / 2 - $S * 0.05), $y2 - 14, (int) ($S / 2 + $S * 0.05), $y2 + 8, 10, $this->col($im, [24, 25, 28]));
        $this->glare($im, $x1, $y1, $x2, $y2, $r, 108);
    }

    protected function drawEarbuds(GdImage $im, array $c, int $view): void
    {
        $S = $this->S;
        $w = (int) ($S * 0.46); $h = (int) ($S * 0.36);
        $x1 = (int) ($S / 2 - $w / 2); $y1 = (int) ($S * ($view ? 0.52 : 0.4)); $x2 = $x1 + $w; $y2 = $y1 + $h; $r = (int) ($S * 0.15);
        $this->roundRect($im, $x1 + 20, $y1 + 26, $x2 + 20, $y2 + 26, $r, $this->col($im, $this->shade($c, -0.3)));
        $this->roundRect($im, $x1, $y1, $x2, $y2, $r, $this->col($im, $c));
        imagefilledrectangle($im, $x1 + 30, (int) ($y1 + $h * 0.34), $x2 - 30, (int) ($y1 + $h * 0.34) + 6, $this->col($im, $this->shade($c, -0.2)));
        imagefilledellipse($im, (int) ($S / 2), (int) ($y1 + $h * 0.62), 20, 20, $this->col($im, [64, 224, 160]));
        $this->glare($im, $x1, $y1, $x2, $y2, $r, 110);

        if ($view) {
            foreach ([-1, 1] as $side) {
                $bx = (int) ($S / 2 + $side * $S * 0.13); $by = (int) ($S * 0.3);
                $this->roundRect($im, $bx - 28, $by, $bx + 28, $by + (int) ($S * 0.17), 26, $this->col($im, $this->shade($c, -0.06)));
                imagefilledellipse($im, $bx - $side * 30, $by - 10, (int) ($S * 0.12), (int) ($S * 0.12), $this->col($im, $c));
                imagefilledellipse($im, $bx - $side * 52, $by - 18, (int) ($S * 0.05), (int) ($S * 0.05), $this->col($im, [40, 42, 46]));
            }
        }
    }

    protected function drawStand(GdImage $im, array $c): void
    {
        $S = $this->S; $cx = (int) ($S / 2);
        $dark = $this->col($im, $this->shade($c, -0.3));
        $base = $this->col($im, $c);
        $this->roundRect($im, (int) ($cx - $S * 0.26), (int) ($S * 0.78), (int) ($cx + $S * 0.26), (int) ($S * 0.84), 30, $dark);
        $this->roundRect($im, (int) ($cx - $S * 0.26), (int) ($S * 0.76), (int) ($cx + $S * 0.26), (int) ($S * 0.81), 28, $base);
        imagefilledpolygon($im, [(int) ($cx + $S * 0.02), (int) ($S * 0.78), (int) ($cx + $S * 0.08), (int) ($S * 0.78), (int) ($cx - $S * 0.04), (int) ($S * 0.42), (int) ($cx - $S * 0.1), (int) ($S * 0.42)], $dark);
        imagefilledpolygon($im, [(int) ($cx - $S * 0.18), (int) ($S * 0.62), (int) ($cx + $S * 0.04), (int) ($S * 0.62), (int) ($cx - $S * 0.06), (int) ($S * 0.16), (int) ($cx - $S * 0.28), (int) ($S * 0.16)], $base);
        imagefilledpolygon($im, [(int) ($cx - $S * 0.2), (int) ($S * 0.6), (int) ($cx + $S * 0.06), (int) ($S * 0.6), (int) ($cx + $S * 0.06), (int) ($S * 0.65), (int) ($cx - $S * 0.2), (int) ($S * 0.65)], $dark);
        imagefilledpolygon($im, [(int) ($cx - $S * 0.25), (int) ($S * 0.2), (int) ($cx - $S * 0.21), (int) ($S * 0.2), (int) ($cx - $S * 0.13), (int) ($S * 0.55), (int) ($cx - $S * 0.17), (int) ($S * 0.55)], $this->col($im, [255, 255, 255], 100));
    }

    protected function drawRing(GdImage $im, array $c): void
    {
        $S = $this->S; $cx = (int) ($S / 2); $cy = (int) ($S * 0.5);
        $this->roundRect($im, $cx - (int) ($S * 0.17), $cy - (int) ($S * 0.17) + 20, $cx + (int) ($S * 0.17), $cy + (int) ($S * 0.17) + 20, 60, $this->col($im, $this->shade($c, -0.35)));
        $this->roundRect($im, $cx - (int) ($S * 0.17), $cy - (int) ($S * 0.17), $cx + (int) ($S * 0.17), $cy + (int) ($S * 0.17), 60, $this->col($im, $this->shade($c, -0.1)));
        imagefilledellipse($im, $cx, $cy, (int) ($S * 0.16), (int) ($S * 0.16), $this->col($im, $this->shade($c, 0.1)));
        // the ring
        imagefilledellipse($im, $cx, (int) ($cy - $S * 0.09), (int) ($S * 0.46), (int) ($S * 0.46), $this->col($im, $c));
        imagealphablending($im, false);
        imagefilledellipse($im, $cx, (int) ($cy - $S * 0.09), (int) ($S * 0.34), (int) ($S * 0.34), imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        $this->roundRect($im, $cx - (int) ($S * 0.17), $cy - (int) ($S * 0.17), $cx + (int) ($S * 0.17), $cy - (int) ($S * 0.12), 20, $this->col($im, $this->shade($c, -0.1)));
        imagefilledellipse($im, $cx, $cy - (int) ($S * 0.13), (int) ($S * 0.08), (int) ($S * 0.08), $this->col($im, $this->shade($c, -0.25)));
        imagesetthickness($im, 10);
        imagearc($im, $cx, (int) ($cy - $S * 0.09), (int) ($S * 0.44), (int) ($S * 0.44), 200, 260, $this->col($im, [255, 255, 255], 70));
        imagesetthickness($im, 1);
    }

    protected function drawSelfie(GdImage $im, array $c): void
    {
        $S = $this->S; $cx = (int) ($S / 2);
        $col = $this->col($im, $c); $dark = $this->col($im, $this->shade($c, -0.3));
        foreach ([[-0.22, 0.9], [0, 0.94], [0.22, 0.9]] as [$dx, $ey]) {
            $this->stroke($im, fn ($t) => [$cx + $t * $S * $dx, $S * 0.62 + $t * $S * ($ey - 0.62)], 26, $this->shade($c, -0.15), 120);
        }
        $this->roundRect($im, $cx - 26, (int) ($S * 0.22), $cx + 26, (int) ($S * 0.66), 22, $col);
        $this->roundRect($im, $cx - 40, (int) ($S * 0.58), $cx + 40, (int) ($S * 0.66), 20, $dark);
        $this->roundRect($im, $cx - (int) ($S * 0.13), (int) ($S * 0.06), $cx + (int) ($S * 0.13), (int) ($S * 0.11), 18, $dark);
        $this->roundRect($im, $cx - (int) ($S * 0.13), (int) ($S * 0.2), $cx + (int) ($S * 0.13), (int) ($S * 0.25), 18, $dark);
        $this->roundRect($im, $cx - (int) ($S * 0.11), (int) ($S * 0.1), $cx + (int) ($S * 0.11), (int) ($S * 0.21), 10, $this->col($im, [30, 32, 36]));
        imagefilledellipse($im, $cx, (int) ($S * 0.45), 40, 40, $this->col($im, [64, 224, 160]));
    }

    protected function drawRoll(GdImage $im, array $c, array $o): void
    {
        $S = $this->S;
        $x1 = (int) ($S * 0.2); $x2 = (int) ($S * 0.78); $yc = (int) ($S * 0.4); $rh = (int) ($S * 0.15);
        // unrolled sheet
        $sheet = $this->col($im, $this->shade($c, 0.1), ($o['clear'] ?? false) ? 40 : 0);
        imagefilledpolygon($im, [$x1, $yc + $rh - 10, $x2, $yc + $rh - 10, $x2 - (int) ($S * 0.12), (int) ($S * 0.86), $x1 - (int) ($S * 0.12), (int) ($S * 0.86)], $sheet);
        imagefilledrectangle($im, $x1, $yc - $rh, $x2, $yc + $rh, $this->col($im, $c));
        for ($k = 1; $k < 6; $k++) {
            imageline($im, $x1, $yc - $rh + $k * 48, $x2, $yc - $rh + $k * 48, $this->col($im, $this->shade($c, -0.08), 60));
        }
        imagefilledellipse($im, $x1, $yc, (int) ($S * 0.1), $rh * 2, $this->col($im, $this->shade($c, -0.25)));
        imagefilledellipse($im, $x2, $yc, (int) ($S * 0.1), $rh * 2, $this->col($im, $this->shade($c, 0.12)));
        imagefilledellipse($im, $x2, $yc, (int) ($S * 0.035), (int) ($rh * 0.7), $this->col($im, [70, 60, 50]));
        imagefilledrectangle($im, $x1, $yc - $rh + 12, $x2, $yc - $rh + 30, $this->col($im, [255, 255, 255], 95));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    protected function phoneBox(float $w, float $h, float $r): array
    {
        $S = $this->S;
        $W = (int) ($S * $w); $H = (int) ($S * $h);
        $x1 = (int) ($S / 2 - $W / 2); $y1 = (int) ($S * 0.47 - $H / 2);

        return [$x1, $y1, $x1 + $W, $y1 + $H, (int) ($S * $r)];
    }

    protected function camera(GdImage $im, int $x1, int $y1, array $c, string $style = 'square', bool $phone = false): void
    {
        $S = $this->S;
        $bx = (int) ($x1 + $S * 0.035); $by = (int) ($y1 + $S * 0.035); $size = (int) ($S * 0.165);
        $lens = (int) ($S * 0.064);
        if ($style === 'pill') {
            $this->roundRect($im, $bx, $by, $bx + (int) ($S * 0.085), $by + (int) ($S * 0.25), (int) ($S * 0.042), $this->col($im, $phone ? $c : $this->shade($c, -0.3)));
            $points = [[0.0425, 0.045], [0.0425, 0.125], [0.0425, 0.205]];
        } else {
            $this->roundRect($im, $bx, $by, $bx + $size, $by + $size, (int) ($S * 0.045), $this->col($im, $phone ? $c : $this->shade($c, -0.3)));
            $points = [[0.045, 0.045], [0.045, 0.12], [0.12, 0.0825]];
        }
        foreach ($points as [$px, $py]) {
            $x = (int) ($bx + $S * $px); $y = (int) ($by + $S * $py);
            imagefilledellipse($im, $x, $y, $lens, $lens, $this->col($im, [62, 64, 70]));
            imagefilledellipse($im, $x, $y, (int) ($lens * 0.72), (int) ($lens * 0.72), $this->col($im, [12, 13, 18]));
            imagefilledellipse($im, $x - 8, $y - 8, (int) ($lens * 0.22), (int) ($lens * 0.22), $this->col($im, [120, 140, 200], 50));
        }
        imagefilledellipse($im, (int) ($bx + $S * ($style === 'pill' ? 0.12 : 0.12)), (int) ($by + $S * 0.135), 22, 22, $this->col($im, [245, 233, 200]));
    }

    /** Diagonal light band clipped to the inner rectangle of a shape. */
    protected function glare(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $alpha = 105, float $offset = 0.0): void
    {
        $w = $x2 - $x1; $h = $y2 - $y1;
        $band = [
            [$x1 - $w, $y1 + $h * (0.62 + $offset)],
            [$x2 + $w, $y1 + $h * (0.12 + $offset)],
            [$x2 + $w, $y1 + $h * (0.2 + $offset)],
            [$x1 - $w, $y1 + $h * (0.70 + $offset)],
        ];
        $poly = $this->clipPolygon($band, $x1 + 6, $y1 + $r, $x2 - 6, $y2 - $r);
        if (count($poly) >= 3) {
            $flat = [];
            foreach ($poly as [$px, $py]) {
                $flat[] = (int) $px;
                $flat[] = (int) $py;
            }
            imagefilledpolygon($im, $flat, $this->col($im, [255, 255, 255], $alpha));
        }
    }

    /** Sutherland–Hodgman clip of a polygon against an axis-aligned rectangle. */
    protected function clipPolygon(array $pts, float $xmin, float $ymin, float $xmax, float $ymax): array
    {
        $edges = [
            fn ($p) => $p[0] >= $xmin, fn ($p) => $p[0] <= $xmax,
            fn ($p) => $p[1] >= $ymin, fn ($p) => $p[1] <= $ymax,
        ];
        $intersect = [
            fn ($a, $b) => [$xmin, $a[1] + ($b[1] - $a[1]) * ($xmin - $a[0]) / (($b[0] - $a[0]) ?: 1e-9)],
            fn ($a, $b) => [$xmax, $a[1] + ($b[1] - $a[1]) * ($xmax - $a[0]) / (($b[0] - $a[0]) ?: 1e-9)],
            fn ($a, $b) => [$a[0] + ($b[0] - $a[0]) * ($ymin - $a[1]) / (($b[1] - $a[1]) ?: 1e-9), $ymin],
            fn ($a, $b) => [$a[0] + ($b[0] - $a[0]) * ($ymax - $a[1]) / (($b[1] - $a[1]) ?: 1e-9), $ymax],
        ];
        foreach ($edges as $i => $inside) {
            $out = [];
            $n = count($pts);
            for ($k = 0; $k < $n; $k++) {
                $cur = $pts[$k]; $prev = $pts[($k + $n - 1) % $n];
                if ($inside($cur)) {
                    if (! $inside($prev)) {
                        $out[] = $intersect[$i]($prev, $cur);
                    }
                    $out[] = $cur;
                } elseif ($inside($prev)) {
                    $out[] = $intersect[$i]($prev, $cur);
                }
            }
            $pts = $out;
            if (! $pts) {
                break;
            }
        }

        return $pts;
    }

    /** Makes the corners of a layer outside a rounded rect transparent. */
    protected function clipRoundRect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r): void
    {
        imagealphablending($im, false);
        $clear = imagecolorallocatealpha($im, 0, 0, 0, 127);
        $W = imagesx($im); $H = imagesy($im);
        imagefilledrectangle($im, 0, 0, $W, $y1 - 1, $clear);
        imagefilledrectangle($im, 0, $y2 + 1, $W, $H, $clear);
        imagefilledrectangle($im, 0, 0, $x1 - 1, $H, $clear);
        imagefilledrectangle($im, $x2 + 1, 0, $W, $H, $clear);
        $corners = [[$x1 + $r, $y1 + $r, $x1, $y1], [$x2 - $r, $y1 + $r, $x2 - $r, $y1], [$x1 + $r, $y2 - $r, $x1, $y2 - $r], [$x2 - $r, $y2 - $r, $x2 - $r, $y2 - $r]];
        foreach ($corners as [$cx, $cy, $sx, $sy]) {
            for ($y = $sy; $y <= $sy + $r; $y++) {
                for ($x = $sx; $x <= $sx + $r; $x++) {
                    if (($x - $cx) ** 2 + ($y - $cy) ** 2 > $r * $r) {
                        imagesetpixel($im, $x, $y, $clear);
                    }
                }
            }
        }
        imagealphablending($im, true);
    }

    /** Thick smooth stroke along a parametric path t∈[0,1], optional braid texture. */
    protected function stroke(GdImage $im, callable $path, int $thickness, array $c, int $steps = 400, bool $braid = false): void
    {
        $col = $this->col($im, $c);
        for ($i = 0; $i <= $steps; $i++) {
            [$x, $y] = $path($i / $steps);
            imagefilledellipse($im, (int) $x, (int) $y, $thickness, $thickness, $col);
        }
        if ($braid) {
            $hi = $this->col($im, $this->shade($c, 0.22), 40);
            for ($i = 0; $i <= $steps; $i += 3) {
                [$x, $y] = $path($i / $steps);
                $o = ($i % 6 === 0 ? 1 : -1) * $thickness * 0.18;
                imagefilledellipse($im, (int) ($x + $o), (int) ($y - $o), (int) ($thickness * 0.35), (int) ($thickness * 0.35), $hi);
            }
        }
    }

    protected function softEllipse(GdImage $im, float $cx, int $cy, int $w, int $h, array $c, int $layers = 20): void
    {
        for ($i = $layers; $i > 0; $i--) {
            $f = $i / $layers;
            imagefilledellipse($im, (int) $cx, $cy, (int) ($w * $f), (int) ($h * $f), imagecolorallocatealpha($im, $c[0], $c[1], $c[2], 122));
        }
    }

    protected function copyScaled(GdImage $dst, GdImage $src, float $scale, int $dx, int $dy, int $fade = 0): void
    {
        $S = $this->S;
        $sw = imagesx($src); $sh = imagesy($src);
        $tw = (int) ($sw * $scale * $S / max($sw, $sh) * ($sw / $S > 1 ? 1 : 1));
        $tw = (int) ($S * $scale * $sw / $S); $th = (int) ($S * $scale * $sh / $S);
        $tmp = $this->blank($tw, $th);
        imagealphablending($tmp, false);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        imagealphablending($tmp, true);
        if ($fade > 0) {
            imagefilter($tmp, IMG_FILTER_COLORIZE, 0, 0, 0, $fade);
        }
        imagecopy($dst, $tmp, (int) (($S - $tw) / 2 + $dx), (int) (($S - $th) / 2 + $dy), 0, 0, $tw, $th);
        imagedestroy($tmp);
    }

    protected function roundRect(GdImage $im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
        if ($r === 0) {
            imagefilledrectangle($im, $x1, $y1, $x2, $y2, $color);

            return;
        }
        // Draw as one polygon (no overlapping alpha) approximating the arcs.
        $pts = [];
        $corners = [[$x2 - $r, $y1 + $r, -90], [$x2 - $r, $y2 - $r, 0], [$x1 + $r, $y2 - $r, 90], [$x1 + $r, $y1 + $r, 180]];
        foreach ($corners as [$cx, $cy, $start]) {
            for ($a = 0; $a <= 90; $a += 6) {
                $rad = deg2rad($start + $a);
                $pts[] = (int) round($cx + cos($rad) * $r);
                $pts[] = (int) round($cy + sin($rad) * $r);
            }
        }
        imagefilledpolygon($im, $pts, $color);
    }

    protected function blank(int $w, int $h): GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);
        imagesavealpha($im, true);

        return $im;
    }

    protected function col(GdImage $im, array $c, int $alpha = 0): int
    {
        return imagecolorallocatealpha($im, max(0, min(255, (int) $c[0])), max(0, min(255, (int) $c[1])), max(0, min(255, (int) $c[2])), $alpha);
    }

    public function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    protected function shade(array $c, float $amt): array
    {
        return array_map(fn ($v) => $amt < 0 ? $v * (1 + $amt) : $v + (255 - $v) * $amt, $c);
    }

    protected function mixStops(array $stops, float $t): array
    {
        $n = count($stops) - 1;
        $pos = min($n - 0.0001, max(0, $t * $n));
        $i = (int) floor($pos);
        $f = $pos - $i;
        $a = $stops[$i]; $b = $stops[min($i + 1, $n)];

        return [$a[0] + ($b[0] - $a[0]) * $f, $a[1] + ($b[1] - $a[1]) * $f, $a[2] + ($b[2] - $a[2]) * $f];
    }
}
