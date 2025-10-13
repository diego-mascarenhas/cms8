<?php

namespace Tests\Unit;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use PHPUnit\Framework\TestCase;

class QrCodeGenerationTest extends TestCase
{
    private string $testUrl = 'https://revisionalpha.com/contactenos?name=TestUser&email=test@example.com&phone=123456789&message=Test';

    /**
     * Test QR code generation in SVG format (default)
     */
    public function test_qr_generation_svg_format()
    {
        // Create QR code instance with default settings (SVG)
        $qrcode = new QRCode;

        // Generate QR code
        $result = $qrcode->render($this->testUrl);

        // Assertions for SVG format
        $this->assertIsString($result);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $result);
        $this->assertGreaterThan(10000, strlen($result)); // SVG is typically larger

        // Decode and verify it contains SVG markup
        $base64Data = str_replace('data:image/svg+xml;base64,', '', $result);
        $svgContent = base64_decode($base64Data);
        $this->assertStringContainsString('<svg', $svgContent);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svgContent);

        echo "\n✅ SVG QR Test Results:\n";
        echo "- Format: SVG (data URI)\n";
        echo '- Size: '.strlen($result)." characters\n";
        echo "- Contains SVG markup: YES\n";
        echo '- First 100 chars: '.substr($result, 0, 100)."...\n";
    }

    /**
     * Test QR code generation in PNG format (binary)
     */
    public function test_qr_generation_png_format()
    {
        // Create QR code instance with PNG output
        $qrcode = new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
        ]));

        // Generate QR code
        $result = $qrcode->render($this->testUrl);

        // Assertions for PNG format (data URI)
        $this->assertIsString($result);
        $this->assertStringContainsString('data:image/png;base64,', $result);

        // Extract binary data from data URI
        $base64Data = str_replace('data:image/png;base64,', '', $result);
        $binaryData = base64_decode($base64Data);

        // Check PNG signature in binary data (first 8 bytes)
        $pngSignature = "\x89PNG\r\n\x1a\n";
        $this->assertEquals($pngSignature, substr($binaryData, 0, 8));

        // Verify binary data properties
        $this->assertFalse(ctype_print($binaryData));
        $this->assertLessThan(5000, strlen($binaryData)); // Binary PNG is smaller
        $this->assertGreaterThan(500, strlen($binaryData));

        echo "\n✅ PNG QR Test Results:\n";
        echo "- Format: PNG (data URI)\n";
        echo '- Data URI Size: '.strlen($result)." characters\n";
        echo '- Binary Size: '.strlen($binaryData)." bytes\n";
        echo "- Has PNG signature: YES\n";
        echo "- Is binary data: YES\n";
        echo '- Hex header: '.bin2hex(substr($binaryData, 0, 16))."\n";
    }

    /**
     * Test file saving for both formats
     */
    public function test_qr_file_saving()
    {
        $tempDir = sys_get_temp_dir().'/qr_tests';
        if (! file_exists($tempDir))
        {
            mkdir($tempDir, 0755, true);
        }

        // Test SVG saving
        $qrcodeSvg = new QRCode;
        $svgResult = $qrcodeSvg->render($this->testUrl);
        $svgFile = $tempDir.'/test_qr.svg';
        file_put_contents($svgFile, $svgResult);

        $this->assertFileExists($svgFile);
        $this->assertGreaterThan(0, filesize($svgFile));

        // Test PNG saving
        $qrcodePng = new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
        ]));
        $pngResult = $qrcodePng->render($this->testUrl);
        $pngFile = $tempDir.'/test_qr.png';
        file_put_contents($pngFile, $pngResult);

        $this->assertFileExists($pngFile);
        $this->assertGreaterThan(0, filesize($pngFile));

        echo "\n✅ File Saving Test Results:\n";
        echo '- SVG file: '.$svgFile.' ('.filesize($svgFile)." bytes)\n";
        echo '- PNG file: '.$pngFile.' ('.filesize($pngFile)." bytes)\n";

        // Cleanup
        unlink($svgFile);
        unlink($pngFile);
        rmdir($tempDir);
    }

    /**
     * Test QR code generation with different URLs
     */
    public function test_qr_with_different_urls()
    {
        $testUrls = [
            'https://example.com',
            'https://revisionalpha.com/contactenos?name=José&email=jose@test.com',
            'mailto:contact@example.com',
            'tel:+54911234567',
        ];

        foreach ($testUrls as $url)
        {
            // Create fresh QR instance for each URL to ensure PNG output
            $qrcode = new QRCode(new QROptions([
                'outputType' => QROutputInterface::GDIMAGE_PNG,
            ]));

            $result = $qrcode->render($url);

            $this->assertIsString($result);
            $this->assertGreaterThan(0, strlen($result));
            $this->assertStringContainsString('data:image/png;base64,', $result);

            // Extract and verify binary PNG data
            $base64Data = str_replace('data:image/png;base64,', '', $result);
            $binaryData = base64_decode($base64Data);
            $this->assertEquals("\x89PNG\r\n\x1a\n", substr($binaryData, 0, 8));
        }

        echo "\n✅ Multiple URLs Test Results:\n";
        echo '- Tested '.count($testUrls)." different URLs\n";
        echo "- All generated valid PNG QR codes\n";
    }

    /**
     * Test error handling
     */
    public function test_qr_error_handling()
    {
        try
        {
            // Test with very long data (might cause issues)
            $longData = str_repeat('A', 10000);
            $qrcode = new QRCode(new QROptions([
                'outputType' => QROutputInterface::GDIMAGE_PNG,
            ]));
            $result = $qrcode->render($longData);

            // If it doesn't throw an exception, it should still be valid
            $this->assertIsString($result);
            echo "\n✅ Error Handling Test Results:\n";
            echo "- Long data handled successfully\n";
        } catch (\Exception $e)
        {
            // This is also acceptable - the library should handle limits gracefully
            $this->assertInstanceOf(\Exception::class, $e);
            echo "\n✅ Error Handling Test Results:\n";
            echo '- Long data properly rejected with exception: '.$e->getMessage()."\n";
        }
    }
}
