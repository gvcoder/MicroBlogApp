<?php

declare(strict_types=1);

/**
 * Unit tests for validation helper functions in middleware.php
 * Requirements: 20.1, 20.2, 20.3, 20.4, 20.5
 */

use PHPUnit\Framework\TestCase;

require_once API_PATH . '/auth/middleware.php';

/**
 * @coversNothing
 */
final class ValidationTest extends TestCase
{
    // ── Email ─────────────────────────────────────────────────────────────

    public function testValidEmailReturnsTrue(): void
    {
        $this->assertTrue(validateEmailFormat('user@example.com'));
        $this->assertTrue(validateEmailFormat('user+tag@sub.domain.org'));
    }

    public function testInvalidEmailReturnsFalse(): void
    {
        $this->assertFalse(validateEmailFormat('not-an-email'));
        $this->assertFalse(validateEmailFormat('missing@'));
        $this->assertFalse(validateEmailFormat('@nodomain.com'));
        $this->assertFalse(validateEmailFormat(''));
    }

    // ── Title ─────────────────────────────────────────────────────────────

    /** Req 20.1 */
    public function testTitleWithin200CharsIsValid(): void
    {
        $this->assertTrue(validateNoteTitle(str_repeat('a', 200)));
        $this->assertTrue(validateNoteTitle('Short title'));
    }

    /** Req 20.1 */
    public function testTitleExceeding200CharsIsInvalid(): void
    {
        $this->assertFalse(validateNoteTitle(str_repeat('a', 201)));
    }

    public function testEmptyTitleIsValid(): void
    {
        // Validation helper only checks length; emptiness is checked separately
        $this->assertTrue(validateNoteTitle(''));
    }

    // ── Content ───────────────────────────────────────────────────────────

    /** Req 20.2 */
    public function testContentWithin10000CharsIsValid(): void
    {
        $this->assertTrue(validateNoteContent(str_repeat('x', 10000)));
        $this->assertTrue(validateNoteContent('Short content'));
    }

    /** Req 20.2 */
    public function testContentExceeding10000CharsIsInvalid(): void
    {
        $this->assertFalse(validateNoteContent(str_repeat('x', 10001)));
    }

    // ── Tag ───────────────────────────────────────────────────────────────

    /** Req 20.3 */
    public function testTagWithin50CharsIsValid(): void
    {
        $this->assertTrue(validateTagName(str_repeat('t', 50)));
        $this->assertTrue(validateTagName('php'));
    }

    /** Req 20.3 */
    public function testTagExceeding50CharsIsInvalid(): void
    {
        $this->assertFalse(validateTagName(str_repeat('t', 51)));
    }

    public function testEmptyTagIsValid(): void
    {
        $this->assertTrue(validateTagName(''));
    }

    // ── Boundary values ───────────────────────────────────────────────────

    public function testTitleExactly200CharsIsValid(): void
    {
        $this->assertTrue(validateNoteTitle(str_repeat('a', 200)));
    }

    public function testContentExactly10000CharsIsValid(): void
    {
        $this->assertTrue(validateNoteContent(str_repeat('x', 10000)));
    }

    public function testTagExactly50CharsIsValid(): void
    {
        $this->assertTrue(validateTagName(str_repeat('t', 50)));
    }
}
