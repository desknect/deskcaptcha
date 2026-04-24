<?php

/**
 * @see https://dcaptcha.desknect.com/api-documentacao
 * @see https://desknect.com
 */

namespace DeskCaptcha\Captcha;

use DeskCaptcha\Config;

class Generator
{
    private array $config;
    private array $cores;
    private array $fonts;
    private array $componentes;
    private int   $scale;
    private int   $chars;

    public function __construct(int $scale = 1, int $chars = 4)
    {
        $this->config      = Config::api();
        $configData        = json_decode(file_get_contents(__DIR__ . '/../../data/captcha-config.json'), true);
        $this->cores       = $configData['cores'];
        $this->fonts       = $configData['fonts'];
        $this->componentes = json_decode(file_get_contents(__DIR__ . '/../../data/captcha-componentes.json'), true);
        $this->scale       = $scale;
        $this->chars       = $chars;
    }

    public function generate(): array
    {
        $scale  = $this->scale;
        $w      = $this->config['image_width']  * $scale;
        $h      = $this->config['image_height'] * $scale;

        // Pick random variants
        $vForma1  = $this->rand('forma1');
        $vForma2  = $this->rand('forma2');
        $vForma3  = $this->rand('forma3');
        $vForma4  = $this->rand('forma4');
        $vReta1   = $this->rand('reta1');
        $vReta2   = $this->rand('reta2');

        // Build character sequence: always L N L N ...
        $letras  = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $numeros = '123456789';
        $sequence = [];
        for ($i = 0; $i < $this->chars; $i++) {
            if ($i % 2 === 0) {
                $sequence[] = ['type' => 'letra',  'char' => $letras[random_int(0, strlen($letras) - 1)]];
            } else {
                $sequence[] = ['type' => 'numero', 'char' => $numeros[random_int(0, strlen($numeros) - 1)]];
            }
        }

        $answer = implode('', array_column($sequence, 'char'));

        // Build variant list for chars
        $charVariants = [];
        $letterIdx = 1;
        $numberIdx = 1;
        foreach ($sequence as $item) {
            if ($item['type'] === 'letra') {
                $key = 'letra' . (($letterIdx <= 2) ? $letterIdx : (($letterIdx % 2) + 1));
                $charVariants[] = ['char' => $item['char'], 'variant' => $this->rand($key)];
                $letterIdx++;
            } else {
                $key = 'numero' . (($numberIdx <= 2) ? $numberIdx : (($numberIdx % 2) + 1));
                $charVariants[] = ['char' => $item['char'], 'variant' => $this->rand($key)];
                $numberIdx++;
            }
        }

        // Create image
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        // Render layers
        $this->drawLine($img, $vReta1);
        $this->drawCircle($img, $vForma1, (int)(100 * $scale));
        $this->drawTriangle($img, $vForma2, (int)(300 * $scale));
        $this->drawFormaTexto($img, $vForma3, __DIR__ . '/../../fonts/fontforma3.ttf');
        $this->drawFormaTexto($img, $vForma4, __DIR__ . '/../../fonts/fontforma4.otf');
        $this->drawLine($img, $vReta2);

        // Draw characters distributed across width
        $numChars   = count($charVariants);
        $quadWidth  = $w / $numChars;
        foreach ($charVariants as $idx => $cv) {
            $qcx = (int)($quadWidth * $idx + $quadWidth / 2);
            $this->drawChar($img, $cv['char'], $cv['variant'], $qcx);
        }

        // Save
        $filename = $this->generateFilename();
        $filepath = rtrim($this->config['storage_dir'], '/') . '/' . $filename;
        imagepng($img, $filepath);
        imagedestroy($img);

        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'answer'   => $answer,
            'scale'    => $scale,
            'chars'    => $this->chars,
        ];
    }

    // -------------------------------------------------------------------------
    // Drawing helpers
    // -------------------------------------------------------------------------

    private function drawLine($img, array $reta): void
    {
        $s     = $this->scale;
        $color = $this->allocColor($img, $reta['cor'], $reta['transparencia']);
        $esp   = (int)$reta['espessura'];
        if ($esp > 1) imagesetthickness($img, $esp * $s);
        $h = $this->config['image_height'] * $s;
        $w = $this->config['image_width']  * $s;
        imageline($img, 0, (int)$reta['altura-left'] * $s, $w - 1, (int)$reta['altura-right'] * $s, $color);
        imagesetthickness($img, 1);
    }

    private function drawCircle($img, array $forma, int $cxNatural): void
    {
        $s  = $this->scale;
        $cx = $cxNatural + (int)$forma['posicaoX'] * $s;
        $cy = (int)($this->config['image_height'] / 2 * $s) + (int)$forma['posicaoY'] * $s;
        $fw = (int)$forma['tamanhoX'] * $s;
        $fh = (int)$forma['tamanhoY'] * $s;

        $fill = $this->allocColor($img, $forma['cor'], $forma['transparencia']);
        imagefilledellipse($img, $cx, $cy, $fw, $fh, $fill);

        if ((int)$forma['borda-espessura'] > 0) {
            $border = $this->allocColor($img, $forma['borda-cor'], 0);
            imagesetthickness($img, (int)$forma['borda-espessura'] * $s);
            imageellipse($img, $cx, $cy, $fw, $fh, $border);
            imagesetthickness($img, 1);
        }
    }

    private function drawTriangle($img, array $forma, int $cxNatural): void
    {
        $s  = $this->scale;
        $cx = $cxNatural + (int)$forma['posicaoX'] * $s;
        $cy = (int)($this->config['image_height'] / 2 * $s) + (int)$forma['posicaoY'] * $s;
        $fw = (int)$forma['tamanhoX'] * $s;
        $fh = (int)$forma['tamanhoY'] * $s;

        $points = [
            $cx,         $cy - $fh / 2,
            $cx - $fw / 2, $cy + $fh / 2,
            $cx + $fw / 2, $cy + $fh / 2,
        ];

        $fill = $this->allocColor($img, $forma['cor'], $forma['transparencia']);
        imagefilledpolygon($img, $points, $fill);

        if ((int)$forma['borda-espessura'] > 0) {
            $border = $this->allocColor($img, $forma['borda-cor'], 0);
            imagesetthickness($img, (int)$forma['borda-espessura'] * $s);
            imagepolygon($img, $points, $border);
            imagesetthickness($img, 1);
        }
    }

    private function drawFormaTexto($img, array $forma, string $fontPath): void
    {
        $s     = $this->scale;
        $cx    = (int)$forma['posicaoX'];
        $cy    = (int)$forma['posicaoY'];
        $size  = (int)$forma['tamanho'] * $s;
        $angle = (float)$forma['rotacao'];
        $char  = $forma['fontforma'];

        if (!file_exists($fontPath)) return;

        $bbox   = imagettfbbox($size, 0, $fontPath, $char);
        $tw     = $bbox[2] - $bbox[0];
        $th     = $bbox[1] - $bbox[7];
        $drawX  = (int)($cx - $tw / 2 - $bbox[0]);
        $drawY  = (int)($cy + $th / 2);

        $color = $this->allocColor($img, $forma['cor'], $forma['transparencia']);
        imagettftext($img, $size, $angle, $drawX, $drawY, $color, $fontPath, $char);
    }

    private function drawChar($img, string $char, array $variant, int $qcx): void
    {
        $s     = $this->scale;
        $font  = $this->resolveFont((int)$variant['font']);
        $size  = (int)$variant['tamanho'] * $s;
        $angle = (float)$variant['rotacao'];
        $tx    = $qcx + (int)$variant['posicaoX'] * $s;
        $ty    = (int)($this->config['image_height'] / 2 * $s) + (int)$variant['posicaoY'] * $s;

        $bbox   = imagettfbbox($size, 0, $font, $char);
        $tw     = $bbox[2] - $bbox[0];
        $th     = $bbox[1] - $bbox[7];
        $drawX  = (int)($tx - $tw / 2 - $bbox[0]);
        $drawY  = (int)($ty + $th / 2);

        $color = $this->allocColor($img, $variant['cor'], 0);
        imagettftext($img, $size, $angle, $drawX, $drawY, $color, $font, $char);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function allocColor($img, int $colorId, int $transparencia): int
    {
        $cor   = $this->getCor($colorId);
        [$r, $g, $b] = array_map('intval', explode(',', $cor['rgb']));
        $alpha = $transparencia <= 0 ? 0 : (int)round($transparencia * 127 / 59);
        $alpha = min(127, max(0, $alpha));
        return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
    }

    private function getCor(int $id): array
    {
        foreach ($this->cores as $c) {
            if ($c['id'] === $id) return $c;
        }
        return $this->cores[0];
    }

    private function resolveFont(int $id): string
    {
        foreach ($this->fonts as $f) {
            if ($f['id'] === $id) {
                return __DIR__ . '/../../fonts/' . basename($f['arquivo']);
            }
        }
        return __DIR__ . '/../../fonts/font1.ttf';
    }

    private function rand(string $key): array
    {
        $arr = $this->componentes[$key];
        return $arr[array_rand($arr)];
    }

    private function generateFilename(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $rand  = '';
        for ($i = 0; $i < 8; $i++) {
            $rand .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $rand . date('YmdHis') . '.png';
    }
}
