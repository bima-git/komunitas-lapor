<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageResizer
{
    /**
     * Kecilkan foto ke resolusi maksimal 1280px pada sisi terpanjang, format JPEG q=80,
     * mempertahankan rasio dan orientasi EXIF, lalu simpan ke storage.
     *
     * Jika terjadi kegagalan (file rusak / GD tidak tersedia), gunakan file asli sebagai fallback
     * tanpa menggagalkan pengiriman laporan dan catat Log::warning tanpa data sensitif.
     */
    public function resizeAndStore(UploadedFile $file, string $directory = 'reports', string $disk = 'public'): string
    {
        try {
            $resizedData = $this->resize($file, 1280, 80);

            if ($resizedData !== null) {
                $filename = Str::random(40).'.jpg';
                $path = trim($directory, '/').'/'.$filename;
                Storage::disk($disk)->put($path, $resizedData);

                return $path;
            }
        } catch (\Throwable $e) {
            Log::warning('Pengecilan gambar gagal, fallback ke file asli: '.$e->getMessage(), [
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        // Fallback jika proses resize gagal
        return $file->store($directory, $disk);
    }

    /**
     * Pengecilan gambar murni menggunakan ekstensi GD bawaan PHP.
     *
     * @return string|null Mengembalikan data binary JPEG atau null jika gagal.
     */
    public function resize(UploadedFile|string $file, int $maxDimension = 1280, int $quality = 80): ?string
    {
        if (! extension_loaded('gd')) {
            Log::warning('Ekstensi GD tidak tersedia untuk pengecilan gambar.');

            return null;
        }

        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return null;
        }

        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            return null;
        }

        // Buat resource gambar dari data biner
        $image = @imagecreatefromstring($fileContents);
        if (! $image) {
            return null;
        }

        // Koreksi orientasi EXIF jika tersedia
        $image = $this->correctExifOrientation($image, $filePath);

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            return null;
        }

        // Hitung resolusi baru dengan mempertahankan rasio aspek
        $longestSide = max($width, $height);
        if ($longestSide > $maxDimension) {
            if ($width >= $height) {
                $newWidth = $maxDimension;
                $newHeight = (int) round(($height * $maxDimension) / $width);
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int) round(($width * $maxDimension) / $height);
            }
        } else {
            // Jika kedua sisi <= 1280, pertahankan ukuran asli
            $newWidth = $width;
            $newHeight = $height;
        }

        // Buat kanvas gambar baru
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Beri background putih untuk menangani transparansi PNG / WebP ke JPEG
        $white = imagecolorallocate($resizedImage, 255, 255, 255);
        imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $white);

        // Salin dan resample piksel gambar
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Konversi ke format JPEG dengan kualitas 80
        ob_start();
        imagejpeg($resizedImage, null, $quality);
        $jpegData = ob_get_clean();

        imagedestroy($image);
        imagedestroy($resizedImage);

        return $jpegData ?: null;
    }

    /**
     * Koreksi rotasi/flip gambar sesuai tag EXIF Orientation (bila ada).
     */
    private function correctExifOrientation(\GdImage $image, string $filePath): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($filePath);
            $orientation = $exif['Orientation'] ?? null;

            if ($orientation) {
                switch ($orientation) {
                    case 2:
                        imageflip($image, IMG_FLIP_HORIZONTAL);
                        break;
                    case 3:
                        $rotated = imagerotate($image, 180, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            $image = $rotated;
                        }
                        break;
                    case 4:
                        imageflip($image, IMG_FLIP_VERTICAL);
                        break;
                    case 5:
                        $rotated = imagerotate($image, -90, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            $image = $rotated;
                            imageflip($image, IMG_FLIP_HORIZONTAL);
                        }
                        break;
                    case 6:
                        $rotated = imagerotate($image, -90, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            $image = $rotated;
                        }
                        break;
                    case 7:
                        $rotated = imagerotate($image, 90, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            $image = $rotated;
                            imageflip($image, IMG_FLIP_HORIZONTAL);
                        }
                        break;
                    case 8:
                        $rotated = imagerotate($image, 90, 0);
                        if ($rotated !== false) {
                            imagedestroy($image);
                            $image = $rotated;
                        }
                        break;
                }
            }
        } catch (\Throwable) {
            // Jika EXIF tidak terbaca, lanjutkan dengan gambar yang ada
        }

        return $image;
    }
}
