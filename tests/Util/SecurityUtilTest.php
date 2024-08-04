<?php

namespace App\Tests\Util;

use App\Util\AppUtil;
use App\Util\JsonUtil;
use App\Util\SecurityUtil;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class SecurityUtilTest
 *
 * Test the security util
 *
 * @package App\Tests\Util
 */
class SecurityUtilTest extends TestCase
{
    /** @var SecurityUtil */
    private SecurityUtil $securityUtil;

    /** @var JsonUtil|MockObject */
    private JsonUtil|MockObject $jsonUtilMock;

    /** @var KernelInterface|MockObject */
    private KernelInterface|MockObject $kernelInterface;

    protected function setUp(): void
    {
        $this->jsonUtilMock = $this->createMock(JsonUtil::class);
        $this->kernelInterface = $this->createMock(KernelInterface::class);

        $this->securityUtil = new SecurityUtil(new AppUtil($this->jsonUtilMock, $this->kernelInterface));
    }

    /**
     * Test XSS escaping
     *
     * @return void
     */
    public function testEscapeXss(): void
    {
        // test escaping a string with special characters
        $input = '<script>alert("XSS");</script>';
        $expectedOutput = '&lt;script&gt;alert(&quot;XSS&quot;);&lt;/script&gt;';
        $this->assertEquals($expectedOutput, $this->securityUtil->escapeString($input));
    }

    /**
     * Test security escaping without XSS
     *
     * @return void
     */
    public function testEscapeNonXss(): void
    {
        // test escaping a string without special characters
        $input = 'Hello, World!';
        $expectedOutput = 'Hello, World!';
        $this->assertEquals($expectedOutput, $this->securityUtil->escapeString($input));
    }

    /**
     * Tests generating an Argon2 hash for a password
     *
     * @return void
     */
    public function testGenerateHash(): void
    {
        $password = 'testPassword123';
        $hash = $this->securityUtil->generateHash($password);

        // assert that the hash is not false or null
        $this->assertNotFalse($hash);
        $this->assertNotNull($hash);

        // assert that the hash is a valid Argon2 hash
        $info = password_get_info($hash);
        $this->assertEquals('argon2id', $info['algoName']);
    }

    /**
     * Tests verifying a password using an Argon2 hash
     *
     * @return void
     */
    public function testVerifyPasswordValid(): void
    {
        $password = 'testPassword123';
        $hash = $this->securityUtil->generateHash($password);

        // verify the password with the correct hash
        $this->assertTrue($this->securityUtil->verifyPassword('testPassword123', $hash));
    }

    /**
     * Tests verifying an invalid password using an Argon2 hash
     *
     * @return void
     */
    public function testVerifyPasswordInvalid(): void
    {
        $password = 'testPassword123';
        $hash = $this->securityUtil->generateHash($password);

        // verify the password with an incorrect hash
        $this->assertFalse($this->securityUtil->verifyPassword('wrongPassword123', $hash));
    }

    /**
     * Test encryptAes method
     *
     * @return void
     */
    public function testEncryptAes(): void
    {
        $encryptedData = $this->securityUtil->encryptAes('test value');
        $decryptedData = $this->securityUtil->decryptAes($encryptedData);

        // assert
        $this->assertSame('test value', $decryptedData);
    }
}
