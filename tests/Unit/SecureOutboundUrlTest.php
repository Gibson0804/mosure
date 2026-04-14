<?php

namespace Tests\Unit;

use App\Support\SecureOutboundUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecureOutboundUrlTest extends TestCase
{
    #[Test]
    public function it_blocks_loopback_ip_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SecureOutboundUrl::assertAllowed('http://127.0.0.1/test');
    }

    #[Test]
    public function it_blocks_link_local_metadata_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SecureOutboundUrl::assertAllowed('http://169.254.169.254/latest/meta-data');
    }

    #[Test]
    public function it_allows_public_ip_urls(): void
    {
        $this->expectNotToPerformAssertions();

        SecureOutboundUrl::assertAllowed('https://8.8.8.8/file.png');
    }
}
